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

use FormContainer;
use MyBB;

use function Newpoints\Core\language_load;
use function Newpoints\Core\load_set_guest_data;
use function Newpoints\Core\run_hooks;

use const Newpoints\Core\FIELDS_DATA;
use const Newpoints\Core\FORM_TYPE_CHECK_BOX;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD;
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
    load_set_guest_data();

    run_hooks('admin_load');

    global $run_module, $action_file;

    if ($run_module !== 'newpoints') {
        return false;
    }

    require_once ROOT . "/admin/{$action_file}";

    return true;
}

function admin_tabs(array $modules): array
{
    global $is_super_admin;

    require_once ROOT . '/admin/module_meta.php';

    language_load('module_meta', false, true);

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
    global $permission_modules;
    global $newpoints_custom_load;

    $newpoints_custom_load = true;

    require_once ROOT . '/admin/module_meta.php';

    $permission_modules['newpoints'] = \newpoints_admin_permissions();

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

function newpoints_admin_menu(array &$sub_menu): array
{
    // as plugins can't hook to admin_newpoints_menu, we must allow them to hook to newpoints_admin_newpoints_menu
    $sub_menu = run_hooks('admin_newpoints_menu', $sub_menu);

    return $sub_menu;
}

function newpoints_admin_action_handler(array &$actions): array
{
    // as plugins can't hook to admin_newpoints_action_handler, we must allow them to hook to newpoints_newpoints_action_handler
    $actions = run_hooks('admin_newpoints_action_handler', $actions);

    return $actions;
}

function newpoints_admin_permissions(array &$admin_permissions): array
{
    // as plugins can't hook to admin_newpoints_permissions, we must allow them to hook to newpoints_newpoints_permissions
    $admin_permissions = run_hooks('admin_newpoints_permissions', $admin_permissions);

    return $admin_permissions;
}

function admin_user_groups_edit_graph_tabs(array &$tabs): array
{
    global $lang;

    language_load();

    $tabs['newpoints'] = $lang->newpoints_groups_tab;

    return $tabs;
}

function admin_user_groups_edit_graph(): bool
{
    global $lang, $form, $mybb;

    language_load();

    $data_fields = FIELDS_DATA['usergroups'];

    echo '<div id="tab_newpoints">';

    $form_container = new FormContainer($lang->newpoints_groups_tab);

    $form_fields = [];

    $hook_arguments = [
        'data_fields' => &$data_fields,
        'form_fields' => &$form_fields
    ];

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_start', $hook_arguments);

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (!isset($data_field_data['formType'])) {
            continue;
        }

        $setting_language_string = $data_field_key;

        if (strpos($data_field_key, 'newpoints_user_groups_') !== 0) {
            $setting_language_string = str_replace('newpoints_', 'newpoints_user_groups_', $data_field_key);
        }

        $value = $mybb->get_input($data_field_key, MyBB::INPUT_INT);

        switch ($data_field_data['formType']) {
            case FORM_TYPE_CHECK_BOX:
                $form_fields[] = $form->generate_check_box(
                    $data_field_key,
                    1,
                    $lang->{$setting_language_string},
                    ['checked' => $value]
                );
                break;
            case FORM_TYPE_NUMERIC_FIELD:
                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $value = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
                }

                $form_fields[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                        $data_field_key,
                        $value,
                        [
                            'min' => isset($data_field_data['formOptions']) ? ($data_field_data['formOptions']['min'] ?? 0) : 0,
                            'step' => isset($data_field_data['formOptions']) ? ($data_field_data['formOptions']['step'] ?? 1) : 1,
                        ]
                    );
                break;
        }
    }

    if (empty($form_fields)) {
        return false;
    }

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_intermediate', $hook_arguments);

    $form_container->output_row(
        $lang->newpoints_groups_users,
        '',
        '<div class="group_settings_bit">' . implode('</div><div class="group_settings_bit">', $form_fields) . '</div>'
    );

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_end', $hook_arguments);

    $form_container->end();

    echo '</div>';

    return true;
}

