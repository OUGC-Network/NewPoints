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

namespace NewPoints\Core;

use AbstractPdoDbDriver;
use DateTime;
use DB_SQLite;
use DirectoryIterator;
use Exception;
use InvalidArgumentException;
use JetBrains\PhpStorm\Deprecated;
use JetBrains\PhpStorm\NoReturn;
use Moderation;
use MyBB;
use MybbStuff_MyAlerts_AlertManager;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_Alert;
use NewPoints\System\Url;
use PluginLibrary;
use pluginSystem;
use postParser;
use ReflectionProperty;
use NewPoints\System\Instance;
use Twig\Environment;

use function MyBB\app;
use function MyBB\View\template;
use function NewPoints\Hooks\Forum\myalerts_register_client_alert_formatters;

use const NewPoints\ROOT;

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

            $plugins->add_hook($hook_name, $callable, (int)$priority);
        }
    }
}

function run_hooks(string $hook_name = '', array|Instance &$hook_arguments = []): array|Instance
{
    if (get_setting('main_disable_plugins')) {
        return $hook_arguments;
    }

    global $plugins;

    if ($plugins instanceof pluginSystem) {
        $hook_arguments = $plugins->run_hooks('newpoints_' . $hook_name, $hook_arguments);
    }

    if ($hook_arguments instanceof Instance) {
        return $hook_arguments;
    }

    return (array)$hook_arguments;
}

#[\Deprecated(message: 'use \NewPoints\System\Url instead', since: '4')]
function url_handler(string $new_url = ''): string
{
    static $setUrl = null;

    if ($setUrl === null) {
        $setUrl = main_file_name();
    }

    if (($new_url = trim($new_url))) {
        $setUrl = $new_url;
    }

    return $setUrl;
}

#[\Deprecated(message: 'use \NewPoints\System\Url->set_url() instead', since: '4')]
function url_handler_set(string $new_url): string
{
    return url_handler($new_url);
}

#[\Deprecated(message: 'use \NewPoints\System\Url->get_url() instead', since: '4')]
function url_handler_get(): string
{
    return url_handler();
}

#[\Deprecated(message: 'use \NewPoints\System\Url->build() instead', since: '4')]
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
    int $instance_id = 0,
): bool|string|int|float {
    global $mybb;

    static $setting_values = [];

    $settings_cache = $mybb->cache->read('newpoints_settings');

    if ($instance_id < 1) {
        return SETTINGS[$setting_key] ?? (
            SETTINGS['newpoints_' . $setting_key] ?? (
            $settings_cache['global']['newpoints_' . $setting_key] ?? false
        ));
    }

    if (isset($setting_values[$instance_id][$setting_key])) {
        return $setting_values[$instance_id][$setting_key];
    }

    if (isset(SETTINGS[$instance_id][$setting_key])) {
        $setting_values[$instance_id][$setting_key] = SETTINGS[$instance_id][$setting_key];
    } elseif (isset($settings_cache[$instance_id]['newpoints_' . $setting_key])) {
        $setting_values[$instance_id][$setting_key] = $settings_cache[$instance_id]['newpoints_' . $setting_key];
    } elseif (isset(SETTINGS[$setting_key])) {
        $setting_values[$instance_id][$setting_key] = SETTINGS[$setting_key];
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

function templates_get_twig(string $template_name = '', array $context = []): string
{
    /** @var Environment $twig */
    $twig = app(Environment::class);

    static $strict_variables_enabled = null;

    if ($strict_variables_enabled === null) {
        $strict_variables_enabled = $twig->isStrictVariables();
    }

    if (!$strict_variables_enabled) {
        $twig->enableStrictVariables();
    }

    $contents = template(
        '@ext.newpoints/' . $template_name . '.twig',
        $context
    );

    if (!$strict_variables_enabled) {
        $twig->disableStrictVariables();
    }

    return $contents;
}

/**
 * Adds a new set of templates
 *
 * @param string the key of the template plugin
 * @param array the array containing the templates data
 * @return bool false if something went wrong
 *
 */
function templates_rebuild(): void
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
        $PL->templates('newpoints', 'NewPoints', $templates_list);
    }
}

/**
 * Deletes settings from the database
 *
 * @param array $settings a list of settings seperated by ',' e.g. 'test','test_again','testing'
 * @param string $newpoints_prefix
 * @return bool false if something went wrong
 */
function settings_remove(array $settings, string $newpoints_prefix = 'newpoints_'): void
{
    if (!$settings) {
        return;
    }

    global $db;

    $settings = array_map([$db, 'escape_string'], $settings);

    $settings = implode("','" . $newpoints_prefix, $settings);

    $db->delete_query('newpoints_settings', "name IN ('{$newpoints_prefix}{$settings}')");
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
): void {
    global $db;

    if ($name == '' || $plugin == '' || $title == '' || $description == '' || $options_code == '') {
        return;
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
            "name='{$setting['name']}' AND plugin='{$setting['plugin']}' AND instance_id='{$instance_id}'"
        );

        if ($sid = $db->fetch_field($query, 'sid')) {
            unset($setting['value']);

            $db->update_query('newpoints_settings', $setting, "sid='{$sid}'");
        } else {
            $db->insert_query('newpoints_settings', $setting);
        }
    }
}

