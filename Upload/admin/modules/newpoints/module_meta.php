<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/module_meta.php)
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

use function Newpoints\Core\language_load;
use function Newpoints\Core\run_hooks;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function newpoints_meta()
{
    global $page, $lang, $plugins;

    $sub_menu = [];
    $sub_menu['10'] = [
        'id' => 'plugins',
        'title' => $lang->nav_plugins,
        'link' => 'index.php?module=newpoints-plugins'
    ];
    $sub_menu['15'] = [
        'id' => 'settings',
        'title' => $lang->nav_settings,
        'link' => 'index.php?module=newpoints-settings'
    ];
    $sub_menu['20'] = ['id' => 'log', 'title' => $lang->nav_log, 'link' => 'index.php?module=newpoints-log'];
    $sub_menu['25'] = [
        'id' => 'maintenance',
        'title' => $lang->nav_maintenance,
        'link' => 'index.php?module=newpoints-maintenance'
    ];
    $sub_menu['30'] = [
        'id' => 'forumrules',
        'title' => $lang->nav_forumrules,
        'link' => 'index.php?module=newpoints-forumrules'
    ];
    $sub_menu['35'] = [
        'id' => 'grouprules',
        'title' => $lang->nav_grouprules,
        'link' => 'index.php?module=newpoints-grouprules'
    ];
    $sub_menu['40'] = ['id' => 'stats', 'title' => $lang->nav_stats, 'link' => 'index.php?module=newpoints-stats'];
    $sub_menu['45'] = [
        'id' => 'upgrades',
        'title' => $lang->nav_upgrades,
        'link' => 'index.php?module=newpoints-upgrades'
    ];

    if (function_exists('\\Newpoints\\Core\\run_hooks')) {
        $sub_menu = run_hooks('admin_menu', $sub_menu);
    }

    language_load();

    $page->add_menu_item($lang->newpoints, 'newpoints', 'index.php?module=newpoints', 60, $sub_menu);

    return true;
}

function newpoints_action_handler($action)
{
    global $page, $lang, $plugins;

    $page->active_module = 'newpoints';

    $actions = [
        'plugins' => ['active' => 'plugins', 'file' => 'plugins.php'],
        'settings' => ['active' => 'settings', 'file' => 'settings.php'],
        'log' => ['active' => 'log', 'file' => 'log.php'],
        'maintenance' => ['active' => 'maintenance', 'file' => 'maintenance.php'],
        'forumrules' => ['active' => 'forumrules', 'file' => 'forumrules.php'],
        'grouprules' => ['active' => 'grouprules', 'file' => 'grouprules.php'],
        'stats' => ['active' => 'stats', 'file' => 'stats.php'],
        'upgrades' => ['active' => 'upgrades', 'file' => 'upgrades.php'],
    ];

    $actions = run_hooks('admin_action_handler', $actions);

    if (!isset($actions[$action])) {
        $page->active_action = 'plugins';
        return 'plugins.php';
    }

    $page->active_action = $actions[$action]['active'];
    return $actions[$action]['file'];
}

function newpoints_admin_permissions()
{
    global $lang, $plugins;

    $admin_permissions = [
        'newpoints' => $lang->can_manage_newpoints,
        'plugins' => $lang->can_manage_plugins,
        'settings' => $lang->can_manage_settings,
        'log' => $lang->can_manage_log,
        'maintenance' => $lang->can_manage_maintenance,
        'forumrules' => $lang->can_manage_forumrules,
        'grouprules' => $lang->can_manage_grouprules,
        'stats' => $lang->can_manage_stats,
        'upgrades' => $lang->can_manage_upgrades
    ];

    $admin_permissions = run_hooks('admin_permissions', $admin_permissions);

    return ['name' => $lang->newpoints, 'permissions' => $admin_permissions, 'disporder' => 60];
}