function admin_user_groups_edit_commit(): bool
{
    global $mybb, $db;
    global $updated_group;

    $data_fields = FIELDS_DATA['usergroups'];

    $hook_arguments = [
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('admin_user_groups_edit_commit_start', $hook_arguments);

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (in_array($data_field_data['type'], ['INT', 'SMALLINT', 'TINYINT'])) {
            $updated_group[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_INT);
        } elseif (in_array($data_field_data['type'], ['FLOAT', 'DECIMAL'])) {
            $updated_group[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
        } else {
            $updated_group[$data_field_key] = $db->escape_string($mybb->get_input($data_field_key));
        }
    }

    return true;
}

function admin_formcontainer_end(array &$current_hook_arguments): array
{
    global $lang;
    global $run_module;

    static $done = false;

    if (
        $done ||
        $run_module !== 'forum' ||
        !isset($current_hook_arguments['this']->_title) ||
        (
            $current_hook_arguments['this']->_title !== $lang->additional_forum_options &&
            $current_hook_arguments['this']->_title !== "<div class=\"float_right\" style=\"font-weight: normal;\"><a href=\"#\" onclick=\"$('#additional_options_link').toggle(); $('#additional_options').fadeToggle('fast'); return false;\">{$lang->hide_additional_options}</a></div>" . $lang->additional_forum_options
        )) {
        return $current_hook_arguments;
    }

    $done = true;

    global $lang, $form;
    global $forum_data;

    language_load();

    $data_fields = FIELDS_DATA['forums'];

    $form_fields = [];

    $hook_arguments = [
        'data_fields' => &$data_fields,
        'form_fields' => &$form_fields
    ];

    $hook_arguments = run_hooks('admin_formcontainer_end_start', $hook_arguments);

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (!isset($data_field_data['formType'])) {
            continue;
        }

        $setting_language_string = $data_field_key;

        if (strpos($data_field_key, 'newpoints_forums_') !== 0) {
            $setting_language_string = str_replace('newpoints_', 'newpoints_forums_', $data_field_key);
        }

        $value = (int)$forum_data[$data_field_key];

        switch ($data_field_data['formType']) {
            case FORM_TYPE_CHECK_BOX:
                $form_fields[] = $form->generate_check_box(
                    $data_field_key,
                    1,
                    $lang->{$setting_language_string},
                    ['checked' => $value]
                );
                break;
            case FORM_TYPE_NUMERIC_FIELD:

                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $value = (float)$forum_data[$data_field_key];
                }

                $form_fields[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                        $data_field_key,
                        $value,
                        [
                            'min' => isset($data_field_data['formOptions']) ? ($data_field_data['formOptions']['min'] ?? 0) : 0,
                            'step' => isset($data_field_data['formOptions']) ? ($data_field_data['formOptions']['step'] ?? 1) : 1,
                        ]
                    );
                break;
        }
    }

    if (empty($form_fields)) {
        return $current_hook_arguments;
    }

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_intermediate', $hook_arguments);

    $current_hook_arguments['this']->output_row(
        $lang->newpoints_forums,
        '',
        "<div class=\"forum_settings_bit\">" . implode(
            "</div><div class=\"forum_settings_bit\">",
            $form_fields
        ) . '</div>'
    );

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_end', $hook_arguments);

    return $current_hook_arguments;
}