function settings_load(): void
{
    global $cache;

    $settings = $cache->read('newpoints_settings');

    global $mybb;

    if (!empty($settings)) {
        foreach ($settings as $name => $value) {
            //$mybb->settings[$name] = $value;
        }
    }

    foreach (SETTINGS as $name => $value) {
        //$mybb->settings["newpoints_{$name}"] = $value;
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

    $query = $db->simple_select('newpoints_settings', 'value, name, is_global, instance_id', '', $options);

    while ($setting = $db->fetch_array($query)) {
        if (empty($setting['is_global'])) {
            $settings[(int)$setting['instance_id']][$setting['name']] = $setting['value'];
        } else {
            unset($setting['instance_id']);

            $settings['global'][$setting['name']] = $setting['value'];
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
function settings_rebuild(): void
{
    global $lang;
    global $PL;

    language_load();

    $PL || require_once PLUGINLIBRARY;

    $global_settings_files = [ROOT . '/settings.json'];

    $settings_directories = [
        ROOT . '/settings'
    ];

    $global_settings_list = $settings_list = [];

    $hook_arguments = [
        'global_settings_files' => &$global_settings_files,
        'global_settings_list' => &$global_settings_list,
        'settings_directories' => &$settings_directories,
        'settings_list' => &$settings_list,
    ];

    $hook_arguments = run_hooks('settings_rebuild_start', $hook_arguments);

    /*
    foreach ($global_settings_files as $global_settings_file) {
        $settings_contents = file_get_contents($global_settings_file);

        foreach ((json_decode($settings_contents, true) ?? []) as $setting_key => $setting_data) {
            if (empty($lang->{"setting_newpoints_{$setting_key}"})) {
                continue;
            }

            if ($setting_data['optionscode'] == 'select' || $setting_data['optionscode'] == 'checkbox') {
                foreach ($setting_data['options'] as $option_key) {
                    $setting_data['optionscode'] .= "\n{$option_key}={$lang->{"setting_newpoints_{$setting_key}_{$option_key}"}}";
                }
            }

            $setting_data['title'] = $lang->{"setting_newpoints_{$setting_key}"};

            $setting_data['description'] = $lang->{"setting_newpoints_{$setting_key}_desc"};

            $global_settings_list[$setting_key] = $setting_data;
        }
    }

    if ($global_settings_list) {
        $PL->settings(
            'newpoints',
            $lang->setting_group_newpoints,
            $lang->setting_group_newpoints_desc,
            $global_settings_list
        );
    }*/

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

                    if (isset($setting_data['instance'])) {
                        $setting_data['is_global'] = 0;
                    } else {
                        $setting_data['is_global'] = 1;
                    }
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
                    $lang->{"setting_group_newpoints_{$setting_group}"} ?? '',
                    $lang->{"setting_group_newpoints_{$setting_group}_desc"} ?? '',
                    $settings_data,
                    $instance_id,
                );
            }
        }
    }

    settings_rebuild_cache();
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
#[\Deprecated(message: 'use points_addittion() instead', since: '4')]
function points_add(
    int $user_id,
    float $points,
    float $forum_rate = 1,
    float $group_rate = 1,
    bool $is_string = false,
    bool $immediate = false,
): bool {
    try {
        $instance = instance_object(INSTANCE_DEFAULT_ID, $user_id);
    } catch (Exception $e) {
        log_error(
            INSTANCE_DEFAULT_ID,
            $e->getMessage(),
            user_id: $user_id
        );

        return false;
    }

    global $db, $newpoints_shutdown_cache;

    isset($newpoints_shutdown_cache) || $newpoints_shutdown_cache = [];

    if ($points == 0 || ($user_id <= 0 && !$is_string)) {
        return false;
    }

    if ($is_string === true) {
        $immediate = true;
    }

    $points_rounded = round(
        $points * $forum_rate * $group_rate,
        (int)$instance->get_instance_data()['decimal_digits']
    );

    $instance_column_name = $instance->users_column_get();

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
        if (!isset($newpoints_shutdown_cache[$instance->users_column_get()][$user_id])) {
            $newpoints_shutdown_cache[$instance->users_column_get()][$user_id] = 0;
        }

        $newpoints_shutdown_cache[$instance->users_column_get()][$user_id] += $points_rounded;
    }

    static $newpoints_shutdown = false;

    if (!$newpoints_shutdown) {
        $newpoints_shutdown = true;

        add_shutdown('newpoints_update_addpoints');
    }

    return true;
}

