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
 *    NewPoints is a complex but efficient points system for MyBB.
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
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use Moderation;
use MyBB;
use MybbStuff_MyAlerts_AlertManager;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_Alert;
use PluginLibrary;
use pluginSystem;
use postParser;
use ReflectionProperty;
use Newpoints\System\Instance;

use function Newpoints\Hooks\Forum\myalerts_register_client_alert_formatters;

use const Newpoints\ROOT;

function language_load(string $plugin_code = '', bool $force_user_area = false, bool $suppress_error = false): bool
{
    global $lang;

    if ($plugin_code === '') {
        isset($lang->newpoints) || $lang->load('newpoints', $force_user_area, $suppress_error);
    } elseif ($plugin_code === 'module_meta') {
        isset($lang->nav_plugins) || $lang->load('newpoints_module_meta', $force_user_area, $suppress_error);
    } else {
        if (my_strpos($plugin_code, 'newpoints_') === 0) {
            $plugin_code = str_replace('newpoints_', '', $plugin_code);
        }

        if (!isset($lang->{"newpoints_{$plugin_code}"})) {
            $lang->set_path(MYBB_ROOT . 'inc/plugins/newpoints/languages');

            $lang->load("newpoints_{$plugin_code}", $force_user_area, $suppress_error);

            $lang->set_path(MYBB_ROOT . 'inc/languages');
        }
    }

    return true;
}

function add_hooks(string $namespace): void
{
    global $plugins;

    $namespace_lowercase = strtolower($namespace);

    $defined_user_functions = get_defined_functions()['user'];

    foreach ($defined_user_functions as $callable) {
        $namespace_with_prefix_length = strlen($namespace_lowercase) + 1;

        if (substr($callable, 0, $namespace_with_prefix_length) == $namespace_lowercase . '\\') {
            $hook_name = substr_replace($callable, '', 0, $namespace_with_prefix_length);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hook_name, -2))) {
                $hook_name = substr($hook_name, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hook_name, $callable, $priority);
        }
    }
}

function run_hooks(string $hook_name = '', array &$hook_arguments = []): array
{
    if (get_setting('disable_plugins') !== false) {
        return $hook_arguments;
    }

    global $plugins;

    if ($plugins instanceof pluginSystem) {
        $hook_arguments = $plugins->run_hooks('newpoints_' . $hook_name, $hook_arguments);
    }

    return (array)$hook_arguments;
}

function url_handler(string $new_url = '', int $instance_id = INSTANCE_DEFAULT_ID): string
{
    static $setUrl = null;

    if ($setUrl === null) {
        $setUrl = main_file_name($instance_id);
    }

    if (($new_url = trim($new_url))) {
        $setUrl = $new_url;
    }

    return $setUrl;
}

function url_handler_set(string $new_url): string
{
    return url_handler($new_url);
}

function url_handler_get(): string
{
    return url_handler();
}

function url_handler_build(array $url_append = [], bool $fetch_import_url = false, bool $encode = true): string
{
    global $PL;

    if (!($PL instanceof PluginLibrary)) {
        require_once PLUGINLIBRARY;
    }

    if ($fetch_import_url === false) {
        if ($url_append && !is_array($url_append)) {
            $url_append = explode('=', $url_append);
            $url_append = [$url_append[0] => $url_append[1]];
        }
    }

    return $PL->url_append(url_handler_get(), $url_append, '&amp;', $encode);
}

