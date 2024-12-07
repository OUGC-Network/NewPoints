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

use AbstractPdoDbDriver;
use DateTime;
use DB_SQLite;
use DirectoryIterator;
use Moderation;
use MyBB;
use PluginLibrary;
use pluginSystem;
use postParser;
use ReflectionProperty;

use const Newpoints\ROOT;

const URL = 'newpoints.php';

const RULE_TYPE_FORUM = 'forum';

const RULE_TYPE_GROUP = 'group';

const TASK_ENABLE = 1;

const TASK_DEACTIVATE = 0;

const TASK_DELETE = -1;

function language_load(string $plugin = '', bool $forceUserArea = false, bool $suppressError = false): bool
{
    global $lang;

    if ($plugin === '') {
        isset($lang->newpoints) || $lang->load('newpoints', $forceUserArea, $suppressError);
    } elseif ($plugin === 'module_meta') {
        isset($lang->nav_plugins) || $lang->load('newpoints_module_meta', $forceUserArea, $suppressError);
    } elseif (!isset($lang->{"newpoints_{$plugin}"})) {
        $lang->set_path(MYBB_ROOT . 'inc/plugins/newpoints/languages');

        $lang->load("newpoints_{$plugin}", $forceUserArea, $suppressError);

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

function run_hooks(string $hook_name = '', &$hook_arguments = '')
{
    global $plugins;

    if ($plugins instanceof pluginSystem) {
        $hook_arguments = $plugins->run_hooks('newpoints_' . $hook_name, $hook_arguments);
    }

    return $hook_arguments;
}

function url_handler(string $newUrl = ''): string
{
    static $setUrl = null;

    if ($setUrl === null) {
        $setUrl = main_file_name();
    }

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

    if (!($PL instanceof PluginLibrary)) {
        $PL || require_once PLUGINLIBRARY;
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

    return SETTINGS[$setting_key] ?? (
        $mybb->settings['newpoints_' . $setting_key] ?? false
    );
}

/**
 * Somewhat like htmlspecialchars_uni but for JavaScript strings
 *
 * @param string $str : The string to be parsed
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
        'allow_html' => false,
        'allow_mycode' => true,
        'allow_smilies' => false,
        'allow_imgcode' => true,
        'filter_badwords' => true,
        'nl2br' => false
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

    return my_strlen($message);
}

/**
 * Deletes templates from the database
 *
 * @param array $templates a list of templates seperated by ',' e.g. 'test','test_again','testing'
 * @param string $newpoints_prefix
 * @return bool false if something went wrong
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
 * @param string $name the title of the template
 * @param string $contents the contents of the template
 * @param int $sid the sid of the template
 * @return bool false if something went wrong
 *
 */
function templates_add(string $name, string $contents, int $sid = -1): bool
{
    global $db;

    if (!$name || !$contents) {
        return false;
    }

    $name = strpos($name, 'newpoints_') === 0 ? $name : 'newpoints_' . $name;

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

        if (file_exists($file_path)) {
            $template_contents = file_get_contents($file_path);
        } else {
            $template_contents = '';
        }

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

    if (!($PL instanceof PluginLibrary)) {
        $PL || require_once PLUGINLIBRARY;
    }

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
 * @param array $settings a list of settings seperated by ',' e.g. 'test','test_again','testing'
 * @param string $newpoints_prefix
 * @return bool false if something went wrong
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
 * @param string $plugin the name (unique identifier) of the setting plugin
 * @param array $settings the array containing the settings
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
 * @param string $name the name (unique identifier) of the setting
 * @param string $plugin the codename of plugin which owns the setting ('main' for main setting)
 * @param string $title the title of the setting
 * @param string $description the description of the setting
 * @param string $options_code the type of the setting ('text', 'textarea', etc...)
 * @param string $value the value of the setting
 * @param int $display_order the display order of the setting
 * @return bool false on failure, true on success
 *
 */
function settings_add(
    string $name,
    string $plugin,
    string $title,
    string $description,
    string $options_code,
    string $value = '',
    int $display_order = 0
): bool {
    global $db;

    if ($name == '' || $plugin == '' || $title == '' || $description == '' || $options_code == '') {
        return false;
    }

    $setting = [
        'name' => $db->escape_string($name),
        'plugin' => $db->escape_string($plugin),
        'title' => $db->escape_string($title),
        'description' => $db->escape_string($description),
        'type' => $db->escape_string($options_code),
        'value' => $db->escape_string($value),
        'disporder' => $display_order
    ];

    if (!$display_order) {
        $query = $db->simple_select(
            'newpoints_settings',
            'disporder',
            "name='{$setting['name']}' AND plugin='{$setting['plugin']}'"
        );

        $current_display_order = (int)$db->fetch_field($query, 'disporder');

        if ($current_display_order > 0) {
            $setting['disporder'] = $current_display_order;
        } else {
            $query = $db->simple_select(
                'newpoints_settings',
                'MAX(disporder) AS max_display_order',
                "plugin='{$setting['plugin']}'"
            );

            $max_display_order = (int)$db->fetch_field($query, 'max_display_order');

            if ($max_display_order > 0) {
                $setting['disporder'] = $max_display_order + 1;
            }
        }
    }

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
    global $cache;

    $settings = $cache->read('newpoints_settings');

    global $mybb;

    foreach (SETTINGS as $name => $value) {
        $mybb->settings["newpoints_{$name}"] = $value;
    }

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
    global $mybb;

    static $done = false;

    if ($done) {
        return true;
    }

    $done = true;

    // Load NewPoints' settings whenever NewPoints plugin is executed
    // Adds one additional query per page
    // TODO: Perhaps use Plugin Library to modify the init.php file to load settings from both tables (MyBB's and NewPoints')
    // OR: Go back to the old method and put the settings in the settings table but keep a copy in NewPoints' settings table
    // but also add a page on ACP to run the check and fix any missing settings or perhaps do the check via task.
    if (defined('IN_ADMINCP')) {
        global $mybb, $db;

        // Plugins get "require_once" on Plugins List and Plugins Check and we do not want to load our settings when our file is required by those
        if ($mybb->get_input('module') === 'config-plugins' || !$db->table_exists('newpoints_settings')) {
            //return false;
        }
    }

    return settings_load();
}

/**
 * Rebuild the settings cache.
 *
 * @param array $settings An array which will contain the settings once the function is run.
 */
function settings_rebuild_cache(array &$settings = []): array
{
    global $db, $cache, $mybb;

    $settings = [];

    if (!$db->table_exists('newpoints_settings')) {
        return $settings;
    }

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

                    if (in_array($setting_data['type'], ['select', 'checkbox', 'radio'])) {
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
        foreach ($settings_list as $setting_group => $settings_data) {
            if (empty($lang->{"setting_group_newpoints_{$setting_group}"})) {
                $lang->{"setting_group_newpoints_{$setting_group}"} = $setting_group;
            }

            if (empty($lang->{"setting_group_newpoints_{$setting_group}_desc"})) {
                $lang->{"setting_group_newpoints_{$setting_group}_desc"} = '';
            }

            settings(
                $setting_group,
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
 * @param int $uid the id of the user
 * @param float $points the number of points to add or subtract (if a negative value)
 * @param float $forumrate the forum income rate
 * @param float $grouprate the user group income rate
 * @param bool $isstring if the uid is a string in case we don't have the uid we can update the points field by searching for the user name
 * @param bool $immediate true if you want to run the query immediatly. Default is false which means the query will be run on shut down. Note that if the previous paremeter is set to true, the query is run immediatly
 * Note: some pages (by other plugins) do not run queries on shutdown so adding this to shutdown may not be good if you're not sure if it will run.
 * @return bool
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

    $points_rounded = round($points * $forumrate * $grouprate, (int)get_setting('main_decimal'));

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

function points_substract(
    int $uid,
    float $points
): bool {
    return points_add($uid, -$points, 1, 1, false, true);
}

function points_add_simple(
    int $user_id,
    float $points,
    int $forum_id = 0
): bool {
    $forum_rate = 1;

    if ($forum_id !== 0) {
        $forum_rate = rules_forum_get_rate($forum_id);

        if (!$forum_rate) {
            return false;
        }
    }

    $group_rate = rules_get_group_rate(get_user($user_id));

    if (!$group_rate) {
        return false;
    }

    return points_add($user_id, $points, $forum_rate, $group_rate, false, true);
}

function points_update(): bool
{
    global $userpoints, $db;

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
 * @param float $points the amount of points
 * @return string formated points
 *
 */
function points_format(float $points): string
{
    return get_setting('main_curprefix') . my_number_format(
            round($points, (int)get_setting('main_decimal'))
        ) . get_setting('main_cursuffix');
}

/**
 * Get rules of a certain group or forum
 *
 * @param string $type the type of rule: 'forum' or 'group'
 * @param int $id the id of the group or forum
 * @return array false if something went wrong
 */
function rules_get(string $type, int $id): array
{
    global $db, $cache;

    $rule_data = [];

    if ($type === RULE_TYPE_FORUM) {
        $typeid = 'f';
    } elseif ($type === RULE_TYPE_GROUP) {
        $typeid = 'g';
    } else {
        return $rule_data;
    }

    $cached_rules = $cache->read('newpoints_rules');

    if (!$cached_rules) {
        //throw new Exception('Invalid rule identifier');
        // Something's wrong so let's get rule from DB
        // To fix this issue, the administrator should edit a rule and save it (all rules are re-cached when one is added/edited)
        $query = $db->simple_select("newpoints_{$type}rules", 'rate', "{$typeid}id='{$id}'");

        if ($db->num_rows($query)) {
            $rule_data = $db->fetch_array($query);
        }
    } elseif (!empty($cached_rules) && isset($cached_rules[$type]) && !empty($cached_rules[$type][$id])) {
        // If the array is not empty then grab from cache
        $rule_data = $cached_rules[$type][$id];
    }

    return $rule_data;
}

function rules_forum_get(int $forum_id): array
{
    return rules_get(RULE_TYPE_FORUM, $forum_id);
}

function rules_group_get(int $group_id): array
{
    return rules_get(RULE_TYPE_GROUP, $group_id);
}

/**
 * Get all rules
 *
 * @param string $type the type of rule: 'forum' or 'group'
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

function rules_forum_get_rate(int $forum_id): float
{
    $forum_data = get_forum($forum_id);

    return isset($forum_data['newpoints_rate']) ? (float)$forum_data['newpoints_rate'] : 1;
}

function rate_group_get(int $group_id)
{
    $group_rules = rules_group_get($group_id);

    return isset($group_rules['rate']) ? (float)$group_rules['rate'] : 1;
}

function rules_get_group_rate(array $user = [], string $rate_key = 'newpoints_rate'): float
{
    global $mybb;

    $group_rate = 1;

    if (empty($user)) {
        $user = $mybb->user;
    }

    $rate_values = [];

    $user_groups = (string)$user['usergroup'];

    if (!get_setting('main_group_rate_primary_only')) {
        $user_groups .= ",{$user['additionalgroups']}";
    }

    $groups_cache = $mybb->cache->read('usergroups');

    foreach (explode(',', $user_groups) as $group_id) {
        $group_data = $groups_cache[(int)$group_id] ?? [];

        if (!empty($group_data[$rate_key])) {
            $rate_values[] = (float)$group_data[$rate_key];
        }
    }

    if (empty($rate_values)) {
        return $group_rate;
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
 * @param array $rules An array which will contain the rules once the function is run.
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
function private_message_send(array $private_message_data, int $from_user_id = 0, bool $admin_override = false): bool
{
    global $session;

    $private_message_data['ipaddress'] = $private_message_data['ipaddress'] ?? $session->packedip;

    return send_pm($private_message_data, $from_user_id, $admin_override);
}

function my_alerts_send(int $from_user_id, int $to_user_id, string $alert_code)
{
    global $db;

    if (!class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        return false;
    }

    $query = $db->simple_select('alert_types', 'id', "code='{$alert_code}'");

    $alert_type_id = (int)$db->fetch_field($query, 'id');

    if (!$alert_type_id) {
        return false;
    }

    $query = $db->simple_select(
        'alerts',
        'id',
        "object_id='{$from_user_id}' AND uid='{$to_user_id}' AND unread=1 AND alert_type_id='{$alert_type_id}'"
    );

    if ($db->num_rows($query)) {
        return false;
    }

    $time = new DateTime();

    $db->insert_query('alerts', [
        'uid' => $to_user_id,
        'from_user_id' => $from_user_id,
        'alert_type_id' => $alert_type_id,
        'object_id' => $from_user_id,
        'dateline' => $time->format('Y-m-d H:i:s'),
        'extra_details' => json_encode([]),
        'unread' => 1,
    ]);
}

/**
 * Get the user group data of the gid
 *
 * @param int $gid the usergroup ID
 * @return array the user data
 *
 */
function get_group(int $gid): array
{
    global $db;

    if (!$gid) {
        return [];
    }

    $query = $db->simple_select('usergroups', '*', 'gid=\'' . $gid . '\'');

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    }

    return [];
}

/**
 * Find and replace a string in a particular template in global templates set
 *
 * @param string $title The name of the template
 * @param string $find The regular expression to match in the template
 * @param string $replace The replacement string
 * @return bool true if matched template name, false if not.
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
 * @param string $action action taken
 * @param string $data extra data
 * @param string $username $username of who's executed the action
 * @param int $uid $uid of who's executed the action
 * @return bool false if something went wrong
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
 * @param array $action action taken
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
 * @param array|string $groups_comma Allowed usergroups (if set to 'all', every user has access; if set to '' no one has)
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
    global $mypoints, $newpoints_user_balance_formatted;

    if (empty($mybb->user) || empty($mybb->user['uid']) || !isset($mybb->user['newpoints'])) {
        $mybb->user['newpoints'] = 0;
    } else {
        $mybb->user['newpoints'] = (float)$mybb->user['newpoints'];
    }

    $newpoints_user_balance_formatted = $mypoints = points_format($mybb->user['newpoints']);

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

// Updates users' points by user group
function users_update(): bool
{
    global $db, $cache;

    $user_groups = $cache->read('usergroups');

    foreach ($user_groups as $user_group_data) {
        if (
            empty($user_group_data['newpoints_allowance']) ||
            empty($user_group_data['newpoints_allowance_period']) ||
            $user_group_data['newpoints_allowance_last_stamp'] > (TIME_NOW - $user_group_data['newpoints_allowance_period'])
        ) {
            continue;
        }

        $amount = (float)$user_group_data['newpoints_allowance'];

        $group_id = (int)$user_group_data['gid'];

        $where_clauses = ["`usergroup`='{$group_id}'"];

        if (empty($user_group_data['newpoints_allowance_primary_only'])) {
            switch ($db->type) {
                case 'pgsql':
                case 'sqlite':
                    $where_clauses[] = "','||additionalgroups||',' LIKE '%,{$group_id},%'";
                    break;
                default:
                    $where_clauses[] = "CONCAT(',',`additionalgroups`,',') LIKE '%,{$group_id},%'";
            }
        }

        $db->update_query(
            'users',
            ['newpoints' => "`newpoints`+'{$amount}'"],
            implode(' OR ', $where_clauses),
            '',
            true
        );

        $db->update_query('usergroups', ['newpoints_allowance_last_stamp' => TIME_NOW], "gid='{$group_id}'");
    }

    $cache->update_usergroups();

    return true;
}

/**
 * Get the user data of a user name
 *
 * @param string $username the user name
 * @param string $fields the fields to fetch
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

function users_get_group_permissions(int $user_id): array
{
    $user = get_user($user_id);

    $user_group = [];

    if (!empty($user['uid'])) {
        $user_group = usergroup_permissions(
            !empty($user['additionalgroups']) ? $user['usergroup'] . ',' . $user['additionalgroups'] : $user['usergroup']
        );

        if (!empty($user['displaygroup'])) {
            $display_group = usergroup_displaygroup($user['displaygroup']);

            if (is_array($display_group)) {
                $user_group = array_merge($user_group, $display_group);
            }
        }
    }

    return $user_group;
}

function group_permission_get_lowest(string $permission_key, int $user_id = 0): float
{
    if (!$user_id) {
        global $mybb;

        $user_id = (int)$mybb->user['uid'];
    }

    $user_data = get_user($user_id);

    $group_ids = $user_data['usergroup'] ?? '';

    if (!empty($user_data['usergroup'])) {
        $group_ids .= ',' . $user_data['additionalgroups'];
    }

    foreach (explode(',', $group_ids) as $group_id) {
        $group_permissions = usergroup_permissions($group_id);

        if (!isset($group_permissions[$permission_key])) {
            continue;
        }

        $group_value = (float)$group_permissions[$permission_key];

        if (!isset($permission_value)) {
            $permission_value = $group_value;

            continue;
        }

        if ($group_value < $permission_value) {
            $permission_value = $group_value;
        }
    }

    if (isset($permission_value)) {
        return $permission_value;
    }

    return 0;
}

/* --- Setting groups and settings: --- */

/**
 * Create and/or update setting group and settings. Taken from PluginLibrary
 *
 * @param string $group_name
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
    array $items_object
): array {
    foreach ($items_object as &$item_value) {
        $item_value = (int)$item_value;
    }

    return array_filter(array_unique($items_object));
}

function task_enable(
    string $plugin_code = '',
    string $title = '',
    string $description = '',
    int $action = TASK_ENABLE
): bool {
    global $db, $lang;

    language_load();

    if ($action === TASK_DELETE) {
        $db->delete_query('tasks', "file='{$plugin_code}'");

        return true;
    }

    $db_query = $db->simple_select('tasks', '*', "file='{$plugin_code}'", ['limit' => 1]);

    if ($db->num_rows($db_query)) {
        $db->update_query('tasks', ['enabled' => $action], "file='{$plugin_code}'");
    } else {
        include_once MYBB_ROOT . 'inc/functions_task.php';

        $new_task_data = [
            'title' => $db->escape_string($title),
            'description' => $db->escape_string($description),
            'file' => $db->escape_string($plugin_code),
            'minute' => 0,
            'hour' => 0,
            'day' => $db->escape_string('*'),
            'weekday' => 0,
            'month' => $db->escape_string('*'),
            'enabled' => 1,
            'logging' => 1
        ];

        $new_task_data['nextrun'] = fetch_next_run($new_task_data);

        $db->insert_query('tasks', $new_task_data);
    }

    return true;
}

function task_disable(string $plugin_code = ''): bool
{
    task_enable($plugin_code, '', '', TASK_DEACTIVATE);

    return true;
}

function task_delete(string $plugin_code = ''): bool
{
    task_enable($plugin_code, '', '', TASK_DELETE);

    return true;
}

function page_build_menu_options(): string
{
    static $options = null;

    if ($options === null) {
        global $mybb, $lang, $theme;

        $menu_items = [
            0 => [
                'lang_string' => 'newpoints_home',
            ]
        ];

        if (!empty($mybb->usergroup['newpoints_can_see_stats'])) {
            $menu_items[10] = [
                'action' => 'stats',
                'lang_string' => 'newpoints_statistics',
            ];
        }

        if (!empty($mybb->usergroup['newpoints_can_donate'])) {
            $menu_items[20] = [
                'action' => 'donate',
                'lang_string' => 'newpoints_donate',
            ];
        }

        $menu_items = run_hooks('default_menu', $menu_items);

        $alternative_background = alt_trow(true);

        $options = '';

        foreach ($menu_items as $option) {
            if (isset($option['setting']) && !get_setting($option['setting'])) {
                continue;
            }

            $action_url = $item_selected = $option_name = '';

            if (isset($option['action'])) {
                $action_url = url_handler_build(['action' => $option['action']]);

                if (my_strtolower($mybb->get_input('action')) === my_strtolower($option['action'])) {
                    $item_selected = eval(templates_get('option_selected'));
                }
            } else {
                $action_url = url_handler_build();
            }

            $option_name = '';

            if (isset($option['lang_string']) && isset($lang->{$option['lang_string']})) {
                $option_name = $lang->{$option['lang_string']};
            } elseif (isset($option['action'])) {
                $option_name = ucwords((string)$option['action']);
            }

            $option = run_hooks('menu_build_option', $option);

            $options .= eval(templates_get('option'));

            $alternative_background = alt_trow();
        }
    }

    return $options;
}

function page_build_menu(): string
{
    global $mybb, $lang, $theme;

    $menu_options = page_build_menu_options();

    return eval(templates_get('menu'));
}

function main_file_name(): string
{
    return (string)get_setting('main_file');
}

function get_income_value(string $income_type): float
{
    $income_value = 1;

    switch ($income_type) {
        case INCOME_TYPE_POST_NEW:
            $income_value = (float)get_setting('income_newpost');
            break;
        case INCOME_TYPE_POST_MINIMUM_CHARACTERS:
            $income_value = (float)get_setting('income_minchar');
            break;
        case INCOME_TYPE_POST_PER_CHARACTER:
            $income_value = (float)get_setting('income_perchar');
            break;
        case INCOME_TYPE_POST_PER_REPLY:
            $income_value = (float)get_setting('income_perreply');
            break;
        case INCOME_TYPE_PAGE_VIEW:
            $income_value = (float)get_setting('income_pageview');
            break;
        case INCOME_TYPE_VISIT:
            $income_value = (float)get_setting('income_visit');
            break;
        case INCOME_TYPE_VISIT_MINUTES:
            $income_value = (float)get_setting('income_visit_minutes');
            break;
        case INCOME_TYPE_THREAD_NEW:
            $income_value = (float)get_setting('income_newthread');
            break;
        case INCOME_TYPE_POLL_NEW:
            $income_value = (float)get_setting('income_newpoll');
            break;
        case INCOME_TYPE_USER_REGISTRATION:
            $income_value = (float)get_setting('income_newreg');
            break;
        case INCOME_TYPE_USER_REFERRAL:
            $income_value = (float)get_setting('income_referral');
            break;
        case INCOME_TYPE_POLL_VOTE:
            $income_value = (float)get_setting('income_pervote');
            break;
        case INCOME_TYPE_PRIVATE_MESSAGE_NEW:
            $income_value = (float)get_setting('income_pmsent');
            break;
    }

    return $income_value;
}

function post_parser(): postParser
{
    global $parser;

    if (!($parser instanceof postParser)) {
        require_once MYBB_ROOT . 'inc/class_parser.php';

        $parser = new postParser();
    }

    return $parser;
}

function post_parser_parse_message(
    string $message,
    array $options = []
): string {
    return post_parser()->parse_message($message, array_merge([
        'allow_html' => false,
        'allow_mycode' => true,
        'allow_smilies' => true,
        'allow_imgcode' => true,
        'allow_videocode' => true,
        'filter_badwords' => true,
        'shorten_urls' => true,
        'highlight' => false,
        'me_username' => ''
    ], $options));
}

function moderation_object()
{
    static $moderation = null;

    if (!($moderation instanceof Moderation)) {
        require_once MYBB_ROOT . 'inc/class_moderation.php';

        $moderation = new Moderation();
    }

    return $moderation;
}

function plugins_version_get(string $plugin_code): int
{
    global $cache;

    $plugins_list = $cache->read('newpoints_plugins_versions');

    if (!$plugins_list) {
        $plugins_list = [];
    }

    if (isset($plugins_list[$plugin_code])) {
        return (int)$plugins_list[$plugin_code];
    }

    return 0;
}

function plugins_version_update(string $plugin_code, int $version): bool
{
    global $cache;

    $plugins_list = (array)$cache->read('newpoints_plugins_versions');

    if (isset($plugins_list[$plugin_code])) {
        unset($plugins_list[$plugin_code]);
    }

    if (!empty($plugins_list)) {
        $cache->update('newpoints_plugins_versions', $plugins_list);
    } else {
        $cache->delete('newpoints_plugins_versions');
    }

    return true;
}

function plugins_version_delete(string $plugin_code): bool
{
    global $cache;

    $plugins_list = (array)$cache->read('newpoints_plugins_versions');

    if (isset($plugins_list[$plugin_code])) {
        unset($plugins_list[$plugin_code]);
    }

    if (!empty($plugins_list)) {
        $cache->update('newpoints_plugins_versions', $plugins_list);
    } else {
        $cache->delete('newpoints_plugins_versions');
    }

    return true;
}

function page_build_cancel_confirmation(
    string $form_input_name,
    int $form_input_value,
    string $table_text,
    string $form_view_name
): string {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons;

    $page_title = $table_title = $lang->newpoints_page_confirm_table_cancel_title;

    $button_text = $lang->newpoints_page_confirm_table_cancel_button;

    add_breadcrumb($page_title);

    $mybb->get_input['manage'] = $mybb->get_input('manage', MyBB::INPUT_INT);

    $confirm_contents = eval(templates_get('page_confirm_cancel'));

    $newpoints_content = eval(templates_get('page_confirm'));

    $page_contents = eval(templates_get('page'));

    output_page($page_contents);

    exit;
}

function page_build_purchase_confirmation(
    string $table_description,
    string $form_input_name,
    int $form_input_value,
    string $form_view_name = '',
    string $extra_rows = ''
): string {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons;

    $page_title = $table_title = $lang->newpoints_page_confirm_table_purchase_title;

    $button_text = $lang->newpoints_page_confirm_table_purchase_button;

    add_breadcrumb($page_title);

    $mybb->get_input['manage'] = $mybb->get_input('manage', MyBB::INPUT_INT);

    $confirm_contents = eval(templates_get('page_confirm_purchase'));

    $newpoints_content = eval(templates_get('page_confirm'));

    if ($newpoints_pagination) {
        $newpoints_pagination = eval(templates_get('page_pagination'));
    }

    $page_contents = eval(templates_get('page'));

    output_page($page_contents);

    exit;
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com )
function control_object(&$obj, string $code)
{
    static $cnt = 0;
    $newname = '_objcont_newpoints_' . (++$cnt);
    $objserial = serialize($obj);
    $classname = get_class($obj);
    $checkstr = 'O:' . strlen($classname) . ':"' . $classname . '":';
    $checkstr_len = strlen($checkstr);
    if (substr($objserial, 0, $checkstr_len) == $checkstr) {
        $vars = [];
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
    function control_db(string $code)
    {
        global $db;
        $linkvars = [
            'read_link' => $db->read_link,
            'write_link' => $db->write_link,
            'current_link' => $db->current_link,
        ];
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
    function control_db(string $code)
    {
        global $db;
        $oldLink = $db->db;
        unset($db->db);
        control_object($db, $code);
        $db->db = $oldLink;
    }
} else {
    function control_db(string $code)
    {
        control_object($GLOBALS['db'], $code);
    }
}