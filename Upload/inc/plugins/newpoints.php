<?php
/***************************************************************************
 *
 *   NewPoints plugin (/inc/plugins/newpoints.php)
 *     Author: Pirata Nervo
 *   Copyright: © 2009-2012 Pirata Nervo
 *
 *   Website: http://www.mybb-plugins.com
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

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

// Load NewPoints' settings whenever NewPoints plugin is executed
// Adds one additional query per page
// TODO: Perhaps use Plugin Library to modify the init.php file to load settings from both tables (MyBB's and NewPoints')
// OR: Go back to the old method and put the settings in the settings table but keep a copy in NewPoints' settings table
// but also add a page on ACP to run the check and fix any missing settings or perhaps do the check via task.
if (defined('IN_ADMINCP')) {
    global $mybb;

    // Plugins get "require_once" on Plugins List and Plugins Check and we do not want to load our settings when our file is required by those
    if ($mybb->get_input('module') != 'config-plugins' && $GLOBALS['db']->table_exists('newpoints_settings')) {
        newpoints_load_settings();
    }
} else {
    newpoints_load_settings();
}

if (THIS_SCRIPT == 'showthread.php') {
    global $templatelist;
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'newpoints_postbit,newpoints_donate_inline';
} elseif (THIS_SCRIPT == 'member.php') {
    global $templatelist;
    if (isset($templatelist)) {
        $templatelist .= ',';
    }
    $templatelist .= 'newpoints_profile,newpoints_donate_inline';
}

define('NEWPOINTS_VERSION', '2.1.2');
define('NEWPOINTS_VERSION_CODE', '212');
define('MAX_DONATIONS_CONTROL', '5'); // Maximum donations someone can send each 15 minutes

// load plugins and do other stuff
if (defined('IN_ADMINCP')) {
    define('NP_HOOKS', 1); // 1 means Admin
} else {
    define('NP_HOOKS', 2); // 2 means outside ACP
}

// load hooks
require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/hooks.php';

if (defined('IN_ADMINCP')) {
    global $db, $mybb;

    function newpoints_info()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        return newpoints_plugin_info();
    }

    function newpoints_install()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_install();
    }

    function newpoints_is_installed()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        return newpoints_plugin_is_installed();
    }

    function newpoints_uninstall()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_uninstall();
    }

    function newpoints_do_template_edits()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_do_template_edits();
    }

    function newpoints_undo_template_edits()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_undo_template_edits();
    }

    function newpoints_activate()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_activate();
    }

    function newpoints_deactivate()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_deactivate();
    }
}

function newpoints_count_characters($message)
{
    // Attempt to remove any quotes
    $message = preg_replace(array(
        '#\[quote=([\"\']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"\']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si',
        '#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si',
        '#\[quote\]#si',
        '#\[\/quote\]#si'
    ), '', $message);

    // Attempt to remove any MyCode
    global $parser;
    if (!is_object($parser)) {
        require_once constant('MYBB_ROOT') . 'inc/class_parser.php';
        $parser = new postParser();
    }

    $message = $parser->parse_message($message, array(
        'allow_html' => 0,
        'allow_mycode' => 1,
        'allow_smilies' => 0,
        'allow_imgcode' => 1,
        'filter_badwords' => 1,
        'nl2br' => 0
    ));

    // before stripping tags, try converting some into spaces
    $message = preg_replace(array(
        '~\<(?:img|hr).*?/\>~si',
        '~\<li\>(.*?)\</li\>~si'
    ), array(' ', "\n* $1"), $message);

    $message = unhtmlentities(strip_tags($message));

    // Remove all spaces?
    $message = trim_blank_chrs($message);
    $message = preg_replace('/\s+/', '', $message);

    // convert \xA0 to spaces (reverse &nbsp;)
    $message = trim(
        preg_replace(array('~ {2,}~', "~\n{2,}~"),
            array(' ', "\n"),
            strtr($message, array("\xA0" => utf8_encode("\xA0"), "\r" => '', "\t" => ' ')))
    );

    // newline fix for browsers which don't support them
    $message = preg_replace("~ ?\n ?~", " \n", $message);

    return (int)my_strlen($message);
}

/**************************************************************************************/
/****************** FUNCTIONS THAT CAN/SHOULD BE USED BY PLUGINS **********************/
/**************************************************************************************/

/**
 * Somewhat like htmlspecialchars_uni but for JavaScript strings
 *
 * @param string: The string to be parsed
 * @return string: Javascript compatible string
 */
