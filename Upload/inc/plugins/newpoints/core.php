<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/core.php)
 *    Author: Pirata Nervo
 *    Copyright: © 2009 Pirata Nervo
 *    Copyright: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
 *
 ***************************************************************************
 ****************************************************************************
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

use DirectoryIterator;
use pluginSystem;

use function send_pm;

use const Newpoints\ROOT;

const URL = 'newpoints.php';

function language_load(string $plugin = ''): bool
{
    global $lang;

    if ($plugin === '') {
        isset($lang->newpoints) || $lang->load('newpoints');
    } elseif (!isset($lang->{"newpoints_{$plugin}"})) {
        $lang->set_path(MYBB_ROOT . 'inc/plugins/newpoints/languages');

        $lang->load("newpoints_{$plugin}");

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

function url_handler(string $newUrl = ''): string
{
    static $setUrl = URL;

    if (($newUrl = trim($newUrl))) {
        $setUrl = $newUrl;
    }

    return $setUrl;
}

function url_handler_set(string $newUrl): string
{
    return url_handler($newUrl);
}

function url_handler_get(): string
{
    return url_handler();
}

function url_handler_build(array $urlAppend = [], bool $fetchImportUrl = false, bool $encode = true): string
{
    global $PL;

    if (!is_object($PL)) {
        $PL or require_once PLUGINLIBRARY;
    }

    if ($fetchImportUrl === false) {
        if ($urlAppend && !is_array($urlAppend)) {
            $urlAppend = explode('=', $urlAppend);
            $urlAppend = [$urlAppend[0] => $urlAppend[1]];
        }
    }

    return $PL->url_append(url_handler_get(), $urlAppend, '&amp;', $encode);
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
        require_once MYBB_ROOT . 'inc/class_parser.php';
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
function templates_remove(array $templates, string $newpoints_prefix = 'newpoints_'): bool
{
    if (!$templates) {
        return false;
    }

    global $db;

    if ($newpoints_prefix) {
        $templates = array_map(function ($template_name) use ($newpoints_prefix) {
            return "{$newpoints_prefix}{$template_name}";
        }, $templates);
    }

    $templates = array_map([$db, 'escape_string'], $templates);

    $templates = implode("','", $templates);

    $db->delete_query('templates', "title IN ('{$templates}')");

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

function templates_get_name(string $template_name = '', string $plugin_prefix = ''): string
{
    $template_prefix = '';

    if ($plugin_prefix && !$template_name) {
        $plugin_prefix = rtrim($plugin_prefix, '_');
    }

    if ($template_name || $plugin_prefix) {
        $template_prefix = '_';
    }

    return "newpoints{$template_prefix}{$plugin_prefix}{$template_name}";
}

function templates_get(
    string $template_name = '',
    bool $enable_html_comments = true,
    string $plugin_path = ROOT,
    string $plugin_prefix = ''
): string {
    global $templates;

    if (DEBUG) {
        $file_path = $plugin_path . "/templates/{$template_name}.html";

        $template_contents = file_get_contents($file_path);

        $templates->cache[templates_get_name($template_name, $plugin_prefix)] = $template_contents;
    } elseif (my_strpos($template_name, '/') !== false) {
        $template_name = substr($template_name, strpos($template_name, '/') + 1);
    }

    return $templates->render(templates_get_name($template_name, $plugin_prefix), true, $enable_html_comments);
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
    global $PL;

    $templates_directories = [ROOT . '/templates'];

    $templates_list = [];

    $hook_arguments = [
        'templates_directories' => &$templates_directories,
        'templates_list' => &$templates_list,
    ];

    $hook_arguments = run_hooks('templates_rebuild_start', $hook_arguments);

    foreach ($templates_directories as $plugin_code => $template_directory) {
        if (is_string($plugin_code) && !empty($plugin_code)) {
            $plugin_code = "{$plugin_code}_";
        } else {
            $plugin_code = '';
        }

        if (file_exists($template_directory)) {
            $templates_directory_iterator = new DirectoryIterator($template_directory);

            foreach ($templates_directory_iterator as $template_file) {
                if (!$template_file->isFile()) {
                    continue;
                }

                $path_name = $template_file->getPathname();

                $path_info = pathinfo($path_name);

                if ($path_info['extension'] === 'html') {
                    if (empty($path_info['filename'])) {
                        $templates_list[rtrim($plugin_code, '_')] = file_get_contents($path_name);
                    } else {
                        $templates_list[$plugin_code . $path_info['filename']] = file_get_contents($path_name);
                    }
                }
            }
        }
    }

    $hook_arguments = run_hooks('templates_rebuild_end', $hook_arguments);

    if ($templates_list) {
        $PL->templates('newpoints', 'Newpoints', $templates_list);
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
function settings_remove(array $settings, string $newpoints_prefix = 'newpoints_'): bool
{
    if (!$settings) {
        return false;
    }

    global $db;

    if ($newpoints_prefix) {
        $settings = array_map(function ($setting_name) use ($newpoints_prefix) {
            return "{$newpoints_prefix}{$setting_name}";
        }, $settings);
    }

    $settings = array_map([$db, 'escape_string'], $settings);

    $settings = implode("','", $settings);

    $db->delete_query('newpoints_settings', "name IN ('{$settings}')");

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

    $display_order = 0;

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
                'disporder' => ++$display_order
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

    settings_rebuild_cache();

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
    string $group_name,
    string $title,
    string $description,
    string $type,
    string $value = '',
    int $display_order = 0
): bool {
    global $db;

    if ($name == '' || $group_name == '' || $title == '' || $description == '' || $type == '') {
        return false;
    }

    $setting = [
        'name' => $db->escape_string($name),
        'plugin' => $db->escape_string($group_name),
        'title' => $db->escape_string($title),
        'description' => $db->escape_string($description),
        'type' => $db->escape_string($type),
        'value' => $db->escape_string($value),
        'disporder' => $display_order
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
    global $mybb, $cache;

    $settings = $cache->read('newpoints_settings');
    global $mybb;

    if (!empty($settings)) {
        foreach ($settings as $name => $value) {
            $mybb->settings[$name] = $value;
        }
    }

    /* something is wrong so let's rebuild the cache data */
    if (empty($settings)) {
        $settings = [];

        settings_rebuild_cache($settings);
    }

    return true;
}

function settings_load_init(): bool
{
    // Load NewPoints' settings whenever NewPoints plugin is executed
    // Adds one additional query per page
    // TODO: Perhaps use Plugin Library to modify the init.php file to load settings from both tables (MyBB's and NewPoints')
    // OR: Go back to the old method and put the settings in the settings table but keep a copy in NewPoints' settings table
    // but also add a page on ACP to run the check and fix any missing settings or perhaps do the check via task.
    if (defined('IN_ADMINCP')) {
        global $mybb, $db;

        // Plugins get "require_once" on Plugins List and Plugins Check and we do not want to load our settings when our file is required by those
        if ($mybb->get_input('module') === 'config-plugins' || !$db->table_exists('newpoints_settings')) {
            return false;
        }
    }

    return settings_load();
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
        //$setting['value']=str_replace("\"", "\\\"", $setting['value']);
        $settings[$setting['name']] = $setting['value'];
        $mybb->settings[$setting['name']] = $setting['value'];
    }
    $db->free_result($query);

    $cache->update('newpoints_settings', $settings);

    return $settings;
}

/**
 * Adds a new set of templates
 *
 * @param string the key of the template plugin
 * @param array the array containing the templates data
 * @return bool false if something went wrong
 *
 */
function settings_rebuild(): bool
{
    global $lang;
    global $PL;

    language_load();

    $settings_directories = [
        ROOT . '/settings'
    ];

    $settings_list = [];

    $hook_arguments = [
        'settings_directories' => &$settings_directories,
        'settings_list' => &$settings_list,
    ];

    $hook_arguments = run_hooks('settings_rebuild_start', $hook_arguments);

    foreach ($settings_directories as $setting_directory) {
        if (file_exists($setting_directory)) {
            $settings_directory_iterator = new DirectoryIterator($setting_directory);

            foreach ($settings_directory_iterator as $settings_file) {
                if (!$settings_file->isFile()) {
                    continue;
                }

                $path_name = $settings_file->getPathname();

                $path_info = pathinfo($path_name);

                if ($path_info['extension'] !== 'json') {
                    continue;
                }

                $setting_group = $path_info['filename'];

                $settings_contents = file_get_contents($path_name);

                $settings_data = json_decode($settings_contents, true);

                if (empty($settings_data) || !is_array($settings_data) || count($settings_data) < 1) {
                    continue;
                }

                foreach ($settings_data as $setting_key => &$setting_data) {
                    if (empty($lang->{"setting_newpoints_{$setting_group}_{$setting_key}"})) {
                        continue;
                    }

                    if (in_array($setting_data['type'], ['select', 'checkbox'])) {
                        foreach ($setting_data['options'] as $option_key) {
                            $option_value = $option_key;

                            if (isset($lang->{"setting_newpoints_{$setting_group}_{$setting_key}_{$option_key}"})) {
                                $option_value = $lang->{"setting_newpoints_{$setting_group}_{$setting_key}_{$option_key}"};
                            }

                            $setting_data['type'] .= "\n{$option_key}={$option_value}";
                        }
                    }

                    $setting_data['title'] = $lang->{"setting_newpoints_{$setting_group}_{$setting_key}"};

                    $setting_data['description'] = $lang->{"setting_newpoints_{$setting_group}_{$setting_key}_desc"};
                }

                $settings_list[$setting_group] = $settings_data;
            }
        }
    }

    $hook_arguments = run_hooks('settings_rebuild_end', $hook_arguments);

    if ($settings_list) {
        foreach ($settings_list as $group_name => $settings_data) {
            if (empty($lang->{"setting_group_newpoints_{$setting_group}"})) {
                $lang->{"setting_group_newpoints_{$setting_group}"} = $group_name;
            }

            if (empty($lang->{"setting_group_newpoints_{$setting_group}_desc"})) {
                $lang->{"setting_group_newpoints_{$setting_group}_desc"} = '';
            }

            settings(
                $group_name,
                $lang->{"setting_group_newpoints_{$setting_group}"},
                $lang->{"setting_group_newpoints_{$setting_group}_desc"},
                $settings_data
            );
        }
    }

    settings_rebuild_cache();

    return true;
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
    float $forumrate = 1,
    float $grouprate = 1,
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
    //$db->update_query("users", array('newpoints' =>'newpoints+('.floatval($points).')'), 'uid=\''.intval($uid).'\'', '', true);

    $points_rounded = round($points * $forumrate * $grouprate, intval($mybb->settings['newpoints_main_decimal']));

    if ($isstring) // where username
    {
        $db->write_query(
            'UPDATE ' . $db->table_prefix . "users SET newpoints=newpoints+'" . $points_rounded . "' WHERE username='" . $db->escape_string(
                $uid
            ) . "'"
        );
        // where uid
        // if immediate, run the query now otherwise add it to shutdown to avoid slow down
    } elseif ($immediate) {
        $db->write_query(
            'UPDATE ' . $db->table_prefix . "users SET newpoints=newpoints+'" . $points_rounded . "' WHERE uid='" . $uid . "'"
        );
    } else {
        isset($userpoints) || $userpoints = [];

        isset($userpoints[$uid]) || $userpoints[$uid] = 0;

        $userpoints[$uid] += $points_rounded;
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
                    'UPDATE `' . $db->table_prefix . 'users` SET `newpoints`=`newpoints`-(' . abs(
                        (float)$amount
                    ) . ') WHERE `uid`=\'' . $uid . '\''
                );
            } else {
                $db->write_query(
                    'UPDATE `' . $db->table_prefix . 'users` SET `newpoints`=`newpoints`+(' . (float)$amount . ') WHERE `uid`=\'' . $uid . '\''
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

function rules_get_group_rate(string $rule_key, array $user = []): float
{
    $group_rate = 0;

    if (empty($user)) {
        global $mybb;

        $user = $mybb->user;
    }

    $rate_values = [];

    foreach (explode(',', "{$mybb->user['usergroup']},{$mybb->user['usergroup']}") as $group_id) {
        $groupsrules = newpoints_getrules('group', (int)$group_id);

        if (!empty($groupsrules[$rule_key])) {
            $rate_values[] = (float)$groupsrules[$rule_key];
        }
    }

    if (empty($rate_values)) {
        return 1;
    }

    $distance = INF;

    $closest_to_one = false;

    foreach ($rate_values as $rate_value) {
        $difference = abs(1 - $rate_value);

        if ($difference < $distance) {
            $distance = $difference;

            $closest_to_one = $rate_value;
        }
    }

    return $closest_to_one;
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
                'dateline' => TIME_NOW
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
            'date' => TIME_NOW,
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

function load_set_guest_data(): bool
{
    global $mybb;

    if (empty($mybb->user) || empty($mybb->user['uid'])) {
        $mybb->user['newpoints'] = 0;
    }

    return true;
}

function plugins_load(): bool
{
    global $cache, $newpoints_plugins;

    isset($newpoints_plugins) || $newpoints_plugins = '';

    $plugin_list = $cache->read('newpoints_plugins');

    static $newpoints_plugins_loaded = [];

    if (!empty($plugin_list) && is_array($plugin_list['active'])) {
        foreach ($plugin_list['active'] as $plugin) {
            if (isset($newpoints_plugins_loaded[$plugin])) {
                continue;
            }

            $newpoints_plugins_loaded[$plugin] = true;

            $plugin_file_path = MYBB_ROOT . "inc/plugins/newpoints/plugins/{$plugin}.php";

            if (!empty($plugin) && file_exists($plugin_file_path)) {
                require_once $plugin_file_path;
            }
        }

        $newpoints_plugins = $plugin_list;
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
                'UPDATE `' . $db->table_prefix . 'users` SET `newpoints`=`newpoints`+' . $amount . ' WHERE `usergroup`=' . $gid
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
    $user_data = get_user_by_username($username, ['fields' => explode(',', $fields)]);

    if (empty($user_data)) {
        return [];
    }

    return $user_data;
}

/* --- Setting groups and settings: --- */

/**
 * Create and/or update setting group and settings. Taken from PluginLibrary
 *
 * @param string $name Internal unique group name and setting prefix.
 * @param string $title Group title that will be shown to the admin.
 * @param string $description Group description that will show up in the group overview.
 * @param array $list The list of settings to be added to that group.
 */
function settings(string $group_name, string $title, string $description, array $list)
{
    global $db;

    /* Setting group: */

    /* Settings: */

    // Deprecate all the old entries.
    $db->update_query(
        'newpoints_settings',
        ['description' => 'NEWPOINTSDELETEMARKER'],
        "plugin='{$group_name}'"
    );

    // Create and/or update settings.
    foreach ($list as $key => $setting) {
        // Prefix all keys with group name.
        $key = "newpoints_{$group_name}_{$key}";

        // Filter valid entries.
        $setting = array_intersect_key(
            $setting,
            [
                'title' => 0,
                'description' => 0,
                'type' => 0,
                'value' => 0,
            ]
        );

        // Escape input values.
        $setting = array_map([$db, 'escape_string'], (array)$setting);

        isset($display_order) || $display_order = 0;

        $setting = array_merge(
            [
                'description' => '',
                'type' => 'yesno',
                'value' => '0',
                'disporder' => ++$display_order
            ],
            $setting
        );

        $setting['name'] = $db->escape_string($key);

        $setting['plugin'] = $group_name;

        // Check if the setting already exists.
        $query = $db->simple_select(
            'newpoints_settings',
            'sid',
            "plugin='{$group_name}' AND name='{$setting['name']}'"
        );

        if ($row = $db->fetch_array($query)) {
            // It exists, update it, but keep value intact.
            unset($setting['value']);

            $db->update_query('newpoints_settings', $setting, "sid='{$row['sid']}'");
        } else {
            // It doesn't exist, create it.
            $db->insert_query('newpoints_settings', $setting);
        }
    }

    // Delete deprecated entries.
    $db->delete_query(
        'newpoints_settings',
        "plugin='{$group_name}' AND description='NEWPOINTSDELETEMARKER'"
    );

    // Rebuild the settings file.
    settings_rebuild_cache();
}

function sanitize_array_integers(
    array|string $items_object,
    bool $implode = false,
    string $delimiter = ','
): array|string {
    if (!is_array($items_object)) {
        $items_object = explode($delimiter, $items_object);
    }

    foreach ($items_object as &$item_value) {
        $item_value = (int)$item_value;
    }

    $return_array = array_filter(array_unique($items_object));

    if ($implode) {
        return implode($delimiter, $return_array);
    }

    return $return_array;
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com )
function control_object(&$obj, $code)
{
    static $cnt = 0;
    $newname = '_objcont_' . (++$cnt);
    $objserial = serialize($obj);
    $classname = get_class($obj);
    $checkstr = 'O:' . strlen($classname) . ':"' . $classname . '":';
    $checkstr_len = strlen($checkstr);
    if (substr($objserial, 0, $checkstr_len) == $checkstr) {
        $vars = array();
        // grab resources/object etc, stripping scope info from keys
        foreach ((array)$obj as $k => $v) {
            if ($p = strrpos($k, "\0")) {
                $k = substr($k, $p + 1);
            }
            $vars[$k] = $v;
        }
        if (!empty($vars)) {
            $code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
        }
        eval('class ' . $newname . ' extends ' . $classname . ' {' . $code . '}');
        $obj = unserialize('O:' . strlen($newname) . ':"' . $newname . '":' . substr($objserial, $checkstr_len));
        if (!empty($vars)) {
            $obj->___setvars($vars);
        }
    }
    // else not a valid object or PHP serialize has changed
}

// explicit workaround for PDO, as trying to serialize it causes a fatal error (even though PHP doesn't complain over serializing other resources)
if ($GLOBALS['db'] instanceof AbstractPdoDbDriver) {
    $GLOBALS['AbstractPdoDbDriver_lastResult_prop'] = new ReflectionProperty('AbstractPdoDbDriver', 'lastResult');
    $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setAccessible(true);
    function control_db($code)
    {
        global $db;
        $linkvars = array(
            'read_link' => $db->read_link,
            'write_link' => $db->write_link,
            'current_link' => $db->current_link,
        );
        unset($db->read_link, $db->write_link, $db->current_link);
        $lastResult = $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->getValue($db);
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, null); // don't let this block serialization
        control_object($db, $code);
        foreach ($linkvars as $k => $v) {
            $db->$k = $v;
        }
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, $lastResult);
    }
} elseif ($GLOBALS['db'] instanceof DB_SQLite) {
    function control_db($code)
    {
        global $db;
        $oldLink = $db->db;
        unset($db->db);
        control_object($db, $code);
        $db->db = $oldLink;
    }
} else {
    function control_db($code)
    {
        control_object($GLOBALS['db'], $code);
    }
}