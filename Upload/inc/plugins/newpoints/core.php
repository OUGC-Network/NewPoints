<?php

/***************************************************************************
 *
 *   Newpoints plugin (/inc/plugins/newpoints/core.php)
 *   Author: Pirata Nervo
 *   Copyright: © 2009 Pirata Nervo
 *   Copyright: © 2024 Omar Gonzalez
 *
 *   Website: https://ougc.network
 *
 *   NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
 *
 ***************************************************************************/

/****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace Newpoints\Core;

use pluginSystem;

use function send_pm;

use const Newpoints\ROOT;

function language_load(string $plugin = ''): bool
{
    global $lang;

    if ($plugin === '') {
        isset($lang->newpoints) || $lang->load('newpoints');
    } else {
        $lang->set_path(MYBB_ROOT . 'inc/plugins/newpoints/languages');
        $lang->load($plugin);
        $lang->set_path(MYBB_ROOT . 'inc/languages');
    }

    return true;
}

function add_hooks(string $namespace): bool
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }

    return true;
}

function run_hooks(string $hook_name = '', mixed &$hook_arguments = ''): mixed
{
    global $plugins;

    if ($plugins instanceof pluginSystem) {
        $hook_arguments = $plugins->run_hooks('newpoints_' . $hook_name, $hook_arguments);
    }

    return $hook_arguments;
}

function get_setting(string $setting_key = '')
{
    global $mybb;

    return isset(SETTINGS[$setting_key]) ? SETTINGS[$setting_key] : (
    isset($mybb->settings['newpoints_' . $setting_key]) ? $mybb->settings['newpoints_' . $setting_key] : false
    );
}

/**
 * Somewhat like htmlspecialchars_uni but for JavaScript strings
 *
 * @param string: The string to be parsed
 * @return string: Javascript compatible string
 */
function js_special_characters(string $str): string
{
    // Converts & -> &amp; allowing Unicode
    // Parses out HTML comments as the XHTML validator doesn't seem to like them
    $string = preg_replace(['#\<\!--.*?--\>#', '#&(?!\#[0-9]+;)#'], ['', '&amp;'], $str);
    return strtr(
        $string,
        ["\n" => '\n', "\r" => '\r', '\\' => '\\\\', '"' => '\x22', "'" => '\x27', '<' => '&lt;', '>' => '&gt;']
    );
}

function count_characters(string $message): int
{
    // Attempt to remove any quotes
    $message = preg_replace([
        '#\[quote=([\"\']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"\']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si',
        '#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si',
        '#\[quote\]#si',
        '#\[\/quote\]#si'
    ], '', $message);

    // Attempt to remove any MyCode
    global $parser;
    if (!is_object($parser)) {
        require_once constant('MYBB_ROOT') . 'inc/class_parser.php';
        $parser = new postParser();
    }

    $message = $parser->parse_message($message, [
        'allow_html' => 0,
        'allow_mycode' => 1,
        'allow_smilies' => 0,
        'allow_imgcode' => 1,
        'filter_badwords' => 1,
        'nl2br' => 0
    ]);

    // before stripping tags, try converting some into spaces
    $message = preg_replace([
        '~\<(?:img|hr).*?/\>~si',
        '~\<li\>(.*?)\</li\>~si'
    ], [' ', "\n* $1"], $message);

    $message = unhtmlentities(strip_tags($message));

    // Remove all spaces?
    $message = trim_blank_chrs($message);
    $message = preg_replace('/\s+/', '', $message);

    // convert \xA0 to spaces (reverse &nbsp;)
    $message = trim(
        preg_replace(['~ {2,}~', "~\n{2,}~"],
            [' ', "\n"],
            strtr($message, ["\xA0" => utf8_encode("\xA0"), "\r" => '', "\t" => ' ']))
    );

    // newline fix for browsers which don't support them
    $message = preg_replace("~ ?\n ?~", " \n", $message);

    return (int)my_strlen($message);
}

/**
 * Deletes templates from the database
 *
 * @param string a list of templates seperated by ',' e.g. 'test','test_again','testing'
 * @return bool false if something went wrong
 *
 */
