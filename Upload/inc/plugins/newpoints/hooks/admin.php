<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/hooks/admin.php)
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

namespace Newpoints\Hooks\Admin;

use MyBB;

use function Newpoints\Core\load_set_guest_data;
use function Newpoints\Core\run_hooks;

use const Newpoints\ROOT;

function admin_config_plugins_deactivate(): bool
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') != 'deactivate' ||
        $mybb->get_input('plugin') != 'newpoints' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
        return false;
    }

    if ($mybb->request_method != 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=newpoints'
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function admin_load(): bool
{
    global $plugins, $newpoints_plugins, $mybb;

    if (!$newpoints_plugins || !isset($newpoints_plugins)) {
        load_set_guest_data();
    }

    // as plugins can't hook to admin_load, we must allow them to hook to newpoints_admin_load
    run_hooks('admin_load');

    return true;
}

function admin_load10(): bool
{
    global $run_module, $action_file;

    if ($run_module !== 'newpoints') {
        return false;
    }

    $action_file_path = ROOT . "/admin/{$action_file}";

    require_once $action_file_path;

    return true;
}

function admin_tabs(array $modules): array
{
    global $lang;
    global $is_super_admin;

    require_once ROOT . '/admin/module_meta.php';

    $lang->load('newpoints_module_meta', false, true);

    $has_permission = false;

    if (function_exists('newpoints_admin_permissions')) {
        if (isset($mybb->admin['permissions']['newpoints']) || $is_super_admin) {
            $has_permission = true;
        }
    } else {
        $has_permission = true;
    }

    if ($has_permission) {
        $initialized = newpoints_meta();

        if ($initialized) {
            $modules['newpoints'] = 1;
        }
    } else {
        $modules['newpoints'] = 0;
    }

    return $modules;
}

function admin_user_admin_permissions_edit(): bool
{
    global $permission_modules, $modules;
    global $newpoints_custom_load;

    $newpoints_custom_load = true;
    //$modules = [];

    require_once ROOT . '/admin/module_meta.php';

    $permission_modules['newpoints'] = newpoints_admin_permissions();

    return true;
}

function admin_page_output_tab_control_start(array $tabs): array
{
    global $newpoints_custom_load;

    if (empty($newpoints_custom_load)) {
        return $tabs;
    }

    static $already_done = false;

    if ($already_done === true) {
        return $tabs;
    }

    $already_done = true;

    global $permission_modules, $modules;
    global $module_tabs;

    require_once ROOT . '/admin/module_meta.php';

    $modules[$permission_modules['newpoints']['disporder']][] = 'newpoints';

    ksort($modules);

    $module_tabs = [];

    foreach ($modules as $mod) {
        if (!is_array($mod)) {
            continue;
        }

        foreach ($mod as $module) {
            $module_tabs[$module] = $permission_modules[$module]['name'];
        }
    }

    return $module_tabs;
}

function admin_newpoints_menu(array &$sub_menu): array
{
    global $plugins, $newpoints_plugins;

    if (!$newpoints_plugins || !isset($newpoints_plugins)) {
        //plugins_load();
    }

    // as plugins can't hook to admin_newpoints_menu, we must allow them to hook to newpoints_admin_newpoints_menu
    $sub_menu = run_hooks('admin_newpoints_menu', $sub_menu);

    return $sub_menu;
}

function admin_newpoints_action_handler(array &$actions): array
{
    global $plugins, $newpoints_plugins;

    if (!$newpoints_plugins || !isset($newpoints_plugins)) {
        //plugins_load();
    }

    // as plugins can't hook to admin_newpoints_action_handler, we must allow them to hook to newpoints_newpoints_action_handler
    $actions = run_hooks('admin_newpoints_action_handler', $actions);

    return $actions;
}

function admin_newpoints_permissions(array &$admin_permissions): array
{
    global $plugins, $newpoints_plugins;

    if (!$newpoints_plugins || !isset($newpoints_plugins)) {
        //plugins_load();
    }

    // as plugins can't hook to admin_newpoints_permissions, we must allow them to hook to newpoints_newpoints_permissions
    $admin_permissions = run_hooks('admin_newpoints_permissions', $admin_permissions);

    return $admin_permissions;
}