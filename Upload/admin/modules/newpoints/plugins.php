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

use Newpoints\System\Url;

use function Newpoints\Admin\db_verify_columns;
use function Newpoints\Admin\db_verify_tables;
use function Newpoints\Admin\my_alerts_install;
use function Newpoints\Admin\plugin_library_load;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\instance_get;
use function Newpoints\Core\instance_object;
use function Newpoints\Core\language_load;
use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\settings_rebuild;
use function Newpoints\Core\settings_rebuild_cache;
use function Newpoints\Core\templates_rebuild;

use const Newpoints\Core\FIELDS_DATA;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb, $cache;

language_load();

$lang->load('config_plugins', false, true);

$url = new Url('index.php');

$url = $url->set_url($url->build([
    'module' => 'newpoints-plugins'
]));

$sub_tabs = [
    'newpoints_plugins' => [
        'title' => $lang->newpoints_plugins,
        'link' => $url->get_url(),
        'description' => $lang->newpoints_plugins_description
    ],
    'newpoints_plugins_check_updates' => [
        'title' => $lang->newpoints_plugins_check_updates,
        'link' => $url->build(['action' => 'check_updates']),
        'description' => $lang->newpoints_plugins_check_updates_description
    ]
];

// Activates or deactivates a specific plugin
if ($mybb->get_input('action') === 'activate' || $mybb->get_input('action') === 'deactivate') {
    if (!verify_post_check($mybb->get_input('my_post_key'))) {
        flash_message($lang->invalid_post_verify_key2, 'error');
        admin_redirect($url->get_url());
    }

    if ($mybb->get_input('action') == 'activate') {
        run_hooks('admin_plugins_activate');
    } else {
        run_hooks('admin_plugins_deactivate');
    }

    if ($mybb->get_input('no')) {
        admin_redirect($url->get_url());
    }

    if ($mybb->request_method !== 'post') {
        $process_url = $url->build([
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
        admin_redirect($url->get_url());
    }

    $plugins_cache = $cache->read('newpoints_plugins');
    $active_plugins = $plugins_cache['active'] ?? [];

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
            admin_redirect($url->get_url());
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
    } else {
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

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            db_verify_columns(
                [
                    'users' => [
                        instance_object($instance_id)->users_column_get() => FIELDS_DATA['users']['newpoints']
                    ]
                ]
            );
        } catch (Exception $e) {
            \Newpoints\Core\log_error($instance_id, $e->getMessage());
        }
    }

    db_verify_columns();

    rules_rebuild_cache();

    my_alerts_install();

    $cache->update_attachtypes();

    $cache->update_smilies();

    $cache->update_posticons();

    $cache->update_badwords();

    $cache->update_usergroups();

    $cache->update_forumpermissions();

    $cache->update_stats();

    $cache->update_statistics();

    $cache->update_moderators();

    $cache->update_awaitingactivation();

    $cache->update_forums();

    $cache->update_usertitles();

    $cache->update_reportedcontent();

    $cache->update_mycode();

    $cache->update_mailqueue();

    $cache->update_update_check();

    $cache->update_default_theme();

    $cache->update_tasks();

    $cache->update_bannedips();

    $cache->update_bannedemails();

    $cache->update_spiders();

    $cache->update_most_replied_threads();

    $cache->update_most_viewed_threads();

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

    admin_redirect($url->get_url());
} elseif ($mybb->input['action'] == 'check_updates') {
    $plugins_list = newpoints_get_plugins();

    run_hooks('admin_config_plugins_check');

    $plugins_codenames = $plugins_names = [];

    if ($plugins_list) {
        $active_hooks = $plugins->hooks;

        foreach ($plugins_list as $plugin_file) {
            require_once MYBB_ROOT . 'inc/plugins/newpoints/plugins/' . $plugin_file;

            $plugin_codename = str_replace('.php', '', $plugin_file);

            $plugin_information_function = $plugin_codename . '_info';

            if (!function_exists($plugin_information_function)) {
                continue;
            }

            $plugin_information = $plugin_information_function();

            $plugin_information['codename'] = trim($plugin_information['codename'] ?? '');

            if (!empty($plugin_information['codename'])) {
                $plugins_codenames[] = $plugin_information['codename'];

                $plugins_names[$plugin_information['codename']] = [
                    'name' => $plugin_information['name'] ?? '',
                    'version' => $plugin_information['version'] ?? ''
                ];
            }
        }

        $plugins->hooks = $active_hooks;
    }

    if (empty($plugins_codenames)) {
        flash_message($lang->newpoints_plugins_error_version_check_no_supported_plugins, 'error');

        admin_redirect($url->get_url());
    }

    $plugin_repositories = array_map('trim', explode(PHP_EOL, get_setting('main_plugins_repositories')));

    $repositories_plugins = [];

    foreach ($plugin_repositories as $plugin_repository) {
        $repository_contents = fetch_remote_file(
            'https://' . $plugin_repository . '/version_check.php?' . http_build_query(['info' => $plugins_codenames]
            ) . '&'
        );

        if (!$repository_contents) {
            continue;
        }

        $repository_contents = trim($repository_contents);

        $parser = create_xml_parser($repository_contents);

        $tree = $parser->get_tree();

        if (!is_array($tree) || !isset($tree['plugins'])) {
            continue;
        }

        $tree['plugin_repository'] = $plugin_repository;

        foreach ($tree['plugins']['plugin'] as $key => $plugin_data) {
            $tree['plugins']['plugin'][$key]['repository'] = $plugin_repository;
        }

        $repositories_plugins = array_merge_recursive($tree['plugins'], $repositories_plugins);
    }

    if (!$repositories_plugins) {
        flash_message($lang->newpoints_plugins_error_communication_problem, 'error');

        admin_redirect($url->get_url());
    }

    if (isset($repositories_plugins[0]) && array_key_exists('error', $repositories_plugins)) {
        switch ($repositories_plugins[0]['error']) {
            case '1':
                $error_msg = $lang->newpoints_plugins_error_communication_problem_no_input;
                break;
            case '2':
                $error_msg = $lang->newpoints_plugins_error_communication_problem_no_plugin_ids;
                break;
            default:
                $error_msg = '';
        }

        flash_message($lang->newpoints_plugins_error_communication_problem . $error_msg, 'error');

        admin_redirect($url->get_url());
    }

    $table = new Table();

    $table->construct_header($lang->newpoints_plugins_plugin);

    $table->construct_header($lang->newpoints_plugins_your_version, ['class' => 'align_center', 'width' => 125]);

    $table->construct_header($lang->newpoints_plugins_latest_version, ['class' => 'align_center', 'width' => 125]);

    $table->construct_header($lang->controls, ['class' => 'align_center', 'width' => 125]);

    if (!is_array($repositories_plugins['plugin'])) {
        flash_message($lang->newpoints_plugins_success_plugins_up_to_date, 'success');

        admin_redirect($url->get_url());
    }

    if (array_key_exists('tag', $repositories_plugins['plugin'])) {
        $only_plugin = $repositories_plugins['plugin'];

        unset($repositories_plugins['plugin']);

        $repositories_plugins['plugin'][0] = $only_plugin;
    }

    $done_plugins = [];

    foreach ($repositories_plugins['plugin'] as $plugin_data) {
        $is_vulnerable = array_key_exists('vulnerable', $plugin_data);

        if (empty($done_plugins[$plugin_data['attributes']['codename']]) && version_compare(
                $plugins_names[$plugin_data['attributes']['codename']]['version'],
                $plugin_data['version']['value'],
                '<'
            )) {
            $done_plugins[$plugin_data['attributes']['codename']] = true;

            $plugin_data['download_url']['value'] = htmlspecialchars_uni($plugin_data['download_url']['value']);

            $plugin_data['version']['value'] = htmlspecialchars_uni($plugin_data['version']['value']);

            if (isset($plugin_data['vulnerable']['value'])) {
                $plugin_data['vulnerable']['value'] = htmlspecialchars_uni($plugin_data['vulnerable']['value']);
            }

            if ($is_vulnerable) {
                $table->construct_cell(
                    "<div class=\"error\" id=\"flash_message\">
    {$lang->newpoints_plugins_error_version_check_vulnerable} {$plugins_names[$plugin_data['attributes']['codename']]['name']}
</div>
<p>	<b>{$lang->newpoints_plugins_error_version_vulnerable_notes}</b> <br /><br /> {$plugin_data['vulnerable']['value']}</p>"
                );
            } else {
                $table->construct_cell(
                    "<strong>{$plugins_names[$plugin_data['attributes']['codename']]['name']}</strong>"
                );
            }

            $table->construct_cell(
                "{$plugins_names[$plugin_data['attributes']['codename']]['version']}",
                ['class' => 'align_center']
            );

            $table->construct_cell(
                "<strong><span style=\"color: #C00\">{$plugin_data['version']['value']}</span></strong>",
                ['class' => 'align_center']
            );

            if ($is_vulnerable) {
                $main_module_url = $url->get_url();

                $table->construct_cell(
                    "<a href=\"{$main_module_url}\"><b>{$lang->newpoints_plugins_deactivate}</b></a>",
                    ['class' => 'align_center', 'width' => 150]
                );
            } else {
                $plugin_repository = $plugin_data['repository'];

                $table->construct_cell(
                    "<strong><a href=\"https://{$plugin_repository}/{$plugin_data['download_url']['value']}\" target=\"_blank\" rel=\"noopener\">{$lang->newpoints_plugins_download}</a></strong>",
                    ['class' => 'align_center']
                );
            }

            $table->construct_row();
        }
    }

    if ($table->num_rows() == 0) {
        flash_message($lang->newpoints_plugins_success_plugins_up_to_date, 'success');

        admin_redirect($url->get_url());
    }

    $page->add_breadcrumb_item($lang->newpoints_plugins_plugin_updates);

    $page->output_header($lang->newpoints_plugins_plugin_updates);

    $page->output_nav_tabs($sub_tabs, 'newpoints_plugins_check_updates');

    $table->output($lang->newpoints_plugins_plugin_updates);

    $page->output_footer();
} else {
    $page->add_breadcrumb_item($lang->newpoints_plugins, $url->get_url());

    $page->output_header($lang->newpoints_plugins);

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
            $plugin_information_function = $codename . '_info';
            if (!function_exists($plugin_information_function)) {
                continue;
            }

            $plugin_information = $plugin_information_function();

            if (!empty($plugin_information['website'])) {
                $plugin_information['name'] = "<a href=\"" . $plugin_information['website'] . "\">" . $plugin_information['name'] . '</a>';
            }

            if (!empty($plugin_information['authorsite'])) {
                $plugin_information['author'] = "<a href=\"" . $plugin_information['authorsite'] . "\">" . $plugin_information['author'] . '</a>';
            }

            if (!newpoints_iscompatible($plugin_information)) {
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
                "<strong>{$plugin_information['name']}</strong> ({$plugin_information['version']})<br /><small>{$plugin_information['description']}</small><br /><i><small>{$lang->created_by} {$plugin_information['author']}</small></i>"
            );

            // Plugin is not installed at all
            if (!$installed && $compatibility_warning) {
                $table->construct_cell(
                    "{$compatibility_warning}",
                    ['class' => 'align_center', 'colspan' => 2]
                );
            } elseif (!$installed) {
                $activate_url = $url->build([
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
                $deactivate_url = $url->build([
                    'action' => 'deactivate',
                    'plugin' => $codename,
                    'my_post_key' => $mybb->post_code
                ]);

                $table->construct_cell(
                    "<a href=\"{$deactivate_url}\">{$lang->deactivate}</a>",
                    ['class' => 'align_center', 'width' => 150]
                );

                if ($uninstall_button) {
                    $uninstall_url = $url->build([
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
                $activate_url = $url->build([
                    'action' => 'activate',
                    'plugin' => $codename,
                    'my_post_key' => $mybb->post_code
                ]);

                $table->construct_cell(
                    "<a href=\"{$activate_url}\">{$lang->activate}</a>",
                    ['class' => 'align_center', 'width' => 150]
                );

                if ($uninstall_button) {
                    $uninstall_url = $url->build([
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
    $dir = opendir(MYBB_ROOT . 'inc/plugins/newpoints/plugins/');

    // browse plugins directory
    if ($dir) {
        while ($file = readdir($dir)) {
            if (
                $file == '.' ||
                $file == '..' ||
                strpos($file, 'newpoints_') !== 0 ||
                is_dir(MYBB_ROOT . 'inc/plugins/newpoints/plugins/' . $file)
            ) {
                continue;
            }

            $ext = get_extension($file);

            if ($ext === 'php') {
                $plugins_list[] = $file;
            }
        }

        sort($plugins_list);

        closedir($dir);
    }

    return $plugins_list;
}

function newpoints_iscompatible($plugin_info): bool
{
    if (!is_array($plugin_info)) {
        require_once MYBB_ROOT . 'inc/plugins/newpoints/plugins/' . $plugin_info . '.php';

        $plugin_function = $plugin_info . '_info';

        if (!function_exists($plugin_function)) {
            return false;
        }

        $plugin_info = $plugin_function();
    }

    if (empty($plugin_info['compatibility']) || $plugin_info['compatibility'] === '*') {
        return true;
    }

    $compatibility = explode(',', $plugin_info['compatibility']);

    $is_compatible = false;

    foreach ($compatibility as $version) {
        $version = trim($version);
        $version = str_replace('*', '.+', preg_quote($version));
        $version = str_replace('\.+', '.+', $version);

        $newpoints_version_code = NEWPOINTS_VERSION_CODE;

        if (preg_match("#{$version}#i", (string)$newpoints_version_code)) {
            $is_compatible = true;

            break;
        }
    }

    return $is_compatible;
}