#[\Deprecated(message: 'use points_subtraction() instead', since: '4')]
function points_subtract(
    int $user_id,
    float $points,
): bool {
    return points_add($user_id, -abs($points), 1, 1, false, true);
}

#[\Deprecated(message: 'use \NewPoints\System\Instance->points_add() instead', since: '4')]
function points_add_simple(
    int $user_id,
    float $points,
    #[Deprecated]
    int $forum_id = 0,
): bool {
    try {
        instance_object(INSTANCE_DEFAULT_ID, $user_id)
            ->set_forum($forum_id)
            ->points_addition($points);

        return true;
    } catch (Exception $e) {
        log_error(
            INSTANCE_DEFAULT_ID,
            $e->getMessage(),
            user_id: $user_id
        );

        return false;
    }
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
#[\Deprecated(message: 'use \NewPoints\System\Instance->points_format() instead', since: '4')]
function points_format(float $points): string
{
    try {
        return instance_object(INSTANCE_DEFAULT_ID)->points_format($points);
    } catch (Exception $e) {
        log_error(
            INSTANCE_DEFAULT_ID,
            $e->getMessage(),
        );

        return '';
    }
}

/**
 * Get rules of a certain group or forum
 *
 * @param string $type the type of rule: 'forum' or 'group'
 * @param int $rule_id the id of the group or forum
 * @return array false if something went wrong
 */
#[\Deprecated(since: '3')]
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
        //throw new InvalidArgumentException('Invalid rule identifier');
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

#[\Deprecated(since: '3')]
function rules_forum_get(int $forum_id): array
{
    return rules_get(RULE_TYPE_FORUM, $forum_id);
}

#[\Deprecated(since: '3')]
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
#[\Deprecated(since: '3')]
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

#[\Deprecated(since: '3')]
function rules_forum_get_rate(int $forum_id): float
{
    $forum_data = get_forum($forum_id);

    return isset($forum_data[Permissions::Rate]) ? (float)$forum_data[Permissions::Rate] : 1;
}

#[\Deprecated(since: '3')]
function rate_group_get(int $group_id): float
{
    $group_rules = rules_group_get($group_id);

    return isset($group_rules['rate']) ? (float)$group_rules['rate'] : 1;
}

#[\Deprecated(since: '3')]
function rules_get_group_rate(
    array $user = [],
    string $rate_key = IncomeRates::RateAddition,
): float {
    global $mybb;

    $group_rate = 1;

    if (empty($user)) {
        $user = $mybb->user;
    }

    $rate_values = [];

    $user_groups = (string)$user['usergroup'];

    // this feature is deprecated so switching to settings_get_value() is not necessary
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

#[\Deprecated(since: '3')]
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
    bool $admin_override = false,
    int $instance_id = INSTANCE_DEFAULT_ID
): bool {
    try {
        $instance = instance_object($instance_id, (int)($private_message_data['touid'] ?? 0));
    } catch (InvalidArgumentException $e) {
        log_error($instance_id, $e->getMessage());

        return false;
    }

    if (!$instance->notifications_private_message_enabled()) {
        return false;
    }

    global $session;

    $private_message_data['ipaddress'] = $private_message_data['ipaddress'] ?? $session->packedip;

    return send_pm($private_message_data, $from_user_id, $admin_override);
}

#[\Deprecated(since: '3')]
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

#[\Deprecated(since: '4')]
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
#[\Deprecated(message: 'use \NewPoints\System\Logger instead', since: '4')]
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
    try {
        return instance_object(INSTANCE_DEFAULT_ID, $user_id)->logger->log_action(
            $log_action,
            $log_points,
            $primary_id,
            $secondary_id,
            $tertiary_id,
            $log_type,
            $user_id,
        )
            ->get_log_id();
    } catch (Exception $e) {
        log_error(
            INSTANCE_DEFAULT_ID,
            $e->getMessage(),
            user_id: $user_id
        );

        return 0;
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
#[\Deprecated(message: 'use \NewPoints\System\Instance instead', since: '4')]
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

    try {
        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                if (empty($mybb->user[$instance->users_column_get()])) {
                    $mybb->user[$instance->users_column_get()] = 0;
                } else {
                    $mybb->user[$instance->users_column_get()] = (float)$mybb->user[$instance->users_column_get()];
                }
            } catch (Exception $e) {
                log_error($instance_id, $e->getMessage());

                continue;
            }
        }
    } catch (Exception $exception) {
    }
}