function templates_remove(string $templates): bool
{
    global $db;

    if (!$templates) {
        return false;
    }

    $db->delete_query('templates', 'title IN (' . $templates . ')');

    return true;
}

/**
 * Adds a new template
 *
 * @param string the title of the template
 * @param string the contents of the template
 * @param int the sid of the template
 * @return bool false if something went wrong
 *
 */
function templates_add(string $name, string $contents, $sid = -1): bool
{
    global $db;

    if (!$name || !$contents) {
        return false;
    }

    $templatearray = [
        'title' => $db->escape_string($name),
        'template' => $db->escape_string($contents),
        'sid' => intval($sid)
    ];

    $query = $db->simple_select(
        'templates',
        'tid,title,template',
        "sid='{$sid}' AND title='{$templatearray['title']}'"
    );

    $templates = [];
    $duplicates = [];

    while ($templ = $db->fetch_array($query)) {
        if (isset($templates[$templ['title']])) {
            $duplicates[$templ['tid']] = $templ['tid'];
            $templates[$templ['title']]['template'] = false;
        } else {
            $templates[$templ['title']] = $templ;
        }
    }

    // Remove duplicates
    if ($duplicates) {
        $db->delete_query('templates', 'tid IN (' . implode(',', $duplicates) . ')');
    }

    // Update if necessary, insert otherwise
    if (isset($templates[$name])) {
        if ($templates[$name]['template'] !== $contents) {
            return $db->update_query('templates', $templatearray, "tid={$templates[$name]['tid']}");
        }

        return false;
    }

    $db->insert_query('templates', $templatearray);

    return true;
}

function templates_get_name(string $template_name = ''): string
{
    $template_prefix = '';

    if ($template_name) {
        $template_prefix = '_';
    }

    return "newpoints{$template_prefix}{$template_name}";
}

function templates_get(string $template_name = '', bool $enable_html_comments = true): string
{
    global $templates;

    if (DEBUG) {
        $file_path = ROOT . "/templates/{$template_name}.html";

        $template_contents = file_get_contents($file_path);

        $templates->cache[templates_get_name($template_name)] = $template_contents;
    } elseif (my_strpos($template_name, '/') !== false) {
        $template_name = substr($template_name, strpos($template_name, '/') + 1);
    }

    return $templates->render(templates_get_name($template_name), true, $enable_html_comments);
}

/**
 * Adds a new set of templates
 *
 * @param string the key of the template plugin
 * @param array the array containing the templates data
 * @return bool false if something went wrong
 *
 */
