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

function newpoints_meta(): bool
{
    global $page, $lang;

    language_load();

    $sub_menu_items = [
        10 => [
            'id' => 'plugins',
            'title' => $lang->nav_plugins,
            'link' => 'index.php?module=newpoints-plugins'
        ],
        15 => [
            'id' => 'settings',
            'title' => $lang->nav_settings,
            'link' => 'index.php?module=newpoints-settings'
        ],
        20 => [
            'id' => 'log',
            'title' => $lang->nav_log,
            'link' => 'index.php?module=newpoints-log'
        ],
        25 => [
            'id' => 'maintenance',
            'title' => $lang->nav_maintenance,
            'link' => 'index.php?module=newpoints-maintenance'
        ],
        30 => [
            'id' => 'forumrules',
            'title' => $lang->nav_forumrules,
            'link' => 'index.php?module=newpoints-forumrules'
        ],
        35 => [
            'id' => 'grouprules',
            'title' => $lang->nav_grouprules,
            'link' => 'index.php?module=newpoints-grouprules'
        ]
    ];

    if (function_exists('\Newpoints\Core\run_hooks')) {
        $sub_menu_items = run_hooks('admin_menu', $sub_menu_items);
    }

    $page->add_menu_item($lang->newpoints, 'newpoints', 'index.php?module=newpoints', 60, $sub_menu_items);

    return true;
}

function newpoints_action_handler(string $current_action): string
{
    global $page;

    $page->active_module = 'newpoints';

    $action_handlers = [
        'plugins' => [
            'active' => 'plugins',
            'file' => 'plugins.php'
        ],
        'settings' => [
            'active' => 'settings',
            'file' => 'settings.php'
        ],
        'log' => [
            'active' => 'log',
            'file' => 'log.php'
        ],
        'maintenance' => [
            'active' => 'maintenance',
            'file' => 'maintenance.php'
        ],
        'forumrules' => [
            'active' => 'forumrules',
            'file' => 'forumrules.php'
        ],
        'grouprules' => [
            'active' => 'grouprules',
            'file' => 'grouprules.php'
        ],
    ];

    $action_handlers = run_hooks('admin_action_handler', $action_handlers);

    if (!isset($action_handlers[$current_action])) {
        $page->active_action = 'plugins';

        return 'plugins.php';
    }

    $page->active_action = $action_handlers[$current_action]['active'];

    return $action_handlers[$current_action]['file'];
}

function newpoints_admin_permissions(): array
{
    global $lang;

    language_load();

    $admin_permissions = [
        'newpoints' => $lang->can_manage_newpoints,
        'plugins' => $lang->can_manage_plugins,
        'settings' => $lang->can_manage_settings,
        'log' => $lang->can_manage_log,
        'maintenance' => $lang->can_manage_maintenance,
        'forumrules' => $lang->can_manage_forumrules,
        'grouprules' => $lang->can_manage_grouprules,
    ];

    $admin_permissions = run_hooks('admin_permissions', $admin_permissions);

    return ['name' => $lang->newpoints, 'permissions' => $admin_permissions, 'disporder' => 60];
}