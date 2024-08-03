<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/plugins.php)
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

use function Newpoints\Admin\db_verify_columns;
use function Newpoints\Admin\db_verify_tables;
use function Newpoints\Admin\plugin_library_load;
use function Newpoints\Admin\task_enable;
use function Newpoints\Core\language_load;
use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\settings_rebuild;
use function Newpoints\Core\settings_rebuild_cache;
use function Newpoints\Core\templates_rebuild;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\url_handler_get;
use function Newpoints\Core\url_handler_set;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb, $cache;

language_load();

$lang->load('config_plugins', false, true);

url_handler_set('index.php');

url_handler_set(url_handler_build([
    'module' => 'newpoints-plugins'
]));

// Activates or deactivates a specific plugin
if ($mybb->get_input('action') == 'activate' || $mybb->get_input('action') == 'deactivate') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        flash_message($lang->invalid_post_verify_key2, 'error');
        admin_redirect(url_handler_get());
    }

    if ($mybb->get_input('action') == 'activate') {
        run_hooks('admin_plugins_activate');
    } else {
        run_hooks('admin_plugins_deactivate');
    }

    if ($mybb->get_input('no')) {
        admin_redirect(url_handler_get());
    }

    if ($mybb->request_method !== 'post') {
        $process_url = url_handler_build([
            'action' => $mybb->get_input('action'),
            'uninstall' => $mybb->get_input('uninstall', MyBB::INPUT_INT),
            'plugin' => $mybb->get_input('plugin'),
            'my_post_key' => $mybb->get_input('my_post_key'),
        ]);

        if ($mybb->get_input('action') == 'activate') {
            $message = $lang->newpoints_confirmation_plugin_activation;
        } elseif ($mybb->get_input('uninstall', MyBB::INPUT_INT)) {
            $message = $lang->newpoints_confirmation_plugin_uninstallation;
        } else {
            $message = $lang->newpoints_confirmation_plugin_deactivation;
        }

        $page->output_confirm_action($process_url, $message);
    }

    $codename = $mybb->get_input('plugin');
    $codename = str_replace(['.', '/', "\\"], '', $codename);
    $file = basename($codename);

    $plugin_file_path = MYBB_ROOT . "inc/plugins/newpoints/plugins/{$file}.php";

    // Check if the file exists and throw an error if it doesn't
    if (!file_exists($plugin_file_path)) {
        flash_message($lang->error_invalid_plugin, 'error');
        admin_redirect(url_handler_get());
    }

    $plugins_cache = $cache->read('newpoints_plugins');
    $active_plugins = $plugins_cache['active'];

    require_once $plugin_file_path;

    $installed_func = "{$codename}_is_installed";

    $installed = true;

    if (function_exists($installed_func) && !$installed_func()) {
        $installed = false;
    }

    $install_uninstall = false;

    if ($mybb->get_input('action') == 'activate') {
        $message = $lang->success_plugin_activated;

        // Plugin is compatible with this version?

        if (!newpoints_iscompatible($codename)) {
            flash_message($lang->sprintf($lang->newpoints_plugin_incompatible, NEWPOINTS_VERSION), 'error');
            admin_redirect(url_handler_get());
        }

        // If not installed and there is a custom installation function
        if ($installed == false && function_exists("{$codename}_install")) {
            call_user_func("{$codename}_install");
            $message = $lang->success_plugin_installed;
            $install_uninstall = true;
        }

        if (function_exists("{$codename}_activate")) {
            call_user_func("{$codename}_activate");
        }

        $active_plugins[$codename] = $codename;
        $executed[] = 'activate';
    } elseif ($mybb->get_input('action') == 'deactivate') {
        $message = $lang->success_plugin_deactivated;

        if (function_exists("{$codename}_deactivate")) {
            call_user_func("{$codename}_deactivate");
        }

        if ($mybb->get_input('uninstall') == 1 && function_exists("{$codename}_uninstall")) {
            call_user_func("{$codename}_uninstall");
            $message = $lang->success_plugin_uninstalled;
            $install_uninstall = true;
        }

        unset($active_plugins[$codename]);
    }

    plugin_library_load();

    settings_rebuild();

    templates_rebuild();

    db_verify_tables();

    db_verify_columns();

    rules_rebuild_cache();

    // Update plugin cache
    $plugins_cache['active'] = $active_plugins;
    $cache->update('newpoints_plugins', $plugins_cache);

    // Log admin action
    log_admin_action($codename, $install_uninstall);

    if ($mybb->get_input('action') == 'activate') {
        run_hooks('admin_plugins_activate_commit');
    } else {
        run_hooks('admin_plugins_deactivate_commit');
    }

    // Rebuild settings cache
    $array = [];
    settings_rebuild_cache($array);

    flash_message($message, 'success');
    admin_redirect(url_handler_get());
}