function templates_rebuild(): bool
{
    global $db, $plugins;

    $prefix = 'newpoints';

    // Default templates
    $template_list = [
        'postbit' => '<br /><span class="smalltext">{$currency}: <a href="{$mybb->settings[\'bburl\']}/newpoints.php">{$points}</a></span>{$donate}',
        'profile' => '<tr>
	<td class="trow2"><strong>{$currency}:</strong></td>
	<td class="trow2"><a href="{$mybb->settings[\'bburl\']}/newpoints.php">{$points}</a>{$donate}</td>
</tr>',
        'donate_inline' => ' <span class="smalltext">[<a href="javascript: void(0);" onclick="MyBB.popupWindow(\'{$mybb->settings[\'bburl\']}/newpoints.php?action=donate&amp;uid={$uid}&amp;pid={$post[\'pid\']}&amp;modal=1\', null, true); return false;">{$lang->newpoints_donate}</a>]</span>',
        'donate_form' => '<form action="{$mybb->settings[\'bburl\']}/newpoints.php" method="POST">
<input type="hidden" name="postcode" value="{$mybb->post_code}" />
<input type="hidden" name="action" value="do_donate" />
<input type="hidden" name="pid" value="{$pid}" />
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead" colspan="2"><strong>{$lang->newpoints_donate}</strong></td>
</tr>
<tr>
<td class="trow1" width="50%"><strong>{$lang->newpoints_user}:</strong><br /><span class="smalltext">{$lang->newpoints_user_desc}</span></td>
<td class="trow1" width="50%"><input type="text" name="username" value="{$user[\'username\']}" class="textbox" id="username" size="20" /></td>
</tr>
<tr>
<td class="trow2" width="50%"><strong>{$lang->newpoints_amount}:</strong><br /><span class="smalltext">{$lang->newpoints_amount_desc}</span></td>
<td class="trow2" width="50%"><input type="text" name="amount" value="" class="textbox" size="20" /></td>
</tr>
<tr>
<td class="trow1" width="50%"><strong>{$lang->newpoints_reason}:</strong><br /><span class="smalltext">{$lang->newpoints_reason_desc}</span></td>
<td class="trow1" width="50%"><input type="text" name="reason" value="" class="textbox" size="20" /></td>
</tr>
<tr>
<td class="tfoot" width="100%" colspan="2" align="center"><input type="submit" name="submit" value="{$lang->newpoints_submit}" class="button" /></td>
</tr>
</table>
</form>',
        'modal' => '<div class="modal">
	<div style="overflow-y: auto; max-height: 400px;">
		{$code}
	</div>
</div>',
        'donate' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->newpoints} - {$lang->newpoints_donate}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
<td valign="top" width="180">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->newpoints_menu}</strong></td>
</tr>
{$options}
</table>
</td>
<td valign="top">
{$form}
</td>
</tr>
</table>
{$footer}
<link rel="stylesheet" href="{$mybb->asset_url}/jscripts/select2/select2.css">
<script type="text/javascript" src="{$mybb->asset_url}/jscripts/select2/select2.min.js?ver=1804"></script>
<script type="text/javascript">
<!--
if(use_xmlhttprequest == "1")
{
	MyBB.select2();
	$("#username").select2({
		placeholder: "{$lang->newpoints_search_user}",
		minimumInputLength: 3,
		maximumSelectionSize: 3,
		multiple: false,
		width: 150,
		ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
			url: "xmlhttp.php?action=get_users",
			dataType: \'json\',
			data: function (term, page) {
				return {
					query: term, // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var value = $(element).val();
			if (value !== "") {
				callback({
					id: value,
					text: value
				});
			}
		},
       // Allow the user entered text to be selected as well
       createSearchChoice:function(term, data) {
			if ( $(data).filter( function() {
				return this.text.localeCompare(term)===0;
			}).length===0) {
				return {id:term, text:term};
			}
		},
	});

  	$(\'[for=username]\').click(function(){
		$("#username").select2(\'open\');
		return false;
	});
}
// -->
</script>
</body>
</html>',
        'statistics' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->newpoints} - {$lang->newpoints_statistics}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
    <tr>
        <td valign="top" width="180">
            <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                <tr>
                <td class="thead"><strong>{$lang->newpoints_menu}</strong></td>
                </tr>
                {$options}
            </table>
        </td>
        <td valign="top">
            <table width="100%" border="0" align="center">
                <tr>
                    <td valign="top" width="40%">
                        <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                            <tr>
                                <td class="thead" colspan="2"><strong>{$lang->newpoints_richest_users}</strong></td>
                            </tr>
                            <tr>
                                <td class="tcat" width="50%"><strong>{$lang->newpoints_user}</strong></td>
                                <td class="tcat" width="50%" align="center"><strong>{$lang->newpoints_amount}</strong></td>
                            </tr>
                            {$richest_users}
                        </table>
                    </td>
                </tr>
            </table>
        </td>
        <td valign="top" width="60%">
            <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
                <tr>
                    <td class="thead" colspan="4"><strong>{$lang->newpoints_last_donations}</strong></td>
                </tr>
                <tr>
                    <td class="tcat" width="30%"><strong>{$lang->newpoints_from}</strong></td>
                    <td class="tcat" width="30%"><strong>{$lang->newpoints_to}</strong></td>
                    <td class="tcat" width="20%" align="center"><strong>{$lang->newpoints_amount}</strong></td>
                    <td class="tcat" width="20%" align="center"><strong>{$lang->newpoints_date}</strong></td>
                </tr>
                {$last_donations}
            </table>
        </td>
    </tr>
</table>
{$footer}
</body>
</html>',
        'statistics_richest_user' => '<tr>
