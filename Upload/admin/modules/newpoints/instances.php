<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/instances.php)
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

use function Newpoints\Admin\db_verify_columns;
use function Newpoints\Core\instance_get;
use function Newpoints\Core\instance_object;
use function Newpoints\Core\language_load;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\url_handler_set;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb;

language_load();

url_handler_set('index.php');

url_handler_set(url_handler_build([
    'module' => 'newpoints-instances',
]));

$sub_tabs = [
    'newpoints_instances' => [
        'title' => $lang->newpoints_instances,
        'link' => url_handler_build(),
        'description' => $lang->newpoints_instances_description
    ],
    'newpoints_instances_new' => [
        'title' => $lang->newpoints_instances_new,
        'link' => url_handler_build(['action' => 'new']),
        'description' => $lang->newpoints_instances_new_description
    ],
];

run_hooks('admin_instances_begin');

if ($mybb->get_input('action') === 'create_column') {
    $instance_id = $mybb->get_input('instance_id', MyBB::INPUT_INT);

    try {
        $instance_object = instance_object($instance_id);
    } catch (InvalidArgumentException $e) {
        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');
    }

    db_verify_columns(
        [
            'users' => [
                $instance_object->get_users_column_name() => \Newpoints\Core\FIELDS_DATA['users']['newpoints']
            ]
        ]
    );

    flash_message($lang->newpoints_instances_create_column_success, 'success');

    admin_redirect('index.php?module=newpoints-instances');
} else {
    $page->add_breadcrumb_item($lang->newpoints_instances, url_handler_build());

    $page->output_header($lang->newpoints_instances);

    $page->output_nav_tabs($sub_tabs, 'newpoints_instances');

    $table = new Table();

    $table->construct_header($lang->newpoints_instances_thead_id, ['width' => '1%', 'class' => 'align_center']);

    $table->construct_header($lang->newpoints_instances_thead_name);

    $table->construct_header($lang->newpoints_instances_thead_column, ['width' => '20%', 'class' => 'align_center']);

    $table->construct_header($lang->newpoints_instances_thead_main_file, ['width' => '20%', 'class' => 'align_center']);

    $table->construct_header($lang->newpoints_instances_thead_enabled, ['width' => '5%', 'class' => 'align_center']);

    $table->construct_header($lang->options, ['width' => '15%', 'class' => 'align_center']);

    url_handler_set('index.php');

    url_handler_set(url_handler_build([
        'module' => 'newpoints-settings',
    ]));

    $permissions_settings = check_admin_permissions(['module' => $page->active_module, 'action' => 'settings'], false);

    foreach (instance_get() as $instance_id => $instance_data) {
        $table->construct_cell($instance_id, ['class' => 'align_center']);

        try {
            $instance_object = instance_object($instance_id);
        } catch (InvalidArgumentException $e) {
        }

        try {
            $table->construct_cell($instance_object->get_display_name_upper());
        } catch (Exception $e) {
        }

        if ($instance_object->column_exists()) {
            $table->construct_cell(
                "<code style='color: darkgreen;'>{$instance_data['users_column_name']}</code>",
                ['class' => 'align_center']
            );
        } else {
            $table->construct_cell(
                "<code style='color: darkred;'>{$instance_data['users_column_name']}</code>",
                ['class' => 'align_center']
            );
        }

        $main_file = \Newpoints\Core\get_setting('main_file', $instance_id);

        if ($main_file_exists = file_exists(MYBB_ROOT . $main_file)) {
            $table->construct_cell(
                "<code style='color: darkgreen;'>" . $main_file . "</code>",
                ['class' => 'align_center']
            );
        } else {
            $table->construct_cell(
                "<code style='color: darkgreen;'>" . $main_file . "</code>",
                ['class' => 'align_center']
            );
        }

        if ($instance_data['enabled']) {
            $phrase = $lang->disable;

            $icon = "on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}";
        } else {
            $phrase = $lang->enable;

            $icon = "off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}";
        }

        $urlParams = [];

        $table->construct_cell(
            "<img src=\"styles/{$page->style}/images/icons/bullet_{$icon}\" style=\"vertical-align: middle;\" />",
            ['class' => 'align_center']
        );

        $popup = new PopupMenu("instance_{$instance_id}", $lang->options);

        if ($permissions_settings) {
            $popup->add_item(
                $lang->newpoints_instances_thead_options_settings,
                url_handler_build(['instance_id' => $instance_id]),
            );
        }

        $popup->add_item(
            $lang->edit,
            'index.php?module=newpoints-instances&amp;action=edit&amp;instance_id=' . $instance_id,
        );

        if ($instance_id !== \Newpoints\Core\INSTANCE_DEFAULT_ID) {
            if (!$instance_object->column_exists()) {
                $popup->add_item(
                    $lang->newpoints_instances_thead_options_create_column,
                    'index.php?module=newpoints-instances&amp;action=create_column&amp;instance_id=' . $instance_id,
                );
            }
        }

        if ($main_file_exists && !$instance_data['enabled']) {
            $popup->add_item(
                $lang->enable,
                'index.php?module=newpoints-instances&amp;action=enable&amp;instance_id=' . $instance_id,
            );
        } else {
            $popup->add_item(
                $lang->disable,
                'index.php?module=newpoints-instances&amp;action=disable&amp;instance_id=' . $instance_id,
            );
        }

        if ($instance_id !== \Newpoints\Core\INSTANCE_DEFAULT_ID) {
            $popup->add_item(
                $lang->delete,
                'index.php?module=newpoints-instances&amp;action=delete&amp;instance_id=' . $instance_id,
            );
        }

        $table->construct_cell($popup->fetch(), ['class' => 'align_center']);

        $table->construct_row();
    }

    $table->output($lang->newpoints_instances_title);
}

run_hooks('admin_instances_terminate');

$page->output_footer();