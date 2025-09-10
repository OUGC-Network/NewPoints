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

use function NewPoints\Core\cache_get_instances;
use function NewPoints\Core\instance_object;
use function NewPoints\Core\language_load;
use function NewPoints\Core\log_error;
use function NewPoints\Core\run_hooks;

use const NewPoints\Core\DEBUG;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function newpoints_meta(): bool
{
    global $page, $lang;

    if (function_exists('\NewPoints\Core\language_load')) {
        language_load();
    } else {
        isset($lang->newpoints) || $lang->load('newpoints');
        isset($lang->nav_plugins) || $lang->load('newpoints_module_meta');

        return false;
    }

    $sub_menu_items = [
        10 => [
            'id' => 'plugins',
            'title' => $lang->nav_plugins,
            'link' => 'index.php?module=newpoints-plugins'
        ],
        20 => [
            'id' => 'instances',
            'title' => $lang->nav_instances,
            'link' => 'index.php?module=newpoints-instances'
        ]
    ];

    if (DEBUG) {
        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id);

                $sub_menu_items[9000 + $instance->instance_id] = [
                    'id' => 'instance_' . $instance->instance_id,
                    'title' => $instance->get_display_name_upper(),
                    'link' => 'index.php?module=newpoints-settings&instance_id=' . $instance->instance_id
                ];
            } catch (Exception $e) {
                log_error($instance_id, $e->getMessage());
            }
        }
    }

    if (function_exists('\NewPoints\Core\run_hooks')) {
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
        'instances' => [
            'active' => 'instances',
            'file' => 'instances.php'
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

    if (function_exists('\NewPoints\Core\language_load')) {
        language_load();
    } else {
        isset($lang->newpoints) || $lang->load('newpoints');
        isset($lang->nav_plugins) || $lang->load('newpoints_module_meta');
    }

    $admin_permissions = [
        'newpoints' => $lang->can_manage_newpoints,
        'plugins' => $lang->can_manage_plugins,
        'settings' => $lang->can_manage_settings,
        'instances' => $lang->can_manage_instances,
    ];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $action_handlers['instance_' . instance_object($instance_id)->instance_id] = [
                'active' => 'settings',
                'file' => 'settings.php'
            ];
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

    if (function_exists('\NewPoints\Core\language_load')) {
        $admin_permissions = run_hooks('admin_permissions', $admin_permissions);
    }

    return ['name' => $lang->newpoints, 'permissions' => $admin_permissions, 'disporder' => 60];
}