function get_setting(
    string $setting_key = '',
    int $instance_id = INSTANCE_DEFAULT_ID
): bool|string|int|float {
    global $mybb;

    static $setting_values = [];

    $settings_cache = $mybb->cache->read('newpoints_settings');

    if (isset($setting_values[$instance_id][$setting_key])) {
        return $setting_values[$instance_id][$setting_key];
    }

    if (isset(SETTINGS[$instance_id][$setting_key])) {
        $setting_values[$instance_id][$setting_key] = SETTINGS[$instance_id][$setting_key] ?? false;
    } elseif (isset($settings_cache[$instance_id]['newpoints_' . $setting_key])) {
        $setting_values[$instance_id][$setting_key] = $settings_cache[$instance_id]['newpoints_' . $setting_key] ?? false;
    } elseif (isset(SETTINGS[$setting_key])) {
        $setting_values[$instance_id][$setting_key] = SETTINGS[$setting_key] ?? false;
    } else {
        $setting_values[$instance_id][$setting_key] = $mybb->settings['newpoints_' . $setting_key] ?? false;
    }

    return $setting_values[$instance_id][$setting_key];
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
        'allow_smilies' => true,
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

    $insert_data = [
        'title' => $db->escape_string($name),
        'template' => $db->escape_string($contents),
        'sid' => $sid
    ];

    $query = $db->simple_select(
        'templates',
        'tid,title,template',
        "sid='{$sid}' AND title='{$insert_data['title']}'"
    );

    $templates = $duplicates = [];

    while ($template_data = $db->fetch_array($query)) {
        if (isset($templates[$template_data['title']])) {
            $duplicates[$template_data['tid']] = $template_data['tid'];
            $templates[$template_data['title']]['template'] = false;
        } else {
            $templates[$template_data['title']] = $template_data;
        }
    }

    // Remove duplicates
    if ($duplicates) {
        $db->delete_query('templates', 'tid IN (' . implode(',', $duplicates) . ')');
    }

    // Update if necessary, insert otherwise
    if (isset($templates[$name])) {
        if ($templates[$name]['template'] !== $contents) {
            return $db->update_query('templates', $insert_data, "tid={$templates[$name]['tid']}");
        }

        return false;
    }

    $db->insert_query('templates', $insert_data);

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
            $templates->cache[templates_get_name($template_name, $plugin_prefix)] = file_get_contents($file_path);
        }
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

    foreach (instance_get() as $instance_id => $instance_data) {
        $db->update_query(
            'newpoints_settings',
            ['description' => 'NEWPOINTSDELETESETTING'],
            "plugin='{$plugin}' AND instance_id='{$instance_id}'"
        );

        $display_order = 0;

        // Create and/or update settings.
        foreach ($settings as $key => $setting) {
            $setting = array_intersect_key(
                $setting,
                [
                    'title' => 0,
                    'description' => 0,
                    'type' => 0,
                    'value' => 0,
                ]
            );

            $setting = array_map([$db, 'escape_string'], $setting);

            $setting = array_merge(
                [
                    'title' => '',
                    'description' => '',
                    'type' => 'yesno',
                    'value' => 0,
                    'disporder' => ++$display_order,
                    'instance_id' => $instance_id
                ],
                $setting
            );

            $setting['plugin'] = $plugin_escaped;

            $setting['name'] = $db->escape_string($plugin . '_' . $key);

            $query = $db->simple_select(
                'newpoints_settings',
                'sid',
                "plugin='{$setting['plugin']}' AND name='{$setting['name']}' AND instance_id='{$instance_id}'"
            );

            if ($sid = $db->fetch_field($query, 'sid')) {
                unset($setting['value']);
                $db->update_query('newpoints_settings', $setting, "sid='{$sid}'");
            } else {
                $db->insert_query('newpoints_settings', $setting);
            }
        }
    }

    $db->delete_query(
        'newpoints_settings',
        "plugin='{$plugin_escaped}' AND description='NEWPOINTSDELETESETTING' AND instance_id='{$instance_id}'"
    );

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

    if (my_strpos($plugin, 'newpoints_') !== false) {
        //$plugin = 'newpoints_' . $plugin;
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

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        $setting['instance_id'] = $instance_id;

        if (!$display_order) {
            $query = $db->simple_select(
                'newpoints_settings',
                'disporder',
                "name='{$setting['name']}' AND plugin='{$setting['plugin']}' AND instance_id='{$instance_id}'"
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
            "name='{$setting['name']}' AND plugin='{$setting['plugin']}' instance_id='{$instance_id}'"
        );

        if ($sid = $db->fetch_field($query, 'sid')) {
            unset($setting['value']);

            $db->update_query('newpoints_settings', $setting, "sid='{$sid}'");
        } else {
            $db->insert_query('newpoints_settings', $setting);
        }
    }

    return true;
}

function settings_load(): void
{
    global $cache;

    $settings = $cache->read('newpoints_settings');

    global $mybb;

    if (!empty($settings)) {
        foreach ($settings as $name => $value) {
            $mybb->settings[$name] = $value;
        }
    }

    foreach (SETTINGS as $name => $value) {
        $mybb->settings["newpoints_{$name}"] = $value;
    }

    /* something is wrong so let's rebuild the cache data */
    if (empty($settings)) {
        settings_rebuild_cache();
    }
}

function settings_load_init(): void
{
    static $done = false;

    if ($done) {
        return;
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

    settings_load();
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

    $query = $db->simple_select('newpoints_settings', 'value, name, instance_id', '', $options);

    while ($setting = $db->fetch_array($query)) {
        $instance_id = (int)$setting['instance_id'];

        //$setting['value']=str_replace("\"", "\\\"", $setting['value']);
        $settings[$instance_id][$setting['name']] = $setting['value'];

        if ($instance_id === INSTANCE_DEFAULT_ID) {
            $mybb->settings[$setting['name']] = $setting['value'];
        }
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
                        //continue;
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

                if (!isset($settings_list[$setting_group])) {
                    $settings_list[$setting_group] = [];
                }

                $settings_list[$setting_group] = array_merge($settings_list[$setting_group], $settings_data);
            }
        }
    }

    $hook_arguments = run_hooks('settings_rebuild_end', $hook_arguments);

    if ($settings_list) {
        foreach ($settings_list as $setting_group => $settings_data) {
            if (!isset($lang->{"setting_group_newpoints_{$setting_group}"}) && !DEBUG) {
                $lang->{"setting_group_newpoints_{$setting_group}"} = $setting_group;
            }

            if (!isset($lang->{"setting_group_newpoints_{$setting_group}_desc"}) && !DEBUG) {
                $lang->{"setting_group_newpoints_{$setting_group}_desc"} = '';
            }

            if (!isset($lang->{"setting_group_newpoints_{$setting_group}"})) {
                //_dump("setting_group_newpoints_{$setting_group}");
            }

            if (!isset($lang->{"setting_group_newpoints_{$setting_group}_desc"})) {
                //_dump("setting_group_newpoints_{$setting_group}_desc");
            }

            foreach (instance_get() as $instance_id => $instance_data) {
                settings(
                    $setting_group,
                    $lang->{"setting_group_newpoints_{$setting_group}"},
                    $lang->{"setting_group_newpoints_{$setting_group}_desc"},
                    $settings_data,
                    $instance_id
                );
            }
        }
    }

    settings_rebuild_cache();

    return true;
}