function admin_forum_management_edit_commit(): bool
{
    global $db, $mybb, $fid;

    $data_fields = FIELDS_DATA['forums'];

    $hook_arguments = [
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('admin_forum_management_edit_commit_start', $hook_arguments);

    $updated_forum = [];

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (in_array($data_field_data['type'], ['INT', 'SMALLINT', 'TINYINT'])) {
            $updated_forum[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_INT);
        } elseif (in_array($data_field_data['type'], ['FLOAT', 'DECIMAL'])) {
            $updated_forum[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
        } else {
            $updated_forum[$data_field_key] = $db->escape_string($mybb->get_input($data_field_key));
        }
    }

    $db->update_query('forums', $updated_forum, "fid='{$fid}'");

    $mybb->cache->update_forums();

    return true;
}

function admin_user_users_edit_graph_tabs(array &$tabs): array
{
    global $lang;

    language_load();

    $tabs['newpoints'] = $lang->newpoints_users_tab;

    return $tabs;
}

function admin_user_users_edit_graph(): bool
{
    global $mybb, $lang;
    global $user, $form;

    language_load();

    $data_fields = FIELDS_DATA['users'];

    echo '<div id="tab_newpoints">';

    $form_container = new \FormContainer($lang->newpoints_users_title . ': ' . htmlspecialchars_uni($user['username']));

    $hook_arguments = [
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('admin_user_users_edit_graph', $hook_arguments);

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (!isset($data_field_data['formType'])) {
            continue;
        }

        $setting_language_string = $data_field_key;

        if (strpos($data_field_key, 'newpoints_user_') !== 0) {
            $setting_language_string = 'newpoints_user_' . str_replace('newpoints_', '', $data_field_key);
        }

        $value = $mybb->get_input($data_field_key, \MyBB::INPUT_INT);

        switch ($data_field_data['formType']) {
            case FORM_TYPE_CHECK_BOX:
                $form_fields[] = $form->generate_check_box(
                    $data_field_key,
                    1,
                    $lang->{$setting_language_string},
                    ['checked' => $value]
                );
                break;
            case FORM_TYPE_NUMERIC_FIELD:
                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $value = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
                }

                $form_fields[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                        $data_field_key,
                        $value,
                        [
                            'min' => isset($data_field_data['formOptions']) ? ($data_field_data['formOptions']['min'] ?? 0) : 0,
                            'step' => isset($data_field_data['formOptions']) ? ($data_field_data['formOptions']['step'] ?? 1) : 1,
                        ]
                    );
                break;
        }
    }

    if (empty($form_fields)) {
        return false;
    }

    $hook_arguments = run_hooks('admin_user_users_edit_graph_intermediate', $hook_arguments);

    $form_container->output_row(
        $lang->newpoints_forums,
        '',
        "<div class=\"user_settings_bit\">" . implode(
            "</div><div class=\"user_settings_bit\">",
            $form_fields
        ) . '</div>'
    );

    $hook_arguments = run_hooks('admin_user_users_edit_graph_end', $hook_arguments);

    $form_container->end();

    echo "</div>\n";

    return true;
}

function admin_user_users_edit_start()
{
    global $newpoints_user_update;

    $newpoints_user_update = true;

    return true;
}

function datahandler_user_validate(\userDataHandler $data_handler): \userDataHandler
{
    global $newpoints_user_update;

    if (empty($newpoints_user_update)) {
        return $data_handler;
    }

    global $mybb;

    $data_fields = FIELDS_DATA['users'];

    $hook_arguments = [
        'data_handler' => &$data_handler,
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('datahandler_user_validate', $hook_arguments);

    $user_data = &$data_handler->data;

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (!isset($data_field_data['formType'])) {
            continue;
        }

        switch ($data_field_data['formType']) {
            case FORM_TYPE_CHECK_BOX:
                $user_data[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_INT);
                break;
            case FORM_TYPE_NUMERIC_FIELD:
                if (!isset($mybb->input[$data_field_key])) {
                    break;
                }

                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $user_data[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
                } else {
                    $user_data[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_INT);
                }
        }
    }

    return $data_handler;
}

function datahandler_user_update(\userDataHandler $data_handler): \userDataHandler
{
    $data_fields = FIELDS_DATA['users'];

    $hook_arguments = [
        'data_handler' => &$data_handler,
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('datahandler_user_update', $hook_arguments);

    $user_data = &$data_handler->data;

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (!isset($data_field_data['formType']) || !isset($user_data[$data_field_key])) {
            continue;
        }

        $data_handler->user_update_data[$data_field_key] = $user_data[$data_field_key];
    }

    return $data_handler;
}