function plugins_load(): void
{
    if (get_setting('main_disable_plugins')) {
        return;
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
}

// Updates users' points by user group
function users_update(int $instance_id = INSTANCE_DEFAULT_ID): bool
{
    try {
        $instance = instance_object($instance_id);
    } catch (Exception $e) {
        log_error($instance_id, $e->getMessage());

        return false;
    }

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
            [$instance->users_column_get() => "`{$instance->users_column_get()}`+'{$amount}'"],
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
        return instance_object($instance_id, $user_id)->user_permissions;
    } catch (Exception $e) {
        log_error(
            $instance_id,
            $e->getMessage(),
            user_id: $user_id,
        );

        return [];
    }
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
 * @param int $instance_id
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
        "plugin='{$group_name}' AND (is_global='1' OR (is_global='0' AND instance_id='{$instance_id}'))"
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
                'is_global' => 1,
                'instance_id' => 0,
            ]
        );

        $setting = array_map([$db, 'escape_string'], (array)$setting);

        isset($display_order) || $display_order = 0;

        $setting = array_merge(
            [
                'title' => $title,
                'description' => $description,
                'type' => 'yesno',
                'value' => 0,
                'disporder' => ++$display_order,
                'instance_id' => $instance_id,
            ],
            $setting
        );

        if (empty($setting['is_global'])) {
            $where_clause = "(is_global='0' AND instance_id='{$instance_id}')";

            $setting['is_global'] = 0;
        } else {
            $where_clause = "is_global='1'";

            $setting['is_global'] = 1;

            unset($setting['instance_id']);
        }

        $setting['name'] = $db->escape_string($key);

        $setting['plugin'] = $group_name;

        $query = $db->simple_select(
            'newpoints_settings',
            'sid',
            "plugin='{$group_name}' AND name='{$setting['name']}' AND {$where_clause}"
        );

        if ($sid = (int)$db->fetch_field($query, 'sid')) {
            unset($setting['value']);

            $db->update_query('newpoints_settings', $setting, "sid='{$sid}'");
        } else {
            $db->insert_query('newpoints_settings', $setting);
        }

        unset($setting);
    }

    // Delete deprecated entries.
    $db->delete_query(
        'newpoints_settings',
        "plugin='{$group_name}' AND description='NEWPOINTSDELETEMARKER'"
    );

    // Rebuild the settings file.
    settings_rebuild_cache();
}