/**
 * Adds/Subtracts points to a user
 *
 * @param int $user_id the id of the user
 * @param float $points the number of points to add or subtract (if a negative value)
 * @param float $forum_rate the forum income rate
 * @param float $group_rate the user group income rate
 * @param bool $is_string if the uid is a string in case we don't have the uid we can update the points field by searching for the user name
 * @param bool $immediate true if you want to run the query immediatly. Default is false which means the query will be run on shut down. Note that if the previous paremeter is set to true, the query is run immediatly
 * Note: some pages (by other plugins) do not run queries on shutdown so adding this to shutdown may not be good if you're not sure if it will run.
 * @return bool
 */
function points_add(
    int $user_id,
    float $points,
    float $forum_rate = 1,
    float $group_rate = 1,
    bool $is_string = false,
    bool $immediate = false,
    int $instance_id = INSTANCE_DEFAULT_ID,
): bool {
    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
    }

    global $db, $newpoints_shutdown_cache;

    isset($newpoints_shutdown_cache) || $newpoints_shutdown_cache = [];

    if ($points == 0 || ($user_id <= 0 && !$is_string)) {
        return false;
    }

    if ($is_string === true) {
        $immediate = true;
    }

    // might work only for MySQL and MySQLi
    //$db->update_query("users", array('newpoints' =>'newpoints+('.(float)$points.')'), 'uid=\''.(int)$uid.'\'', '', true);

    $points_rounded = round(
        $points * $forum_rate * $group_rate,
        (int)$instance_object->settings_get_value('main_decimal')
    );

    $instance_column_name = $instance_object->get_users_column_name();

    if ($is_string) {
        $db->write_query(
            'UPDATE `' . $db->table_prefix . 'users`
            SET `' . $instance_column_name . '`=`' . $instance_column_name . '`+(' . $points_rounded . ')
            WHERE `username`=\'' . $db->escape_string($user_id) . '\''
        );
        // if immediate, run the query now otherwise add it to shutdown to avoid slow down
    } elseif ($immediate) {
        $db->write_query(
            'UPDATE `' . $db->table_prefix . 'users`
            SET `' . $instance_column_name . '`=`' . $instance_column_name . '`+(' . $points_rounded . ')
            WHERE `uid`=\'' . $user_id . '\''
        );
    } else {
        if (!isset($newpoints_shutdown_cache[$instance_object->get_users_column_name()][$user_id])) {
            $newpoints_shutdown_cache[$instance_object->get_users_column_name()][$user_id] = 0;
        }

        $newpoints_shutdown_cache[$instance_object->get_users_column_name()][$user_id] += $points_rounded;
    }

    static $newpoints_shutdown = false;

    if (!$newpoints_shutdown) {
        $newpoints_shutdown = true;

        add_shutdown('newpoints_update_addpoints');
    }

    return true;
}

function points_subtract(
    int $user_id,
    float $points,
    int $instance_id = INSTANCE_DEFAULT_ID,
): bool {
    return points_add($user_id, -abs($points), 1, 1, false, true, $instance_id);
}

function points_add_simple(
    int $user_id,
    float $points,
    #[Deprecated]
    int $forum_id = 0,
    int $instance_id = INSTANCE_DEFAULT_ID,
): bool {
    if ($forum_id) {
        $forum_data = get_forum($forum_id);

        $points *= $forum_data[Permissions::Rate];
    }

    return points_add(
        $user_id,
        abs($points),
        1,
        1,
        false,
        true,
        $instance_id
    );
}

function points_update(): void
{
    global $newpoints_shutdown_cache, $db;

    if (!empty($newpoints_shutdown_cache)) {
        foreach ($newpoints_shutdown_cache as $instance_column_name => $users) {
            foreach ($users as $uid => $amount) {
                if ($amount < 0) {
                    $db->write_query(
                        'UPDATE `' . $db->table_prefix . 'users` SET `' . $instance_column_name . '`=`' . $instance_column_name . '`-(' . abs(
                            (float)$amount
                        ) . ') WHERE `uid`=\'' . $uid . '\''
                    );
                } else {
                    $db->write_query(
                        'UPDATE `' . $db->table_prefix . 'users` SET `' . $instance_column_name . '`=`' . $instance_column_name . '`+(' . (float)$amount . ') WHERE `uid`=\'' . $uid . '\''
                    );
                }
            }
        }

        unset($newpoints_shutdown_cache);
    }
}

/**
 * Formats points according to the settings
 *
 * @param float $points the amount of points
 * @return string formated points
 *
 */
function points_format(float $points, int $instance_id = INSTANCE_DEFAULT_ID): string
{
    $currency_prefix = get_setting('main_cursuffix', $instance_id);

    $points_formatted = my_number_format(round($points, (int)get_setting('main_decimal', $instance_id)));

    $currency_suffix = get_setting('main_curprefix', $instance_id);

    return eval(templates_get('points_format', false));
}