<td class="{$bgcolor}" width="50%">{$user[\'username\']}</td>
<td class="{$bgcolor}" width="50%" align="center">{$user[\'newpoints\']}</td>
</tr>',
        'statistics_donation' => '<tr>
<td class="{$bgcolor}" width="30%">{$donation[\'from\']}</td>
<td class="{$bgcolor}" width="30%">{$donation[\'to\']}</td>
<td class="{$bgcolor}" width="20%" align="center">{$donation[\'amount\']}</td>
<td class="{$bgcolor}" width="20%" align="center">{$donation[\'date\']}</td>
</tr>',
        'no_results' => '<tr>
<td class="{$bgcolor}" width="100%" colspan="{$colspan}">{$no_results}</td>
</tr>',
        'option' => '<tr>
<td class="{$bgcolor}" width="100%">{$raquo}<a href="{$mybb->settings[\'bburl\']}/newpoints.php{$action}">{$lang_string}</a></td>
</tr>',
        'home' => '<html>
<head>
<title>{$mybb->settings[\'bbname\']} - {$lang->newpoints}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
<td valign="top" width="180">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->newpoints_menu}</strong></td>
</tr>
{$options}
</table>
</td>
<td valign="top">
<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
<tr>
<td class="thead"><strong>{$lang->newpoints}</strong></td>
</tr>
<tr>
<td class="trow1">{$lang->newpoints_home_desc}</td>
</tr>
</table>
</td>
</tr>
</table>
{$footer}
</body>
</html>',
        'home_income_row' => '<tr><td valign="middle" align="left"><span style="border-bottom: 1px dashed; cursor: help;" title="{$setting[\'description\']}">{$setting[\'title\']}</span></td><td valign="middle" align="right">{$value}</td></tr>',
        'home_income_table' => '<br /><table align="center"><tr><td align="left"><strong>Source</strong></td><td align="right"><strong>Amount Paid</strong></td></tr>{$income_settings}</table>',
        '' => '',
    ];

    // Get plugin templates
    $template_list = run_hooks('rebuild_templates', $template_list);

    $group = [
        'prefix' => $db->escape_string('newpoints'),
        'title' => $db->escape_string('Newpoints')
    ];

    // Update or create template group:
    $query = $db->simple_select('templategroups', 'prefix', "prefix='{$group['prefix']}'");

    if ($db->fetch_array($query)) {
        $db->update_query('templategroups', $group, "prefix='{$group['prefix']}'");
    } else {
        $db->insert_query('templategroups', $group);
    }

    // Query already existing templates.
    $query = $db->simple_select(
        'templates',
        'tid,title,template',
        "sid=-2 AND (title='{$group['prefix']}' OR title LIKE '{$group['prefix']}=_%' ESCAPE '=')"
    );

    $templates = [];
    $duplicates = [];

    while ($row = $db->fetch_array($query)) {
        $title = $row['title'];

        if (isset($templates[$title])) {
            // PluginLibrary had a bug that caused duplicated templates.
            $duplicates[] = $row['tid'];
            $templates[$title]['template'] = false; // force update later
        } else {
            $templates[$title] = $row;
        }
    }

    // Delete duplicated master templates, if they exist.
    if ($duplicates) {
        $db->delete_query('templates', 'tid IN (' . implode(',', $duplicates) . ')');
    }

    // Update or create templates.
    foreach ($template_list as $name => $code) {
        if (strlen($name)) {
            $name = "newpoints_{$name}";
        } else {
            $name = 'newpoints';
        }

        $template = [
            'title' => $db->escape_string($name),
            'template' => $db->escape_string($code),
            'version' => 1,
            'sid' => -2,
            'dateline' => (int)constant('TIME_NOW')
        ];

        // Update
        if (isset($templates[$name])) {
            if ($templates[$name]['template'] !== $code) {
                // Update version for custom templates if present
                $db->update_query('templates', ['version' => 0], "title='{$template['title']}'");

                // Update master template
                $db->update_query('templates', $template, "tid={$templates[$name]['tid']}");
            }
        } // Create
        else {
            $db->insert_query('templates', $template);
        }

        // Remove this template from the earlier queried list.
        unset($templates[$name]);
    }

    // Remove no longer used templates.
    foreach ($templates as $name => $row) {
        $name = $db->escape_string($name);
        $db->delete_query('templates', "title='{$name}'");
    }

    return true;
}

/**
 * Deletes settings from the database
 *
 * @param string a list of settings seperated by ',' e.g. 'test','test_again','testing'
 * @return bool false if something went wrong
 *
 */
function settings_remove(string $settings): bool
{
    global $db;

    if (!$settings) {
        return false;
    }

    $db->delete_query('newpoints_settings', 'name IN (' . $settings . ')');
    //$db->delete_query('settings', "name IN (".$settings.")");

    return true;
}

/**
 * Adds a new set of settings
 *
 * @param string the name (unique identifier) of the setting plugin
 * @param array the array containing the settings
 * @return bool false on failure, true on success
 *
 */
function settings_add_group(string $plugin, array $settings): bool
{
    global $db;

    $plugin_escaped = $db->escape_string($plugin);

    $db->update_query('newpoints_settings', ['description' => 'NEWPOINTSDELETESETTING'], "plugin='{}'");

    $disporder = 0;

    // Create and/or update settings.
    foreach ($settings as $key => $setting) {
        $setting = array_intersect_key(
            $setting,
            [
                'title' => 0,
                'description' => 0,
                'type' => 0,
                'value' => 0
            ]
        );

        $setting = array_map([$db, 'escape_string'], $setting);

        $setting = array_merge(
            [
                'title' => '',
                'description' => '',
                'type' => 'yesno',
                'value' => 0,
                'disporder' => ++$disporder
            ],
            $setting
        );

        $setting['plugin'] = $plugin_escaped;
        $setting['name'] = $db->escape_string($plugin . '_' . $key);

        $query = $db->simple_select(
            'newpoints_settings',
            'sid',
            "plugin='{$setting['plugin']}' AND name='{$setting['name']}'"
        );

        if ($sid = $db->fetch_field($query, 'sid')) {
            unset($setting['value']);
            $db->update_query('newpoints_settings', $setting, "sid='{$sid}'");
        } else {
            $db->insert_query('newpoints_settings', $setting);
        }
    }

    $db->delete_query('newpoints_settings', "plugin='{$plugin_escaped}' AND description='NEWPOINTSDELETESETTING'");

    newpoints_rebuild_settings_cache();

    return true;
}

/**
 * Adds a new setting
 *
 * @param string the name (unique identifier) of the setting
 * @param string the codename of plugin which owns the setting ('main' for main setting)
 * @param string the title of the setting
 * @param string the description of the setting
 * @param string the type of the setting ('text', 'textarea', etc...)
 * @param string the value of the setting
 * @param int the display order of the setting
 * @return bool false on failure, true on success
 *
 */
function settings_add(
    string $name,
    string $plugin,
    string $title,
    string $description,
    string $type,
    string $value = '',
    int $disporder = 0
): bool {
    global $db;

    if ($name == '' || $plugin == '' || $title == '' || $description == '' || $type == '') {
        return false;
    }

    $setting = [
        'name' => $db->escape_string($name),
        'plugin' => $db->escape_string($plugin),
        'title' => $db->escape_string($title),
        'description' => $db->escape_string($description),
        'type' => $db->escape_string($type),
        'value' => $db->escape_string($value),
        'disporder' => intval($disporder)
    ];

    // Update if setting already exists, insert otherwise.
    $query = $db->simple_select(
        'newpoints_settings',
        'sid',
        "name='{$setting['name']}' AND plugin='{$setting['plugin']}'"
    );

    if ($sid = $db->fetch_field($query, 'sid')) {
        unset($setting['value']);
        $db->update_query('newpoints_settings', $setting, "sid='{$sid}'");
    } else {
        $db->insert_query('newpoints_settings', $setting);
    }

    return true;
}

function settings_load(): bool
{
    global $mybb, $db, $cache;

    $settings = $cache->read('newpoints_settings');
    if ($settings !== false && !empty($settings)) {
        foreach ($settings as $name => $value) {
            $mybb->settings[$name] = $value;
        }
    }

    /* something is wrong so let's rebuild the cache data */
    if (empty($settings) || $settings === false) {
        $settings = [];
        newpoints_rebuild_settings_cache($settings);
    }

    return true;
}

/**
 * Rebuild the settings cache.
 *
 * @param array An array which will contain the settings once the function is run.
 */
function settings_rebuild_cache(array &$settings = []): array
{
    global $db, $cache, $mybb;

    $settings = [];

    $options = [
        'order_by' => 'title',
        'order_dir' => 'ASC'
    ];

    $query = $db->simple_select('newpoints_settings', 'value, name', '', $options);
    while ($setting = $db->fetch_array($query)) {
        //$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
        $settings[$setting['name']] = $setting['value'];
        $mybb->settings[$setting['name']] = $setting['value'];
    }
    $db->free_result($query);

    $cache->update('newpoints_settings', $settings);

    return $settings;
}

/**
 * Adds/Subtracts points to a user
 *
 * @param int the id of the user
 * @param float the number of points to add or subtract (if a negative value)
 * @param int the forum income rate
 * @param int the user group income rate
 * @param bool if the uid is a string in case we don't have the uid we can update the points field by searching for the user name
 * @param bool true if you want to run the query immediatly. Default is false which means the query will be run on shut down. Note that if the previous paremeter is set to true, the query is run immediatly
 * Note: some pages (by other plugins) do not run queries on shutdown so adding this to shutdown may not be good if you're not sure if it will run.
 *
 */
function points_add(
    int $uid,
    float $points,
    int $forumrate = 1,
    int $grouprate = 1,
    bool $isstring = false,
    bool $immediate = false
): bool {
    global $db, $mybb, $userpoints;

    if ($points == 0 || ($uid <= 0 && !$isstring)) {
        return false;
    }

    if ($isstring === true) {
        $immediate = true;
    }

    // might work only for MySQL and MySQLi
    //$db->update_query("users", array('newpoints' => 'newpoints+('.floatval($points).')'), 'uid=\''.intval($uid).'\'', '', true);

    if ($isstring) // where username
    {
        $db->write_query(
            'UPDATE ' . $db->table_prefix . "users SET newpoints=newpoints+'" . floatval(
                round($points * $forumrate * $grouprate, intval($mybb->settings['newpoints_main_decimal']))
            ) . "' WHERE username='" . $db->escape_string($uid) . "'"
        );
    } else // where uid
    {
        // if immediate, run the query now otherwise add it to shutdown to avoid slow down
        if ($immediate) {
            $db->write_query(
                'UPDATE ' . $db->table_prefix . "users SET newpoints=newpoints+'" . floatval(
                    round($points * $forumrate * $grouprate, intval($mybb->settings['newpoints_main_decimal']))
                ) . "' WHERE uid='" . intval($uid) . "'"
            );
        } else {
            isset($userpoints) || $userpoints = [];

            $userpoints[intval($uid)] += floatval(
                round($points * $forumrate * $grouprate, intval($mybb->settings['newpoints_main_decimal']))
            );
        }
    }

    static $newpoints_shutdown;
    if (!isset($newpoints_shutdown)) {
        $newpoints_shutdown = true;
        add_shutdown('newpoints_update_addpoints');
    }

    return true;
}

function points_update(): bool
{
    global $cache, $userpoints, $db;
    if (!empty($userpoints)) {
        foreach ($userpoints as $uid => $amount) {
            if ($amount < 0) {
                $db->write_query(
                    'UPDATE `' . $db->table_prefix . 'users` SET `newpoints` = `newpoints`-(' . abs(
                        (float)$amount
                    ) . ') WHERE `uid`=\'' . $uid . '\''
                );
            } else {
                $db->write_query(
                    'UPDATE `' . $db->table_prefix . 'users` SET `newpoints` = `newpoints`+(' . (float)$amount . ') WHERE `uid`=\'' . $uid . '\''
                );
            }
        }
        unset($userpoints);
    }

    return true;
}

/**
 * Formats points according to the settings
 *
 * @param float the amount of points
 * @return string formated points
 *
 */
function points_format(float $points): string
{
    global $mybb;

    return $mybb->settings['newpoints_main_curprefix'] . my_number_format(
            round((float)$points, intval($mybb->settings['newpoints_main_decimal']))
        ) . $mybb->settings['newpoints_main_cursuffix'];
}

/**
 * Get rules of a certain group or forum
 *
 * @param string the type of rule: 'forum' or 'group'
 * @param int the id of the group or forum
 * @return bool false if something went wrong
 *
 */
function rules_get(string $type, int $id): array
{
    global $db, $cache;

    if (!$type || !$id) {
        return false;
    }

    if ($type == 'forum') {
        $typeid = 'f';
    } elseif ($type == 'group') {
        $typeid = 'g';
    } else {
        return [];
    }

    $rule = [];

    $cachedrules = $cache->read('newpoints_rules');
    if ($cachedrules === false) {
        // Something's wrong so let's get rule from DB
        // To fix this issue, the administrator should edit a rule and save it (all rules are re-cached when one is added/edited)
        $query = $db->simple_select('newpoints_' . $type . 'rules', '*', $typeid . 'id=\'' . intval($id) . '\'');
        $rule = $db->fetch_array($query);
    } else {
        if (!empty($cachedrules)) {
            // If the array is not empty then grab from cache
            $rule = $cachedrules[$type][$id];
        }
    }

    return $rule;
}

/**
 * Get all rules
 *
 * @param string the type of rule: 'forum' or 'group'
 * @return array containing all rules
 *
 */
function rules_get_all(string $type): array
{
    global $db, $cache;

    if (!$type) {
        return false;
    }

    if ($type == 'forum') {
        $typeid = 'f';
    } elseif ($type == 'group') {
        $typeid = 'g';
    } else {
        return [];
    }

    $rules = [];

    $cachedrules = $cache->read('newpoints_rules');
    if ($cachedrules === false) {
        // Something's wrong so let's get the rules from DB
        // To fix this issue, the administrator should edit a rule and save it (all rules are re-cached when one is added/edited)
        $query = $db->simple_select('newpoints_' . $type . 'rules', '*');
        while ($rule = $db->fetch_array($query)) {
            $rules[$rule[$typeid . 'id']] = $rule;
        }
    } else {
        if (!empty($cachedrules[$type])) {
            // Not empty? Then grab the chosen rules
            foreach ($cachedrules[$type] as $crule) {
                $rules[$crule[$typeid . 'id']] = $crule;
            }
        }
    }

    return $rules;
}

/**
 * Rebuild the rules cache.
 *
 * @param array An array which will contain the rules once the function is run.
 */
function rules_rebuild_cache(array &$rules = []): bool
{
    global $db, $cache, $mybb;

    $rules = [];

    // Query forum rules
    $query = $db->simple_select('newpoints_forumrules');
    while ($rule = $db->fetch_array($query)) {
        $rules['forum'][$rule['fid']] = $rule;
    }
    $db->free_result($query);

    // Query group rules
    $query = $db->simple_select('newpoints_grouprules');
    while ($rule = $db->fetch_array($query)) {
        $rules['group'][$rule['gid']] = $rule;
    }
    $db->free_result($query);

    $cache->update('newpoints_rules', $rules);

    return true;
}

/**
 * Sends a PM to a user
 *
 * It's a wrapper for MyBB's function because in the past NewPoints provided a functio while MyBB did not.
 */
function private_message_send(array $pm, int $fromid = 0): bool
{
    return send_pm($pm, $fromid);
}

/**
 * Get the user group data of the gid
 *
 * @param int the usergroup ID
 * @return array the user data
 *
 */
function get_group(int $gid): array
{
    global $db;

    if (!$gid) {
        return [];
    }

    $query = $db->simple_select('usergroups', '*', 'gid=\'' . intval($gid) . '\'');

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    }

    return [];
}

/**
 * Find and replace a string in a particular template in global templates set
 *
 * @param string The name of the template
 * @param string The regular expression to match in the template
 * @param string The replacement string
 * @return bolean true if matched template name, false if not.
 */

function find_replace_template_sets(string $title, string $find, string $replace): bool
{
    global $db;

    $query = $db->write_query(
        '
		SELECT template, tid FROM ' . $db->table_prefix . "templates WHERE title='$title' AND sid=-1
	"
    );
    while ($template = $db->fetch_array($query)) {
        if ($template['template']) // Custom template exists for this group
        {
            if (!preg_match($find, $template['template'])) {
                return false;
            }
            $newtemplate = preg_replace($find, $replace, $template['template']);
            $template['template'] = $newtemplate;
            $update[] = $template;
        }
    }

    if (is_array($update)) {
        foreach ($update as $template) {
            $updatetemp = [
                'template' => $db->escape_string($template['template']),
                'dateline' => (int)constant('TIME_NOW')
            ];
            $db->update_query('templates', $updatetemp, "tid='" . $template['tid'] . "'");
        }
    }
    return true;
}

/**
 * Create a new log entry
 *
 * @param string action taken
 * @param string extra data
 * @param username of who's executed the action
 * @param uid of who's executed the action
 * @return bool false if something went wrong
 *
 */
function log_add(string $action, string $data = '', string $username = '', int $uid = 0): bool
{
    global $db, $mybb;

    if (!$action) {
        return false;
    }

    if ($username == '' || $uid == 0) {
        $username = $mybb->user['username'];
        $uid = $mybb->user['uid'];
    }

    $db->insert_query(
        'newpoints_log',
        [
            'action' => $db->escape_string($action),
            'data' => $db->escape_string($data),
            'date' => (int)constant('TIME_NOW'),
            'uid' => intval($uid),
            'username' => $db->escape_string($username)
        ]
    );

    return true;
}

/**
 * Removes all log entries by action
 *
 * @param array action taken
 *
 */
function log_remove(array $action): bool
{
    global $db, $mybb;

    if (empty($action) || !is_array($action)) {
        return false;
    }

    foreach ($action as $act) {
        $db->delete_query('newpoints_log', 'action=\'' . $act . '\'');
    }

    return true;
}

/**
 * Checks if a user has permissions or not.
 *
 * @param array|string Allowed usergroups (if set to 'all', every user has access; if set to '' no one has)
 *
 */
function check_permissions(string $groups_comma): bool
{
    global $mybb;

    if ($groups_comma == 'all') {
        return true;
    }

    if ($groups_comma == '') {
        return false;
    }

    $groups = explode(',', $groups_comma);

    $ourgroups = explode(',', $mybb->user['additionalgroups']);
    $ourgroups[] = $mybb->user['usergroup'];

    if (count(array_intersect($ourgroups, $groups)) == 0) {
        return false;
    } else {
        return true;
    }
}

function plugins_load(): bool
{
    global $cache, $plugins, $mybb, $theme, $db, $templates, $newpoints_plugins;

    $newpoints_plugins = '';

    // guests have 0 points
    if (isset($mybb->user) && !$mybb->user['uid']) {
        $mybb->user['newpoints'] = 0;
    }

    $pluginlist = $cache->read('newpoints_plugins');

    if (!empty($pluginlist) && is_array($pluginlist['active'])) {
        foreach ($pluginlist['active'] as $plugin) {
            if ($plugin != '' && file_exists(constant('MYBB_ROOT') . 'inc/plugins/newpoints/' . $plugin . '.php')) {
                require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/' . $plugin . '.php';
            }
        }

        $newpoints_plugins = $pluginlist;
    }

    return true;
}

// Updates users' points by user group - used by group rules
function users_update(): bool
{
    global $cache, $userupdates, $db;

    if (!empty($userupdates)) {
        foreach ($userupdates as $gid => $amount) {
            $db->write_query(
                'UPDATE `' . $db->table_prefix . 'users` SET `newpoints` = `newpoints`+' . $amount . ' WHERE `usergroup`=' . $gid
            );
        }
        unset($userupdates);
    }

    return true;
}

/**
 * Get the user data of a user name
 *
 * @param string the user name
 * @param string the fields to fetch
 * @return array the user data
 *
 */
function users_get_by_username(string $username, string $fields = '*'): array
{
    global $db;

    if (!$username) {
        return [];
    }

    $query = $db->simple_select('users', $fields, 'username=\'' . $db->escape_string(trim($username)) . '\'');

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    }

    return [];
}