#[\Deprecated(since: '4')]
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
    int $action = TASK_ENABLE,
    array $options = [],
): bool|int {
    global $db;

    language_load();

    if ($action === TASK_DELETE) {
        try {
            $db->delete_query('tasks', "file='{$plugin_code}'");

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    $db_query = $db->simple_select('tasks', '*', "file='{$plugin_code}'", ['limit' => 1]);

    if ($db->num_rows($db_query)) {
        try {
            $db->update_query('tasks', ['enabled' => $action], "file='{$plugin_code}'");

            return true;
        } catch (Exception $e) {
        }
    } else {
        include_once MYBB_ROOT . 'inc/functions_task.php';

        $new_task_data = array_merge([
            'minute' => 0,
            'hour' => 0,
            'day' => '*',
            'weekday' => 0,
            'month' => '*',
            'logging' => 1
        ], $options, [
            'title' => $title,
            'description' => $description,
            'file' => $plugin_code,
            'enabled' => 1,
        ]);

        $new_task_data = array_map([$db, 'escape_string'], $new_task_data);

        $new_task_data['nextrun'] = fetch_next_run($new_task_data);

        try {
            return (int)$db->insert_query('tasks', $new_task_data);
        } catch (Exception $e) {
        }
    }

    return false;
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

function page_build_menu_options(): array|string
{
    static $menu = null;

    if ($menu !== null) {
        return $menu;
    }

    global $mybb, $lang, $theme;

    if ($mybb->version_code >= 1900) {
        $menu = [];
    } else {
        $menu = '';
    }


    $menu_items = [
        /*0 => [
            'lang_string' => 'newpoints_home',
            'category' => 'main'
        ]*/
    ];

    $can_see_stats = $can_donate = false;

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            if ($instance->user_permissions[Permissions::CanSeeStats]) {
                $can_see_stats = true;
            }

            if ($instance->user_permissions[Permissions::CanDonate]) {
                $can_donate = true;
            }

            unset($instance);
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

    if ($can_see_stats) {
        $menu_items[] = [
            'action' => 'stats',
            'lang_string' => 'newpoints_statistics',
            'category' => 'main',
            'display_order' => get_setting('stats_menu_order'),
            'icon' => 'chart-pie',
        ];
    }

    if ($can_donate) {
        $menu_items[] = [
            'action' => 'donate',
            'lang_string' => 'newpoints_donate',
            'category' => 'user',
            'display_order' => get_setting('donations_menu_order'),
            'icon' => 'share',
        ];
    }

    $menu_items = run_hooks('default_menu', $menu_items);

    $menu_items[] = [
        'action' => 'logs',
        'lang_string' => 'newpoints_logs_menu_title',
        'category' => 'user',
        'display_order' => get_setting('logs_menu_order'),
        'icon' => 'cogs',
    ];

    //$menu_items = array_merge($menu_items, $instance->get_menu_items());

    usort(
        $menu_items,
        fn($a, $b) => ($a['display_order'] ?? 0) <=> ($b['display_order'] ?? 0)
    );

    usort(
        $menu_items,
        fn($a, $b) => strcmp($a['category'] ?? '', $b['category'] ?? '')
    );

    $options_list = [];

    foreach ($menu_items as $option) {
        $options_list[$option['category'] ?? 'market'][] = $option;
    }

    //ksort($options_list);

    global $collapse, $collapsed, $collapsedimg;

    $url = new Url();

    foreach ($options_list as $category_key => $menu_items) {
        $collapsed_name = "category_{$category_key}";

        $collapsedimg[$collapsed_name] = $collapsedimg[$collapsed_name] ?? '';

        $collapsed_image = $collapsedimg[$collapsed_name];

        $collapsed["{$collapsed_name}_e"] = $collapsed["{$collapsed_name}_e"] ?? '';

        $expanded_display = $collapsed["{$collapsed_name}_e"];

        $expanded_alternative_text = !empty($collapsed["{$collapsed_name}_e"]) ? $lang->expcol_expand : $lang->expcol_collapse;

        $alternative_background = alt_trow(true);

        if ($mybb->version_code >= 1900) {
            $options = [];
        } else {
            $options = '';
        }

        foreach ($menu_items as $option) {
            if (isset($option['setting']) && !get_setting($option['setting'])) {
                continue;
            }

            $action_url = $item_selected = $option_name = '';

            if (isset($option['action'])) {
                $action_url = $url->build(['action' => $option['action']]);

                if (my_strtolower($mybb->get_input('action')) === my_strtolower($option['action'])) {
                    $item_selected = eval(templates_get('option_selected'));
                }
            } else {
                $action_url = $url->build();
            }

            $option_name = '';

            if (isset($option['lang_string'])) {
                $option_name = $lang->{$option['lang_string']};
            } elseif (isset($option['action'])) {
                $option_name = ucwords((string)$option['action']);
            }

            if (is_scalar($option)) {
                if (!$option_name) {
                    $option_name = $option;
                }

                $option = (array)$option;
            }

            $option['url'] = $action_url;

            $option = run_hooks('menu_build_option', $option);

            if ($mybb->version_code >= 1900) {
                $options[$option['action']] = $option;
            } else {
                $options .= eval(templates_get('option'));
            }

            $alternative_background = alt_trow();
        }

        $menu_category_title = 'newpoints_menu_category_' . $category_key;

        $menu_category_title = $lang->{$menu_category_title};

        if ($mybb->version_code >= 1900) {
            $menu[$category_key] = $options;
        } else {
            $menu .= eval(templates_get('menu_category'));
        }
    }

    return $menu;
}

function page_build_menu(): string
{
    global $mybb, $lang, $theme;
    global $newpoints_file;

    $menu_options = page_build_menu_options();

    if ($mybb->version_code >= 1900) {
        return template(
            '@ext.newpoints/menu.twig',
            [
                'categories' => $menu_options,
                'url_main' => $newpoints_file,
            ]
        );
    } else {
        return eval(templates_get('menu'));
    }
}

function main_file_name(): string
{
    return (string)get_setting('main_script_name');
}

function get_income_types(): array
{
    $income_types = INCOME_TYPES;

    $income_types = run_hooks('income_types', $income_types);

    foreach ($income_types as $income_type => $income_params) {
        if (!defined('\NewPoints\Core\INCOME_TYPE_' . my_strtoupper($income_type))) {
            //_dump('INCOME_TYPE_' . strtoupper($income_type), $income_type);
            define('NewPoints\Core\INCOME_TYPE_' . my_strtoupper($income_type), my_strtolower($income_type));
        }
    }

    return $income_types;
}

#[\Deprecated(message: 'use \NewPoints\System\Instance instead', since: '4')]
function get_income_value(
    string $income_type,
    int $user_id = 0,
    int $forum_id = 0,
): float {
    try {
        return instance_object(INSTANCE_DEFAULT_ID, $user_id)
            ->set_forum($forum_id)
            ->get_income_value($income_type);
    } catch (Exception $e) {
        log_error(
            INSTANCE_DEFAULT_ID,
            $e->getMessage(),
            user_id: $user_id,
            forum_id: $forum_id
        );

        return 0;
    }
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

#[NoReturn] function page_build_cancel_confirmation(
    string $form_input_name,
    int $form_input_value,
    string $table_text,
    string $form_view_name,
): void {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons, $newpoints_additional;
    global $instance_objects;

    if (!$instance_objects = instance_get_enabled()) {
        error_no_permission();
    }

    isset($newpoints_file) || $newpoints_file = main_file_name();

    isset($newpoints_menu) || $newpoints_menu = page_build_menu();

    $newpoints_errors || $newpoints_errors = '';

    $newpoints_content || $newpoints_content = '';

    $action_name || $action_name = '';

    $newpoints_pagination || $newpoints_pagination = '';

    $newpoints_buttons || $newpoints_buttons = '';

    $newpoints_additional || $newpoints_additional = '';

    $page_title = $table_title = $lang->newpoints_page_confirm_table_cancel_title;

    $button_text = $lang->newpoints_page_confirm_table_cancel_button;

    global $navbits;

    $navbits = [
        0 => [
            'name' => $mybb->settings['bbname_orig'],
            'url' => $mybb->settings['bburl'] . '/index.php'
        ]
    ];

    add_breadcrumb($page_title);

    $mybb->input['manage'] = $mybb->get_input('manage', MyBB::INPUT_INT);

    $confirm_contents = eval(templates_get('page_confirm_cancel'));

    $newpoints_content = eval(templates_get('page_confirm'));

    $page_contents = eval(templates_get('page'));

    output_page($page_contents);

    exit;
}

#[NoReturn] function page_build_purchase_confirmation(
    string $table_description,
    string $form_input_name,
    int $form_input_value,
    string $form_view_name = '',
    string $extra_rows = '',
): void {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons, $newpoints_additional;
    global $instance_objects;

    if (!$instance_objects = instance_get_enabled()) {
        error_no_permission();
    }

    language_load();

    isset($newpoints_file) || $newpoints_file = main_file_name();

    isset($newpoints_menu) || $newpoints_menu = page_build_menu();

    $newpoints_errors || $newpoints_errors = '';

    $newpoints_content || $newpoints_content = '';

    $action_name || $action_name = '';

    $newpoints_pagination || $newpoints_pagination = '';

    $newpoints_buttons || $newpoints_buttons = '';

    $newpoints_additional || $newpoints_additional = '';

    $page_title = $table_title = $lang->newpoints_page_confirm_table_purchase_title;

    $button_text = $lang->newpoints_page_confirm_table_purchase_button;

    global $navbits;

    $navbits = [
        0 => [
            'name' => $mybb->settings['bbname_orig'],
            'url' => $mybb->settings['bburl'] . '/index.php'
        ]
    ];

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
): void {
    global $mybb, $lang;
    global $headerinclude, $header, $footer, $theme;
    global $newpoints_file, $newpoints_menu, $newpoints_errors, $newpoints_content, $action_name, $newpoints_pagination, $newpoints_buttons, $newpoints_additional;

    language_load();

    try {
        $instance = instance_object($instance_id);
    } catch (Exception $e) {
        log_error($instance_id, $e->getMessage());

        error($e->getMessage());

        exit;
    }

    global $navbits;

    $navbits = [
        0 => [
            'name' => $mybb->settings['bbname_orig'],
            'url' => $mybb->settings['bburl'] . '/index.php'
        ]
    ];

    if (!$newpoints_menu) {
        $newpoints_file = main_file_name($instance_id);

        add_breadcrumb($lang->newpoints, $newpoints_file);

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

#[\Deprecated(message: 'use \NewPoints\System\Instance instead', since: '4')]
function user_get_forum_permissions(
    int $forum_id,
    int $user_id,
    int $instance_id = INSTANCE_DEFAULT_ID
): array {
    try {
        return instance_object($instance_id, $user_id)->get_forum_permissions($forum_id);
    } catch (Exception $e) {
        log_error(
            $instance_id,
            $e->getMessage(),
            user_id: $user_id,
            forum_id: $forum_id,
        );

        return [];
    }
}

#[\Deprecated(message: 'use \NewPoints\System\Instance instead', since: '4')]
function user_can_get_points(
    int $user_id,
    int $forum_id = 0,
): bool {
    try {
        return instance_object(INSTANCE_DEFAULT_ID, $user_id)
            ->set_forum($forum_id)
            ->get_user_permission_boolean(Permissions::CanGetPoints);
    } catch (Exception $e) {
        return false;
    }
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

function log_get(int $log_id, array $where_clauses = [], array $query_fields = []): array
{
    global $db;

    $where_clauses[] = "lid='{$log_id}'";

    $query_fields[] = 'lid';

    $query = $db->simple_select(
        'newpoints_log',
        implode(',', $query_fields),
        implode(' AND ', $where_clauses),
        ['limit' => 1]
    );

    if (!$db->num_rows($query)) {
        return [];
    }

    return (array)$db->fetch_array($query);
}

function log_delete(int $log_id, array $where_clauses = []): bool
{
    global $db;

    $where_clauses[] = "lid='{$log_id}'";

    $db->delete_query('newpoints_log', implode(' AND ', $where_clauses));

    return true;
}

function log_error(
    int $instance_id,
    string $error_message,
    int $user_id = 0,
    int $post_id = 0,
    int $thread_id = 0,
    int $forum_id = 0,
    int $income_type = 0,
    int $primary_id = 0,
    int $secondary_id = 0,
    int $tertiary_id = 0,
): void {
    if (!$user_id) {
        global $mybb;

        $user_id = (int)$mybb->user['uid'];
    }

    global $db;

    try {
        $db->insert_query('newpoints_error_log', [
            'instance_id' => $instance_id,
            'error_message' => $db->escape_string($error_message),
            'user_id' => $user_id,
            'post_id' => $post_id,
            'thread_id' => $thread_id,
            'forum_id' => $forum_id,
            'income_type' => $income_type,
            'log_primary_id' => $primary_id,
            'log_secondary_id' => $secondary_id,
            'log_tertiary_id' => $tertiary_id,
            'dateline' => TIME_NOW,
        ]);
    } catch (Exception) {
    }
}

function my_alerts_initiate(): void
{
    if (!function_exists('myalerts_info')) {
        return;
    }

    global $newpoints_my_alerts_formatters;

    $newpoints_my_alerts_formatters = [
        0 => [
            'plugin_code' => 'core',
            'alert_types' => ['add_points', 'subtract_points', 'donation_received'],
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

                    $file_name = str_replace('_formatter', '', $path_info['filename']);

                    $position = my_strpos($path_info['filename'], $formatter_data['plugin_code']);

                    if ($position !== false) {
                        $file_name = substr_replace(
                            $file_name,
                            '',
                            $position,
                            strlen($formatter_data['plugin_code'])
                        );
                    }

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
        $instance = instance_object($instance_id, $user_id);
    } catch (InvalidArgumentException $e) {
        log_error($instance_id, $e->getMessage());

        return false;
    }

    if (!$instance->notifications_alert_enabled()) {
        return false;
    }

    if ($instance->get_user_id() === (int)$mybb->user['uid']) {
        //return false;
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
        "object_id='{$object_id}' AND uid='{$instance->get_user_id()}' AND unread=1 AND alert_type_id='{$alertType->getId()}'"
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

    $alert = new MybbStuff_MyAlerts_Entity_Alert($instance->get_user_id(), $alertType, $object_id);

    $alert->setExtraDetails([
        'instance_id' => $instance->instance_id,
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
        'table_data' => &$tables_data,
    ];

    $insert_data = [];

    foreach ($tables_data as $field_name => $field_definition) {
        if (isset($instance_data[$field_name])) {
            $insert_data[$field_name] = match ($field_definition['type']) {
                'BIGINT', 'INT', 'SMALLINT', 'TINYINT' => (int)$instance_data[$field_name],
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
function instance_object(int $instance_id, int $user_id = 0): Instance
{
    static $instances_cache = [];

    if (!isset($instances_cache[$instance_id][$user_id])) {
        $instances_cache[$instance_id][$user_id] = new Instance($instance_id, $user_id);

        run_hooks('instance_object_init', $instances_cache[$instance_id][$user_id]);
    }

    return $instances_cache[$instance_id][$user_id];
}

function instance_get(?int $instance_id = null, string|array $query_fields = []): array
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
            return [];
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

function instance_get_enabled(): array
{
    global $instance_objects;

    if (!empty($instance_objects)) {
        return $instance_objects;
    }

    $instance_objects = [];

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            $instance_objects[$instance_id] = instance_object($instance_id);

            if (!$instance_objects[$instance_id]->is_enabled()) {
                unset($instance_objects[$instance_id]);

                continue;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
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
        if (empty($instance_data['is_enabled'])) {
            continue;
        }

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

function build_income_table(Instance $instance, string $template_prefix = 'home'): array|string
{
    global $mybb, $lang;

    if ($mybb->version_code >= 1900) {
        $income_settings = [];
    } else {
        $income_settings = '';
    }

    $income_amount = $lang->sprintf(
        $lang->newpoints_income_amount,
        $instance->get_display_name_upper(),
        $instance->get_display_name_lower(),
    );

    $income_setting_params = [];

    $income_types = get_income_types();

    foreach ($income_types as $income_type => $income_params) {
        if ($instance->get_forum_id() &&
            isset($income_params['show_in_forum']) &&
            empty($income_params['show_in_forum'])) {
            continue;
        }

        $income_setting_params["newpoints_income_{$income_type}"] = [];

        foreach ($income_params as $param_key => $param_type) {
            if (is_callable($param_type) && $param_key !== 'format_closure') {
                $income_setting_params["newpoints_income_{$income_type}"][$param_key] = $param_type(
                    $instance->user_permissions["newpoints_income_{$param_key}"] ?? $instance->user_permissions[$param_key],
                );

                continue;
            }

            switch ($param_type) {
                case 'numeric':
                    $income_setting_params["newpoints_income_{$income_type}"][$param_key] = my_number_format(
                        $instance->user_permissions["newpoints_income_{$param_key}"] ?? $instance->user_permissions[$param_key],
                    );
                    break;
                case 'points':
                    $income_setting_params["newpoints_income_{$income_type}"][$param_key] = $instance->points_format(
                        $instance->user_permissions["newpoints_income_{$param_key}"] ?? $instance->user_permissions[$param_key],
                    );
                    break;
            }
        }
    }

    foreach ($income_setting_params as $income_key => $income_setting) {
        $constant_name = my_strtoupper(str_replace('newpoints_income_', 'INCOME_TYPE_', $income_key));

        $income_value = $instance->get_income_value(constant('\NewPoints\Core\\' . $constant_name));

        if (empty($income_value)) {
            continue;
        }

        $income_title = $lang->{"{$income_key}"};

        $income_description = $lang->{"{$income_key}_desc"};

        if ($income_description && is_array($income_setting)) {
            $i = 1;

            foreach ($income_setting as $value) {
                $income_description = str_replace("{{$i}}", $value, $income_description);

                ++$i;
            }
        }

        $permission_key = str_replace('newpoints_income_', '', $income_key);

        if (!empty($income_types[$permission_key]['format_closure']) &&
            is_callable($income_types[$permission_key]['format_closure'])) {
            $value = $income_types[$permission_key]['format_closure']($income_value);
        } else {
            $value = $instance->points_format($income_value);
        }

        if ($mybb->version_code >= 1900) {
            $income_settings[$income_key] = $value;
        } else {
            $income_settings .= eval(templates_get($template_prefix . '_income_row'));
        }
    }

    if ($mybb->version_code >= 1900) {
        return $income_settings;
    } else {
        return eval(templates_get($template_prefix . '_income_table'));
    }
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

function forum_rule_view_lock(int $forum_id): void
{
    $forum_data = get_forum($forum_id);

    $minimum_points = (float)$forum_data['newpoints_view_lock_points'];

    if (!($minimum_points > 0)) {
        return;
    }

    global $lang;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if ($minimum_points > $instance->get_user_column_value()) {
                language_load();

                error(
                    $lang->sprintf(
                        $lang->newpoints_not_enough_points,
                        $instance->points_format($minimum_points)
                    )
                );
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }
}

function forum_rule_post_lock(int $forum_id): void
{
    $forum_data = get_forum($forum_id);

    $minimum_points = (float)$forum_data['newpoints_post_lock_points'];

    if (!($minimum_points > 0)) {
        return;
    }

    global $lang;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if ($minimum_points > $instance->get_user_column_value()) {
                language_load();

                error(
                    $lang->sprintf(
                        $lang->newpoints_not_enough_points,
                        $instance->points_format($minimum_points)
                    )
                );
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }
}

function build_instances_select(
    string $select_name = 'instance_id',
    bool $is_multiple = false,
    bool $show_blank = true,
    array $filter = [],
    array $filter_instances = [],
): string {
    global $mybb, $lang, $instance_objects;

    $instance_objects = $instance_objects ?: instance_get_enabled();

    if ($mybb->version_code >= 1900) {
        $select_options = [];
    } else {
        $select_options = '';
    }

    if ($show_blank) {
        if ($mybb->version_code >= 1900) {
            $select_options[] = [
                'name' => '',
                'value' => '',
                'is_selected' => '',
            ];
        } else {
            $option_value = $selected_element = $option_name = '';

            $select_options .= eval(templates_get('input_select_option'));
        }
    }

    $select_multiple = $is_multiple ? 'multiple="multiple"' : '';

    foreach ($instance_objects as $option_value => $instance) {
        if ($filter_instances && !in_array($option_value, $filter_instances)) {
            continue;
        }

        $option_name = $instance->get_display_name_upper();

        $is_selected = false;

        if (!empty($mybb->input['instance_id']) &&
            $option_value === $mybb->get_input('instance_id', MyBB::INPUT_INT) ||
            !empty($filter['instances']) && in_array($option_value, $filter['instances'])) {
            $is_selected = true;
        }

        if ($mybb->version_code >= 1900) {
            $select_options[] = [
                'name' => $option_name,
                'value' => $option_value,
                'is_selected' => $is_selected,
            ];
        } else {
            $selected_element = '';

            if ($is_selected) {
                $selected_element = 'selected="selected"';
            }

            $select_options .= eval(templates_get('input_select_option'));
        }
    }

    if ($mybb->version_code >= 1900) {
        return templates_get_twig('input_select', [
            'name' => $select_name,
            'is_multiple' => $select_multiple,
            'options' => $select_options,
        ]);
    } else {
        return eval(templates_get('input_select'));
    }
}