/**
 * Get rules of a certain group or forum
 *
 * @param string $type the type of rule: 'forum' or 'group'
 * @param int $rule_id the id of the group or forum
 * @return array false if something went wrong
 */
function rules_get(string $type, int $rule_id): array
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
        $query = $db->simple_select("newpoints_{$type}rules", 'rate', "{$typeid}id='{$rule_id}'");

        if ($db->num_rows($query)) {
            $rule_data = $db->fetch_array($query);
        }
    } elseif (!empty($cached_rules) && isset($cached_rules[$type]) && !empty($cached_rules[$type][$rule_id])) {
        // If the array is not empty then grab from cache
        $rule_data = $cached_rules[$type][$rule_id];
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
        return [];
    }

    if ($type == 'forum') {
        $typeid = 'f';
    } elseif ($type == 'group') {
        $typeid = 'g';
    } else {
        return [];
    }

    $rules = [];

    $rules_cache = $cache->read('newpoints_rules');

    if ($rules_cache === false) {
        // Something's wrong so let's get the rules from DB
        // To fix this issue, the administrator should edit a rule and save it (all rules are re-cached when one is added/edited)
        $query = $db->simple_select('newpoints_' . $type . 'rules');
        while ($rule = $db->fetch_array($query)) {
            $rules[$rule[$typeid . 'id']] = $rule;
        }
    } elseif (!empty($rules_cache[$type])) {
        // Not empty? Then grab the chosen rules
        foreach ($rules_cache[$type] as $crule) {
            $rules[$crule[$typeid . 'id']] = $crule;
        }
    }

    return $rules;
}

function rules_forum_get_rate(int $forum_id): float
{
    $forum_data = get_forum($forum_id);

    return isset($forum_data[Permissions::Rate]) ? (float)$forum_data[Permissions::Rate] : 1;
}

function rate_group_get(int $group_id): float
{
    $group_rules = rules_group_get($group_id);

    return isset($group_rules['rate']) ? (float)$group_rules['rate'] : 1;
}