function newpoints_jsspecialchars($str)
{
    // Converts & -> &amp; allowing Unicode
    // Parses out HTML comments as the XHTML validator doesn't seem to like them
    $string = preg_replace(array('#\<\!--.*?--\>#', '#&(?!\#[0-9]+;)#'), array('', '&amp;'), $str);
    return strtr(
        $string,
        array("\n" => '\n', "\r" => '\r', '\\' => '\\\\', '"' => '\x22', "'" => '\x27', '<' => '&lt;', '>' => '&gt;')
    );
}

/**
 * Deletes templates from the database
 *
 * @param string a list of templates seperated by ',' e.g. 'test','test_again','testing'
 * @return bool false if something went wrong
 *
 */
function newpoints_remove_templates($templates)
{
    global $db;

    if (!$templates) {
        return false;
    }

    return $db->delete_query('templates', 'title IN (' . $templates . ')');
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
function newpoints_add_template($name, $contents, $sid = -1)
{
    global $db;

    if (!$name || !$contents) {
        return false;
    }

    $templatearray = array(
        'title' => $db->escape_string($name),
        'template' => $db->escape_string($contents),
        'sid' => intval($sid)
    );

    $query = $db->simple_select(
        'templates',
        'tid,title,template',
        "sid='{$sid}' AND title='{$templatearray['title']}'"
    );

    $templates = array();
    $duplicates = array();

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

    return $db->insert_query('templates', $templatearray);
}

/**
 * Adds a new set of templates
 *
 * @param string the key of the template plugin
 * @param array the array containing the templates data
 * @return bool false if something went wrong
 *
 */
function newpoints_rebuild_templates()
{
    global $db, $plugins;

    $prefix = 'newpoints';

    // Default templates
    $template_list = array(
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
    );

    // Get plugin templates
    $template_list = $plugins->run_hooks('newpoints_rebuild_templates', $template_list);

    $group = array(
        'prefix' => $db->escape_string('newpoints'),
        'title' => $db->escape_string('Newpoints')
    );

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

    $templates = array();
    $duplicates = array();

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

        $template = array(
            'title' => $db->escape_string($name),
            'template' => $db->escape_string($code),
            'version' => 1,
            'sid' => -2,
            'dateline' => (int)constant('TIME_NOW')
        );

        // Update
        if (isset($templates[$name])) {
            if ($templates[$name]['template'] !== $code) {
                // Update version for custom templates if present
                $db->update_query('templates', array('version' => 0), "title='{$template['title']}'");

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
}

/**
 * Deletes settings from the database
 *
 * @param string a list of settings seperated by ',' e.g. 'test','test_again','testing'
 * @return bool false if something went wrong
 *
 */
function newpoints_remove_settings($settings)
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
function newpoints_add_setting($name, $plugin, $title, $description, $type, $value = '', $disporder = 0)
{
    global $db;

    if ($name == '' || $plugin == '' || $title == '' || $description == '' || $type == '') {
        return false;
    }

    $setting = array(
        'name' => $db->escape_string($name),
        'plugin' => $db->escape_string($plugin),
        'title' => $db->escape_string($title),
        'description' => $db->escape_string($description),
        'type' => $db->escape_string($type),
        'value' => $db->escape_string($value),
        'disporder' => intval($disporder)
    );

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

/**
 * Adds a new set of settings
 *
 * @param string the name (unique identifier) of the setting plugin
 * @param array the array containing the settings
 * @return bool false on failure, true on success
 *
 */
function newpoints_add_settings($plugin, $settings)
{
    global $db;

    $plugin_escaped = $db->escape_string($plugin);

    $db->update_query('newpoints_settings', array('description' => 'NEWPOINTSDELETESETTING'), "plugin='{}'");

    $disporder = 0;

    // Create and/or update settings.
    foreach ($settings as $key => $setting) {
        $setting = array_intersect_key(
            $setting,
            array(
                'title' => 0,
                'description' => 0,
                'type' => 0,
                'value' => 0
            )
        );

        $setting = array_map(array($db, 'escape_string'), $setting);

        $setting = array_merge(
            array(
                'title' => '',
                'description' => '',
                'type' => 'yesno',
                'value' => 0,
                'disporder' => ++$disporder
            ),
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
function newpoints_addpoints($uid, $points, $forumrate = 1, $grouprate = 1, $isstring = false, $immediate = false)
{
    global $db, $mybb, $userpoints;

    if ($points == 0 || ($uid <= 0 && !$isstring)) {
        return;
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
}

function newpoints_update_addpoints()
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
}

/**
 * Get rules of a certain group or forum
 *
 * @param string the type of rule: 'forum' or 'group'
 * @param int the id of the group or forum
 * @return bool false if something went wrong
 *
 */
function newpoints_getrules($type, $id)
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
        return;
    }

    $rule = array();

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
function newpoints_getallrules($type)
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
        return;
    }

    $rules = array();

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
function newpoints_rebuild_rules_cache(&$rules = array())
{
    global $db, $cache, $mybb;

    $rules = array();

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
}

/**
 * Formats points according to the settings
 *
 * @param float the amount of points
 * @return string formated points
 *
 */
function newpoints_format_points($points)
{
    global $mybb;

    return $mybb->settings['newpoints_main_curprefix'] . my_number_format(
            round((float)$points, intval($mybb->settings['newpoints_main_decimal']))
        ) . $mybb->settings['newpoints_main_cursuffix'];
}

/**
 * Sends a PM to a user
 *
 * It's a wrapper for MyBB's function because in the past NewPoints provided a functio while MyBB did not.
 */
function newpoints_send_pm($pm, $fromid = 0)
{
    return send_pm($pm, $fromid);
}

/**
 * Get the user data of a user name
 *
 * @param string the user name
 * @param string the fields to fetch
 * @return array the user data
 *
 */
function newpoints_getuser_byname($username, $fields = '*')
{
    global $db;

    if (!$username) {
        return;
    }

    $query = $db->simple_select('users', $fields, 'username=\'' . $db->escape_string(trim($username)) . '\'');
    return $db->fetch_array($query);
}

/**
 * Get the user group data of the gid
 *
 * @param int the usergroup ID
 * @return array the user data
 *
 */
function newpoints_get_usergroup($gid)
{
    global $db;

    if (!$gid) {
        return;
    }

    $query = $db->simple_select('usergroups', '*', 'gid=\'' . intval($gid) . '\'');
    return $db->fetch_array($query);
}


/**
 * Find and replace a string in a particular template in global templates set
 *
 * @param string The name of the template
 * @param string The regular expression to match in the template
 * @param string The replacement string
 * @return bolean true if matched template name, false if not.
 */

function newpoints_find_replace_templatesets($title, $find, $replace)
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
            $updatetemp = array(
                'template' => $db->escape_string($template['template']),
                'dateline' => (int)constant('TIME_NOW')
            );
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
function newpoints_log($action, $data = '', $username = '', $uid = 0)
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
        array(
            'action' => $db->escape_string($action),
            'data' => $db->escape_string($data),
            'date' => (int)constant('TIME_NOW'),
            'uid' => intval($uid),
            'username' => $db->escape_string($username)
        )
    );

    return true;
}

/**
 * Removes all log entries by action
 *
 * @param array action taken
 *
 */
function newpoints_remove_log($action)
{
    global $db, $mybb;

    if (empty($action) || !is_array($action)) {
        return false;
    }

    foreach ($action as $act) {
        $db->delete_query('newpoints_log', 'action=\'' . $act . '\'');
    }
}

/**
 * Checks if a user has permissions or not.
 *
 * @param array|string Allowed usergroups (if set to 'all', every user has access; if set to '' no one has)
 *
 */
function newpoints_check_permissions($groups_comma)
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

function newpoints_load_plugins()
{
    global $cache, $plugins, $mybb, $theme, $db, $templates, $newpoints_plugins;

    $newpoints_plugins = '';

    // guests have 0 points
    if (!$mybb->user['uid']) {
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
}

function newpoints_load_settings()
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
        $settings = array();
        newpoints_rebuild_settings_cache($settings);
    }
}

/**
 * Rebuild the settings cache.
 *
 * @param array An array which will contain the settings once the function is run.
 */
function newpoints_rebuild_settings_cache(&$settings = array())
{
    global $db, $cache, $mybb;

    $settings = array();

    $options = array(
        'order_by' => 'title',
        'order_dir' => 'ASC'
    );

    $query = $db->simple_select('newpoints_settings', 'value, name', '', $options);
    while ($setting = $db->fetch_array($query)) {
        //$setting['value'] = str_replace("\"", "\\\"", $setting['value']);
        $settings[$setting['name']] = $setting['value'];
        $mybb->settings[$setting['name']] = $setting['value'];
    }
    $db->free_result($query);

    $cache->update('newpoints_settings', $settings);
}

function newpoints_lang_load($plugin)
{
    global $lang;
    if ($plugin == '') {
        return;
    }

    $lang->set_path(constant('MYBB_ROOT') . 'inc/plugins/newpoints/languages');
    $lang->load($plugin);
    $lang->set_path(constant('MYBB_ROOT') . 'inc/languages');
}

// Updates users' points by user group - used by group rules
function newpoints_update_users()
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
}