if (!$mybb->get_input('action')) // view plugins
{
    $page->add_breadcrumb_item($lang->newpoints_plugins, url_handler_get());

    $page->output_header($lang->newpoints_plugins);

    $sub_tabs['newpoints_plugins'] = [
        'title' => $lang->newpoints_plugins,
        'link' => url_handler_get(),
        'description' => $lang->newpoints_plugins_description
    ];

    $page->output_nav_tabs($sub_tabs, 'newpoints_plugins');

    $plugins_cache = $cache->read('newpoints_plugins');

    $active_plugins = [];

    if (!empty($plugins_cache) && is_array($plugins_cache['active'])) {
        $active_plugins = $plugins_cache['active'];
    }

    $plugins_list = newpoints_get_plugins();

    run_hooks('admin_plugins_start');

    // table
    $table = new Table();
    $table->construct_header($lang->plugin);
    $table->construct_header($lang->controls, ['colspan' => 2, 'class' => 'align_center', 'width' => 300]);

    if (!empty($plugins_list)) {
        foreach ($plugins_list as $plugin) {
            require_once MYBB_ROOT . 'inc/plugins/newpoints/plugins/' . $plugin;
            $codename = str_replace('.php', '', $plugin);
            $infofunc = $codename . '_info';
            if (!function_exists($infofunc)) {
                continue;
            }

            $plugininfo = $infofunc();
            if ($plugininfo['website']) {
                $plugininfo['name'] = "<a href=\"" . $plugininfo['website'] . "\">" . $plugininfo['name'] . '</a>';
            }

            if ($plugininfo['authorsite']) {
                $plugininfo['author'] = "<a href=\"" . $plugininfo['authorsite'] . "\">" . $plugininfo['author'] . '</a>';
            }

            if (!newpoints_iscompatible($plugininfo)) {
                $compatibility_warning = "<span style=\"color: red;\">" . $lang->sprintf(
                        $lang->newpoints_plugin_incompatible,
                        NEWPOINTS_VERSION
                    ) . '</span>';
            } else {
                $compatibility_warning = '';
            }

            $installed_func = "{$codename}_is_installed";
            $install_func = "{$codename}_install";
            $uninstall_func = "{$codename}_uninstall";

            $installed = true;
            $install_button = false;
            $uninstall_button = false;

            if (function_exists($installed_func) && $installed_func() != true) {
                $installed = false;
            }

            if (function_exists($install_func)) {
                $install_button = true;
            }

            if (function_exists($uninstall_func)) {
                $uninstall_button = true;
            }

            $table->construct_cell(
                "<strong>{$plugininfo['name']}</strong> ({$plugininfo['version']})<br /><small>{$plugininfo['description']}</small><br /><i><small>{$lang->created_by} {$plugininfo['author']}</small></i>"
            );

            // Plugin is not installed at all
            if (!$installed && $compatibility_warning) {
                $table->construct_cell(
                    "{$compatibility_warning}", ['class' => 'align_center', 'colspan' => 2]
                );
            } elseif (!$installed) {
                $activate_url = url_handler_build([
                    'action' => 'activate',
                    'plugin' => $codename,
                    'my_post_key' => $mybb->post_code
                ]);

                $table->construct_cell(
                    "<a href=\"{$activate_url}\">{$lang->install_and_activate}</a>",
                    ['class' => 'align_center', 'colspan' => 2]
                );
            } // Plugin is activated and installed
            elseif (isset($active_plugins[$codename])) {
                $deactivate_url = url_handler_build([
                    'action' => 'deactivate',
                    'plugin' => $codename,
                    'my_post_key' => $mybb->post_code
                ]);

                $table->construct_cell(
                    "<a href=\"{$deactivate_url}\">{$lang->deactivate}</a>",
                    ['class' => 'align_center', 'width' => 150]
                );

                if ($uninstall_button) {
                    $uninstall_url = url_handler_build([
                        'action' => 'deactivate',
                        'uninstall' => 1,
                        'plugin' => $codename,
                        'my_post_key' => $mybb->post_code
                    ]);

                    $table->construct_cell(
                        "<a href=\"{$uninstall_url}\">{$lang->uninstall}</a>",
                        ['class' => 'align_center', 'width' => 150]
                    );
                } else {
                    $table->construct_cell('&nbsp;', ['class' => 'align_center', 'width' => 150]);
                }
            } // Plugin is installed but not active
            elseif (!$uninstall_button && $compatibility_warning) {
                $table->construct_cell(
                    "{$compatibility_warning}",
                    ['class' => 'align_center', 'colspan' => 2]
                );
            } else {
                $activate_url = url_handler_build([
                    'action' => 'activate',
                    'plugin' => $codename,
                    'my_post_key' => $mybb->post_code
                ]);

                $table->construct_cell(
                    "<a href=\"{$activate_url}\">{$lang->activate}</a>",
                    ['class' => 'align_center', 'width' => 150]
                );

                if ($uninstall_button) {
                    $uninstall_url = url_handler_build([
                        'action' => 'deactivate',
                        'uninstall' => 1,
                        'plugin' => $codename,
                        'my_post_key' => $mybb->post_code
                    ]);

                    $table->construct_cell(
                        "<a href=\"{$uninstall_url}\">{$lang->uninstall}</a>",
                        ['class' => 'align_center', 'width' => 150]
                    );
                } else {
                    $table->construct_cell('&nbsp;', ['class' => 'align_center', 'width' => 150]);
                }
            }

            $table->construct_row();
        }
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->no_plugins, ['colspan' => 3]);
        $table->construct_row();
    }

    run_hooks('admin_plugins_end');

    $table->output($lang->plugins);

    $page->output_footer();
}