function rules_get_group_rate(
    array $user = [],
    string $rate_key = IncomeRates::RateAddition,
    int $instance_id = INSTANCE_DEFAULT_ID,
): float {
    global $mybb;

    $group_rate = 1;

    if (empty($user)) {
        $user = $mybb->user;
    }

    $rate_values = [];

    $user_groups = (string)$user['usergroup'];

    if (!get_setting('main_group_rate_primary_only', $instance_id)) {
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
    global $db, $cache;

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
function private_message_send(
    array $private_message_data,
    int $from_user_id = PRIVATE_MESSAGE_CURRENT_USER_ID,
    bool $admin_override = false
): bool {
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
 * @param int $group_id the usergroup ID
 * @return array the user data
 *
 */
function get_group(int $group_id): array
{
    global $db;

    if (!$group_id) {
        return [];
    }

    $query = $db->simple_select('usergroups', '*', 'gid=\'' . $group_id . '\'');

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

    $update = [];

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

    if (!empty($update)) {
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
 * @param string $log_action action taken
 * @param string $log_data extra data
 * @param string $username $username of who's executed the action
 * @param int $user_id $uid of who's executed the action
 * @param float $log_points
 * @param int $primary_id
 * @param int $secondary_id
 * @param int $tertiary_id
 * @param int $log_type
 * @return int false if something went wrong
 */
function log_add(
    string $log_action,
    string $log_data = '',
    string $username = '',
    int $user_id = 0,
    float $log_points = 0,
    int $primary_id = 0,
    int $secondary_id = 0,
    int $tertiary_id = 0,
    int $log_type = 0
): int {
    switch ($log_type) {
        case LOGGING_TYPE_INCOME:
            return instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                $log_action,
                $user_id,
                $log_points,
                $primary_id,
                $secondary_id,
                $tertiary_id,
            );
        case LOGGING_TYPE_CHARGE:
            return instance_object(INSTANCE_DEFAULT_ID)->logger->log_charge(
                $log_action,
                $user_id,
                $log_points,
                $primary_id,
                $secondary_id,
                $tertiary_id,
            );
        default:
            return instance_object(INSTANCE_DEFAULT_ID)->logger->log_action(
                $log_action,
                $user_id,
                $log_points,
                $primary_id,
                $secondary_id,
                $tertiary_id,
            );
    }
}

/**
 * Removes all log entries by action
 *
 * @param array $action action taken
 *
 */
function log_remove(array $action): bool
{
    global $db;

    if (empty($action)) {
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

function load_set_guest_data(): void
{
    global $mybb;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance_object = instance_object($instance_id);
        } catch (Exception $e) {
            continue;
        }

        if (empty($mybb->user) ||
            empty($mybb->user['uid']) ||
            !isset($mybb->user[$instance_object->get_users_column_name()])) {
            $mybb->user[$instance_object->get_users_column_name()] = 0;
        } else {
            $mybb->user[$instance_object->get_users_column_name()] =
                (float)$mybb->user[$instance_object->get_users_column_name()];
        }
    }
}

function plugins_load(): bool
{
    if (get_setting('disable_plugins') !== false) {
        return false;
    }

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
            empty($user_group_data[IncomePermissions::UserIncomeUserAllowance]) ||
            empty($user_group_data[IncomePermissions::UserIncomeUserAllowanceMinutes]) ||
            $user_group_data[IncomePermissions::UserIncomeUserAllowanceLastStamp] > (TIME_NOW - $user_group_data[IncomePermissions::UserIncomeUserAllowanceMinutes] * 60)
        ) {
            continue;
        }

        $amount = (float)$user_group_data[IncomePermissions::UserIncomeUserAllowance];

        $group_id = (int)$user_group_data['gid'];

        $where_clauses = ["`usergroup`='{$group_id}'"];

        if (empty($user_group_data[IncomePermissions::UserIncomeUserAllowancePrimaryOnly])) {
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

        $db->update_query(
            'usergroups',
            [IncomePermissions::UserIncomeUserAllowanceLastStamp => TIME_NOW],
            "gid='{$group_id}'"
        );
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

function users_get_group_permissions(int $user_id, int $instance_id = INSTANCE_DEFAULT_ID): array
{
    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
        return [];
    }

    $instance_object->set_user($user_id);

    return $instance_object->user_permissions;
}

function group_permission_get_lowest(string $permission_key, int $user_id = 0): float
{
    if (!$user_id) {
        global $mybb;

        $user_id = (int)$mybb->user['uid'];
    }

    $user_data = get_user($user_id);

    $group_ids = $user_data['usergroup'] ?? '';

    if (!empty($user_data['additionalgroups'])) {
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
function settings(
    string $group_name,
    string $title,
    string $description,
    array $list,
    int $instance_id = INSTANCE_DEFAULT_ID
): void {
    global $db;

    /* Setting group: */

    /* Settings: */

    // Deprecate all the old entries.
    $db->update_query(
        'newpoints_settings',
        ['description' => 'NEWPOINTSDELETEMARKER'],
        "plugin='{$group_name}' AND instance_id='{$instance_id}'"
    );

    foreach ($list as $key => $setting) {
        $key = "newpoints_{$group_name}_{$key}";

        $setting = array_intersect_key(
            $setting,
            [
                'title' => 0,
                'description' => 0,
                'type' => 0,
                'value' => 0,
            ]
        );

        $setting = array_map([$db, 'escape_string'], (array)$setting);

        isset($display_order) || $display_order = 0;

        $setting = array_merge(
            [
                'description' => $description,
                'title' => $title,
                'type' => 'yesno',
                'value' => 0,
                'disporder' => ++$display_order,
                'instance_id' => $instance_id
            ],
            $setting
        );

        $setting['name'] = $db->escape_string($key);

        $setting['plugin'] = $group_name;

        $query = $db->simple_select(
            'newpoints_settings',
            'sid',
            "plugin='{$group_name}' AND name='{$setting['name']}' AND instance_id='{$instance_id}'"
        );

        if ($sid = (int)$db->fetch_field($query, 'sid')) {
            unset($setting['value']);

            $db->update_query('newpoints_settings', $setting, "sid='{$sid}'");
        } else {
            $db->insert_query('newpoints_settings', $setting);
        }
    }

    // Delete deprecated entries.
    $db->delete_query(
        'newpoints_settings',
        "plugin='{$group_name}' AND description='NEWPOINTSDELETEMARKER' AND instance_id='{$instance_id}'"
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
    global $db;

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

function page_build_menu_options(int $instance_id = INSTANCE_DEFAULT_ID): string
{
    global $mybb;
    static $menu = null;

    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
        return '';
    }

    if ($menu === null) {
        global $mybb, $lang, $theme;

        $menu_items = [
            /*0 => [
                'lang_string' => 'newpoints_home',
                'category' => 'main'
            ]*/
        ];

        if ($instance_object->permission_check_boolean(Permissions::CanSeeStats)) {
            $menu_items[get_setting('stats_menu_order', $instance_id)] = [
                'action' => 'stats',
                'lang_string' => 'newpoints_statistics',
                'category' => 'main'
            ];
        }

        if ($instance_object->permission_check_boolean(Permissions::CanDonate)) {
            $menu_items[get_setting('donations_menu_order', $instance_id)] = [
                'action' => 'donate',
                'lang_string' => 'newpoints_donate',
                'category' => 'user'
            ];
        }

        $menu_items = run_hooks('default_menu', $menu_items);

        $menu_items[] = [
            'action' => 'logs',
            'lang_string' => 'newpoints_logs_menu_title',
            'category' => 'user'
        ];

        $options_list = [];

        foreach ($menu_items as $option) {
            $options_list[$option['category'] ?? 'market'][] = $option;
        }

        ksort($options_list);

        global $collapse, $collapsed, $collapsedimg;

        foreach ($options_list as $category_key => $menu_items) {
            $collapsed_name = "category_{$category_key}";

            $collapsedimg[$collapsed_name] = $collapsedimg[$collapsed_name] ?? '';

            $collapsed_image = $collapsedimg[$collapsed_name];

            $collapsed["{$collapsed_name}_e"] = $collapsed["{$collapsed_name}_e"] ?? '';

            $expanded_display = $collapsed["{$collapsed_name}_e"];

            $expanded_alternative_text = !empty($collapsed["{$collapsed_name}_e"]) ? $lang->expcol_expand : $lang->expcol_collapse;

            $alternative_background = alt_trow(true);

            $options = '';

            foreach ($menu_items as $option) {
                if (isset($option['setting']) && !get_setting($option['setting'], $instance_id)) {
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

                if (!is_array($option)) {
                    var_dump($option);

                    $option = (array)$option;
                }

                $option = run_hooks('menu_build_option', $option);

                $options .= eval(templates_get('option'));

                $alternative_background = alt_trow();
            }

            $menu_category_title = 'newpoints_menu_category_' . $category_key;

            $menu_category_title = $lang->{$menu_category_title};

            $menu .= eval(templates_get('menu_category'));
        }
    }

    return $menu;
}

function page_build_menu(int $instance_id = INSTANCE_DEFAULT_ID): string
{
    global $mybb, $lang, $theme;
    global $newpoints_file;

    $menu_options = page_build_menu_options($instance_id);

    return eval(templates_get('menu'));
}

function main_file_name(int $instance_id = INSTANCE_DEFAULT_ID): string
{
    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
        return URL;
    }

    return $instance_object->get_script_file();
}

function get_income_types(): array
{
    $income_types = INCOME_TYPES;

    $income_types = run_hooks('income_types', $income_types);

    foreach ($income_types as $income_type => $income_params) {
        if (!defined('\Newpoints\Core\INCOME_TYPE_' . my_strtoupper($income_type))) {
            //_dump('INCOME_TYPE_' . strtoupper($income_type), $income_type);
            define('\Newpoints\Core\INCOME_TYPE_' . my_strtoupper($income_type), my_strtolower($income_type));
        }
    }

    return $income_types;
}

function get_income_value(
    string $income_type,
    int $user_id = 0,
    int $forum_id = 0,
    int $instance_id = INSTANCE_DEFAULT_ID
): float {
    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
        return 0;
    }

    $instance_object->set_user($user_id);

    $instance_object->set_forum($forum_id);

    return $instance_object->get_income_value($income_type);
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

function moderation_object(): Moderation
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

    $plugins_list[$plugin_code] = $version;

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
): never {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons, $newpoints_additional;

    $page_title = $table_title = $lang->newpoints_page_confirm_table_cancel_title;

    $button_text = $lang->newpoints_page_confirm_table_cancel_button;

    add_breadcrumb($page_title);

    $mybb->input['manage'] = $mybb->get_input('manage', MyBB::INPUT_INT);

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
): never {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons, $newpoints_additional;

    $page_title = $table_title = $lang->newpoints_page_confirm_table_purchase_title;

    $button_text = $lang->newpoints_page_confirm_table_purchase_button;

    add_breadcrumb($page_title);

    $mybb->input['manage'] = $mybb->get_input('manage', MyBB::INPUT_INT);

    $confirm_contents = eval(templates_get('page_confirm_purchase'));

    $newpoints_content = eval(templates_get('page_confirm'));

    if ($newpoints_pagination) {
        $newpoints_pagination = eval(templates_get('page_pagination'));
    }

    $page_contents = eval(templates_get('page'));

    output_page($page_contents);

    exit;
}

function page_build_error(
    string $error_message,
    bool $no_permission = false,
    int $instance_id = INSTANCE_DEFAULT_ID
): never {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons, $newpoints_additional;

    language_load();

    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
        error($e->getMessage());
    }

    if (!$newpoints_menu) {
        $newpoints_file = $instance_object->get_script_file($instance_id);

        add_breadcrumb($lang->newpoints, $newpoints_file);

        url_handler_set($newpoints_file);

        $newpoints_menu = page_build_menu($instance_id);
    }

    $page_title = $table_title = $lang->newpoints_page_error_table_title;

    if ($no_permission) {
        $page_title = $table_title = $lang->newpoints_page_no_permission_error_table_title;
    }

    $button_text = $lang->newpoints_page_confirm_table_purchase_button;

    add_breadcrumb($page_title);

    $newpoints_content = eval(templates_get('page_error'));

    $page_contents = eval(templates_get('page'));

    output_page($page_contents);

    exit;
}

function user_get_forum_permissions(int $forum_id, int $user_id): array
{
    return forum_permissions($forum_id, $user_id);
}

function user_can_get_points(
    int $user_id,
    int $forum_id = 0,
    int $instance_id = INSTANCE_DEFAULT_ID
): bool {
    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
        return false;
    }

    $instance_object->set_user($user_id);

    $instance_object->set_forum($forum_id);

    return $instance_object->permission_check_boolean(Permissions::CanGetPoints);
}

function user_update(int $user_id, array $update_data): int
{
    global $db;

    return (int)$db->update_query(
        'users',
        $update_data,
        "uid='{$user_id}'",
        1
    );
}

function log_get(int $log_id, int $instance_id = INSTANCE_DEFAULT_ID): array
{
    global $db;

    $query = $db->simple_select(
        'newpoints_log',
        '*',
        "lid='{$log_id}' AND instance_id='{$instance_id}'",
        ['limit' => 1]
    );

    if (!$db->num_rows($query)) {
        return [];
    }

    return (array)$db->fetch_array($query);
}

function log_delete(int $log_id): bool
{
    global $db;

    $db->delete_query('newpoints_log', "lid='{$log_id}'");

    return true;
}

function my_alerts_initiate(): bool
{
    if (!function_exists('myalerts_info')) {
        return false;
    }

    global $newpoints_my_alerts_formatters;

    $newpoints_my_alerts_formatters = [
        0 => [
            'plugin_code' => 'core',
            'alert_types' => ['add_points', 'subtract_points'],
            'formatters_directory' => ROOT . '/alert_formatters/',
            'namespace' => '\NewPoints\MyAlerts\Formatters\\'
        ]
    ];

    $hook_arguments = [
        'newpoints_my_alerts_formatters' => &$newpoints_my_alerts_formatters,
    ];

    $hook_arguments = run_hooks('my_alerts_init', $hook_arguments);

    if (class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter')) {
        foreach ($newpoints_my_alerts_formatters as $formatter_key => &$formatter_data) {
            if (is_string($formatter_data['plugin_code']) && !empty($formatter_data['plugin_code'])) {
                $formatter_data['plugin_code'] = trim("{$formatter_data['plugin_code']}_");
            }

            if (empty($formatter_data['plugin_code'])) {
                unset($newpoints_my_alerts_formatters[$formatter_key]);

                continue;
            }

            if (file_exists($formatter_data['formatters_directory'])) {
                $formatters_directory_iterator = new DirectoryIterator($formatter_data['formatters_directory']);

                foreach ($formatters_directory_iterator as $formatter_file) {
                    if (!$formatter_file->isFile()) {
                        continue;
                    }

                    $path_name = $formatter_file->getPathname();

                    $path_info = pathinfo($path_name);

                    $file_name = str_replace([
                        "{$formatter_data['plugin_code']}",
                        '_formatter'
                    ], '', $path_info['filename']);

                    if ($path_info['extension'] === 'php' &&
                        in_array($file_name, $formatter_data['alert_types'], true)) {
                        require_once $path_name;
                    }
                }

                $formatter_data['alert_classes'] = [];

                foreach ($formatter_data['alert_types'] as $object_key => &$alert_type) {
                    $alert_class_name = "newpoints_{$formatter_data['plugin_code']}{$alert_type}_formatter";

                    if (isset($formatter_data['namespace'])) {
                        $alert_class_name = "{$formatter_data['namespace']}{$alert_class_name}";
                    }

                    if (!class_exists($alert_class_name)) {
                        unset($formatter_data['alert_types'][$object_key]);

                        continue;
                    }

                    $formatter_data['alert_classes'][$alert_type] = $alert_class_name;
                }

                if (empty($formatter_data['alert_types'])) {
                    unset($newpoints_my_alerts_formatters[$formatter_key]);
                }
            }
        }
    }

    if (!empty($newpoints_my_alerts_formatters) &&
        version_compare(myalerts_info()['version'], get_setting('my_alerts_version')) <= 0) {
        myalerts_register_client_alert_formatters();
    }

    return true;
}

function alert_send(
    int $user_id,
    int $object_id,
    string $plugin_code,
    string $alert_type,
    int $instance_id = INSTANCE_DEFAULT_ID
): bool {
    global $mybb;

    try {
        $instance_object = instance_object($instance_id);
    } catch (Exception $e) {
        return false;
    }

    if ($user_id === (int)$mybb->user['uid']) {
        return false;
    }

    if (!$instance_object->notifications_alert_enabled()) {
        return false;
    }

    if (!class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        return false;
    }

    global $alertType;

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode(
        "newpoints_{$plugin_code}_{$alert_type}"
    );

    if (empty($alertType) || !$alertType->getEnabled()) {
        return false;
    }

    /*global $db;

    $query = $db->simple_select(
        'alerts',
        'id',
        "object_id='{$object_id}' AND uid='{$user_id}' AND unread=1 AND alert_type_id='{$alertType->getId()}'"
    );

    if ($db->fetch_field($query, 'id')) {
        return false;
    }*/

    /**
     * Initialise a new Alert instance.
     *
     * @param int|array $user The ID of the user this alert is for.
     * @param int|MybbSTuff_MyAlerts_Entity_AlertType|string $type The ID of the object this alert is linked to.
     *                                                                 Optionally pass in an AlertType object or the
     *                                                                 short code name of the alert type.
     * @param int $objectId The ID of the object this alert is linked to. (eg: thread ID, post ID, etc.)
     */

    $alert = new MybbStuff_MyAlerts_Entity_Alert($user_id, $alertType, $object_id);

    $alert->setExtraDetails([
        'instance_id' => $instance_id,
    ]);

    $result = MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);

    return true;
}

function instance_insert(
    array $instance_data,
    bool $is_update = false,
    int $instance_id = 0
): int {
    global $db;

    $tables_data = TABLES_DATA['newpoints_instances'];

    $hook_arguments = [
        'insert_data' => &$insert_data,
        'instance_data' => &$instance_data,
        'is_update' => $is_update,
        'instance_id' => &$instance_id,
        'table_fields' => &$tables_data,
    ];

    $insert_data = [];

    foreach ($tables_data as $field_name => $field_definition) {
        if (isset($instance_data[$field_name])) {
            $insert_data[$field_name] = match ($field_definition['type']) {
                'INT', 'TINYINT', 'SMALLINT' => (int)$instance_data[$field_name],
                'FLOAT', 'DECIMAL' => (float)$instance_data[$field_name],
                default => $db->escape_string($instance_data[$field_name]),
            };
        }
    }

    global $db;

    $hook_arguments = run_hooks('instance_insert_update_end', $hook_arguments);

    if ($is_update) {
        $db->update_query('newpoints_instances', $insert_data, "instance_id='{$instance_id}'");
    } else {
        $instance_id = (int)$db->insert_query('newpoints_instances', $insert_data);
    }

    return $instance_id;
}

function instance_update(array $instance_data, int $instance_id): int
{
    return instance_insert($instance_data, true, $instance_id);
}

/**
 * @throws Exception
 */
function instance_object(int $instance_id): Instance
{
    static $instances_cache = [];

    if (!isset($instances_cache[$instance_id])) {
        require_once MYBB_ROOT . 'inc/plugins/newpoints/system/Instance.php';

        $instances_cache[$instance_id] = new Instance($instance_id);
    }

    return $instances_cache[$instance_id];
}

function instance_get(?int $instance_id = null, string|array $query_fields = [], bool $foo = false): array
{
    global $db;

    if ($query_fields === '*') {
        $fields = TABLES_DATA['newpoints_instances'];

        unset($fields['unique_key']);

        $query_fields = array_keys($fields);
    }

    $query_fields[] = 'instance_id';

    $query = $db->simple_select(
        'newpoints_instances',
        implode(',', $query_fields),
        $instance_id ? "instance_id='{$instance_id}'" : '',
        ['order_by' => 'display_order']
    );

    if ($instance_id !== null) {
        if (!$db->num_rows($query)) {
            throw new InvalidArgumentException(
                "Instance with ID {$instance_id} does not exist."
            );
        }

        return (array)$db->fetch_array($query);
    }

    $instance_objects = [];

    while ($instance_data = $db->fetch_array($query)) {
        $instance_data['instance_id'] = (int)$instance_data['instance_id'];

        $instance_objects[$instance_data['instance_id']] = $instance_data;
    }

    return $instance_objects;
}

function cache_update_instances(): array
{
    global $db, $cache;

    $instance_objects = [];

    // Query forum rules
    $query = $db->simple_select('newpoints_instances');

    foreach (instance_get(query_fields: '*') as $instance_id => $instance_data) {
        $instance_objects[$instance_id] = $instance_data;
    }

    $db->free_result($query);

    $cache->update('newpoints_instances', $instance_objects);

    return $instance_objects;
}

function cache_get_instances(?int $instance_id = null): array
{
    global $cache;

    $instance_objects = $cache->read('newpoints_instances');

    if (!$instance_objects) {
        $instance_objects = cache_update_instances();
    }

    if ($instance_id !== null) {
        return $instance_objects[$instance_id] ?? [];
    }

    return $instance_objects;
}

function build_income_table(Instance $instance_object, string $template_prefix = 'home'): string
{
    global $lang;

    $income_settings = '';

    $income_amount = $lang->sprintf(
        $lang->newpoints_income_amount,
        $instance_object->get_display_name_upper(),
        $instance_object->get_display_name_lower(),
    );

    $latest_transactions = [];

    $income_setting_params = [];

    foreach (get_income_types() as $income_type => $income_params) {
        $income_setting_params["newpoints_income_{$income_type}"] = [];

        foreach ($income_params as $param_key => $param_type) {
            switch ($param_type) {
                case 'numeric':
                    $income_setting_params["newpoints_income_{$income_type}"][$param_key] = my_number_format(
                        $instance_object->user_permissions["newpoints_income_{$param_key}"]
                    );
                    break;
            }
        }
    }

    foreach ($income_setting_params as $income_key => $income_setting) {
        $constant_name = my_strtoupper(str_replace('newpoints_income_', 'INCOME_TYPE_', $income_key));

        $income_value = $instance_object->get_income_value(constant('\Newpoints\Core\\' . $constant_name))
            * $instance_object->permission_get_rate_addition();

        if (empty($income_value)) {
            continue;
        }

        $setting['title'] = $lang->{"{$income_key}"};

        $setting['description'] = $lang->{"{$income_key}_desc"};

        $i = 1;

        foreach ($income_setting as $value) {
            $setting['description'] = str_replace("{{$i}}", $value, $setting['description']);

            ++$i;
        }

        $value = $instance_object->points_format($income_value);

        $income_settings .= eval(templates_get($template_prefix . '_income_row'));
    }

    $latest_transactions = implode(' ', $latest_transactions);

    return eval(templates_get($template_prefix . '_income_table'));
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com )
function control_object(&$obj, string $code): void
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
    function control_db(string $code): void
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
    function control_db(string $code): void
    {
        global $db;
        $oldLink = $db->db;
        unset($db->db);
        control_object($db, $code);
        $db->db = $oldLink;
    }
} else {
    function control_db(string $code): void
    {
        control_object($GLOBALS['db'], $code);
    }
}