function newpoints_get_plugins(): array
{
    $plugins_list = [];

    // open directory
    $dir = @opendir(MYBB_ROOT . 'inc/plugins/newpoints/plugins/');

    // browse plugins directory
    if ($dir) {
        while ($file = readdir($dir)) {
            if (
                $file == '.' ||
                $file == '..' ||
                !str_starts_with($file, 'newpoints_') ||
                is_dir(MYBB_ROOT . 'inc/plugins/newpoints/plugins/' . $file)
            ) {
                continue;
            }

            $ext = get_extension($file);

            if ($ext === 'php') {
                $plugins_list[] = $file;
            }
        }

        @sort($plugins_list);

        @closedir($dir);
    }

    return $plugins_list;
}

function newpoints_iscompatible($plugininfo): bool
{
    if (!is_array($plugininfo)) {
        require_once MYBB_ROOT . 'inc/plugins/newpoints/plugins/' . $plugininfo . '.php';
        $infofunc = $plugininfo . '_info';
        if (!function_exists($infofunc)) {
            return false;
        }

        $plugininfo = $infofunc();
    }

    // No compatibility set or compatibility=* - assume compatible
    if (!$plugininfo['compatibility'] || $plugininfo['compatibility'] == '*') {
        return true;
    }
    $compatibility = explode(',', $plugininfo['compatibility']);
    foreach ($compatibility as $version) {
        $version = trim($version);
        $version = str_replace('*', '.+', preg_quote($version));
        $version = str_replace('\.+', '.+', $version);

        $newpoints_version_code = NEWPOINTS_VERSION_CODE;

        if (preg_match("#{$version}#i", (string)$newpoints_version_code)) {
            return true;
        }
    }

    return false;
}