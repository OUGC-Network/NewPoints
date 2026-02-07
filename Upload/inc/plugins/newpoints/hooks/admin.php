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

namespace NewPoints\Hooks\Admin;

use Exception;
use FormContainer;
use MyBB;
use NewPoints\System\Url;

use function NewPoints\Core\instance_object;
use function NewPoints\Core\get_setting;
use function NewPoints\Core\instance_get;
use function NewPoints\Core\language_load;
use function NewPoints\Core\load_set_guest_data;
use function NewPoints\Core\log_error;
use function NewPoints\Core\run_hooks;
use function NewPoints\Admin\recount_rebuild_newpoints_recount;
use function NewPoints\Admin\recount_rebuild_newpoints_recount_from_logs;
use function NewPoints\Admin\recount_rebuild_newpoints_reset;

use const NewPoints\ROOT;
use const NewPoints\Core\FIELDS_DATA;
use const NewPoints\Core\FORM_TYPE_CHECK_BOX;
use const NewPoints\Core\FORM_TYPE_CHECK_BOX_LEGACY;
use const NewPoints\Core\FORM_TYPE_NUMERIC_FIELD;
use const NewPoints\Core\FORM_TYPE_NUMERIC_FIELD_LEGACY;
use const NewPoints\Core\FORM_TYPE_PHP_CODE;
use const NewPoints\Core\FORM_TYPE_PHP_CODE_LEGACY;
use const NewPoints\Core\FORM_TYPE_SELECT_FIELD;
use const NewPoints\Core\FORM_TYPE_SELECT_FIELD_LEGACY;

function admin_config_plugins_deactivate(): bool
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') !== 'deactivate' ||
        $mybb->get_input('plugin') !== 'newpoints' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
        return false;
    }

    if ($mybb->request_method !== 'post') {
        $page->output_confirm_action(
            (new Url('index.php'))
                ->build([
                    'module' => 'config-plugins',
                    'action' => 'deactivate',
                    'uninstall' => 1,
                    'plugin' => 'newpoints',
                ]),
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function admin_config_settings_begin(): void
{
    global $cache;

    language_load();

    $plugins_list = $cache->read('newpoints_plugins_versions');

    foreach ($plugins_list as $plugin => $b) {
        if ($plugin && $plugin = str_replace('newpoints_', '', $plugin)) {
            language_load($plugin, false, true);
        }
    }
}

function admin_load(): bool
{
    load_set_guest_data();

    global $newpoints_globals;
    global $newpoints_user_balance_formatted, $mypoints;

    try {
        foreach (instance_get() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id);

                $newpoints_globals[$instance->users_column_get() . '_user_balance_formatted'] =
                $newpoints_user_balance_formatted = $mypoints =
                    $instance->points_format($instance->get_user_column_value());
            } catch (Exception $e) {
                log_error($instance_id, $e->getMessage());
            }
        }
    } catch (Exception $e) {
    }

    run_hooks('admin_load');

    return true;
}

function admin_tabs(array $modules): array
{
    return [];
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

function newpoints_admin_menu(array &$sub_menu_items): array
{
    // as plugins can't hook to admin_newpoints_menu, we must allow them to hook to newpoints_admin_newpoints_menu
    $sub_menu_items = run_hooks('admin_newpoints_menu', $sub_menu_items);

    return $sub_menu_items;
}

function newpoints_admin_action_handler(array &$action_handlers): array
{
    // as plugins can't hook to admin_newpoints_action_handler, we must allow them to hook to newpoints_newpoints_action_handler
    $action_handlers = run_hooks('admin_newpoints_action_handler', $action_handlers);

    return $action_handlers;
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

    $fields_data = FIELDS_DATA['usergroups'];

    echo '<div id="tab_newpoints">';

    $form_container = new FormContainer($lang->newpoints_groups_tab);

    $form_fields = $form_fields_rate = $form_fields_income = [];

    $hook_arguments = [
        'fields_data' => &$fields_data,
        'data_fields' => &$fields_data,
        'form_fields' => &$form_fields,
        'form_fields_rate' => &$form_fields_rate,
        'form_fields_income' => &$form_fields_income
    ];

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_start', $hook_arguments);

    foreach ($fields_data as $data_field_key => $data_field_data) {
        $data_field_data['form_type'] = $data_field_data['form_type'] ?? ($data_field_data['form_type'] ?? null);

        if (empty($data_field_data['form_type'])) {
            continue;
        }

        if (my_strpos($data_field_key, 'newpoints_income') === 0) {
            // usergroup or forums permissions match global settings only, so settings_get_value() is not necessary
            if (get_setting(str_replace('newpoints_', '', $data_field_key)) !== false) {
                continue;
            }
        }

        $setting_language_string = $data_field_key;

        if (strpos($data_field_key, 'newpoints_user_groups_') !== 0) {
            $setting_language_string = str_replace('newpoints_', 'newpoints_user_groups_', $data_field_key);
        }

        $value = $mybb->get_input($data_field_key, MyBB::INPUT_INT);

        $form_options = [];

        if (isset($data_field_data['formOptions'])) {
            $data_field_data['form_options'] = array_merge(
                $data_field_data['formOptions'],
                $data_field_data['form_options'] ?? []
            );
        }

        if (isset($data_field_data['form_options']['min'])) {
            $form_options['min'] = $data_field_data['form_options']['min'];
        } else {
            $form_options['min'] = 0;
        }

        if (isset($data_field_data['form_options']['step'])) {
            $form_options['step'] = $data_field_data['form_options']['step'];
        } else {
            $form_options['step'] = 1;
        }

        if (isset($data_field_data['form_options']['max'])) {
            $form_options['max'] = $data_field_data['form_options']['max'];
        }

        switch ($data_field_data['form_type']) {
            case FORM_TYPE_CHECK_BOX:
            case FORM_TYPE_CHECK_BOX_LEGACY:
                if (my_strpos($data_field_key, 'newpoints_rate') === 0) {
                    $form_fields_rate[] = $form->generate_check_box(
                        $data_field_key,
                        1,
                        $lang->{$setting_language_string},
                        ['checked' => $value]
                    );
                } elseif (my_strpos($data_field_key, 'newpoints_income') === 0) {
                    $form_fields_income[] = $form->generate_check_box(
                        $data_field_key,
                        1,
                        $lang->{$setting_language_string},
                        ['checked' => $value]
                    );
                } else {
                    $form_fields[] = $form->generate_check_box(
                        $data_field_key,
                        1,
                        $lang->{$setting_language_string},
                        ['checked' => $value]
                    );
                }

                break;
            case FORM_TYPE_NUMERIC_FIELD:
            case FORM_TYPE_NUMERIC_FIELD_LEGACY:
                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $value = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
                }

                if (my_strpos($data_field_key, 'newpoints_rate') === 0) {
                    $form_fields_rate[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                            $data_field_key,
                            $value,
                            $form_options
                        );
                } elseif (my_strpos($data_field_key, 'newpoints_income') === 0) {
                    $form_fields_income[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                            $data_field_key,
                            $value,
                            $form_options
                        );
                } else {
                    $form_fields[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                            $data_field_key,
                            $value,
                            $form_options
                        );
                }

                break;
            case FORM_TYPE_SELECT_FIELD:
            case FORM_TYPE_SELECT_FIELD_LEGACY:
                if (in_array($data_field_data['type'], ['BIGINT', 'INT', 'SMALLINT', 'TINYINT'])) {
                    $value = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
                }

                if (is_callable($data_field_data['formFunction'] ?? '')) {
                    $options_list = $data_field_data['formFunction']();
                } else {
                    $options_list = [];
                }

                if (my_strpos($data_field_key, 'newpoints_rate') === 0) {
                    $form_fields_rate[] = $lang->{$setting_language_string} . $form->generate_select_box(
                            $data_field_key,
                            $options_list,
                            [$value],
                            $form_options
                        );
                } elseif (my_strpos($data_field_key, 'newpoints_income') === 0) {
                    $form_fields_income[] = $lang->{$setting_language_string} . $form->generate_select_box(
                            $data_field_key,
                            $options_list,
                            [$value],
                            $form_options
                        );
                } else {
                    $form_fields[] = $lang->{$setting_language_string} . $form->generate_select_box(
                            $data_field_key,
                            $options_list,
                            [$value],
                            $form_options
                        );
                }

                break;
        }
    }

    if (empty($form_fields) && empty($form_fields_rate) && empty($form_fields_income)) {
        return false;
    }

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_intermediate', $hook_arguments);

    if (!empty($form_fields)) {
        $form_container->output_row(
            $lang->newpoints_groups_users,
            '',
            '<div class="group_settings_bit">' . implode(
                '</div><div class="group_settings_bit">',
                $form_fields
            ) . '</div>'
        );
    }

    if (!empty($form_fields_rate)) {
        $form_container->output_row(
            $lang->newpoints_groups_users_rate,
            '',
            '<div class="group_settings_bit">' . implode(
                '</div><div class="group_settings_bit">',
                $form_fields_rate
            ) . '</div>'
        );
    }

    if (!empty($form_fields_income)) {
        $form_container->output_row(
            $lang->newpoints_groups_users_income,
            '',
            '<div class="group_settings_bit">' . implode(
                '</div><div class="group_settings_bit">',
                $form_fields_income
            ) . '</div>'
        );
    }

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_end', $hook_arguments);

    $form_container->end();

    echo '</div>';

    return true;
}

function admin_user_groups_edit_commit(): bool
{
    global $mybb, $db;
    global $updated_group;

    $fields_data = FIELDS_DATA['usergroups'];

    $hook_arguments = [
        'fields_data' => &$fields_data,
        'data_fields' => &$fields_data,
    ];

    $hook_arguments = run_hooks('admin_user_groups_edit_commit_start', $hook_arguments);

    foreach ($fields_data as $data_field_key => $data_field_data) {
        if (in_array($data_field_data['type'], ['BIGINT', 'INT', 'SMALLINT', 'TINYINT'])) {
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
        !isset($lang->additional_forum_options) ||
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

    $fields_data = FIELDS_DATA['forums'];

    $form_fields = $form_fields_rate = [];

    $hook_arguments = [
        'fields_data' => &$fields_data,
        'data_fields' => &$fields_data,
        'form_fields' => &$form_fields,
        'form_fields_rate' => &$form_fields_rate
    ];

    $hook_arguments = run_hooks('admin_formcontainer_end_start', $hook_arguments);

    foreach ($fields_data as $data_field_key => $data_field_data) {
        $data_field_data['form_type'] = $data_field_data['form_type'] ?? ($data_field_data['form_type'] ?? null);

        if (empty($data_field_data['form_type'])) {
            continue;
        }

        $setting_language_string = $data_field_key;

        if (!str_starts_with($data_field_key, 'newpoints_forum_setting_')) {
            $setting_language_string = str_replace('newpoints_', 'newpoints_forum_setting_', $data_field_key);
        }

        //backwards compatibility, to be removed in future versions
        if (!isset($lang->{$setting_language_string}) && !str_starts_with($data_field_key, 'newpoints_forums_')) {
            $setting_language_string = str_replace('newpoints_', 'newpoints_forums_', $data_field_key);
        }

        $form_options = [];

        if (isset($data_field_data['formOptions'])) {
            $data_field_data['form_options'] = array_merge(
                $data_field_data['formOptions'],
                $data_field_data['form_options'] ?? []
            );
        }

        if (isset($data_field_data['form_options']['min'])) {
            $form_options['min'] = $data_field_data['form_options']['min'];
        } else {
            $form_options['min'] = 0;
        }

        if (isset($data_field_data['form_options']['step'])) {
            $form_options['step'] = $data_field_data['form_options']['step'];
        } else {
            $form_options['step'] = 1;
        }

        if (isset($data_field_data['form_options']['max'])) {
            $form_options['max'] = $data_field_data['form_options']['max'];
        }

        switch ($data_field_data['form_type']) {
            case FORM_TYPE_CHECK_BOX:
            case FORM_TYPE_CHECK_BOX_LEGACY:
                $value = (int)$forum_data[$data_field_key];

                if (my_strpos($data_field_key, 'newpoints_rate') === 0) {
                    $form_fields_rate[] = $form->generate_check_box(
                        $data_field_key,
                        1,
                        $lang->{$setting_language_string},
                        ['checked' => $value]
                    );
                } else {
                    $form_fields[] = $form->generate_check_box(
                        $data_field_key,
                        1,
                        $lang->{$setting_language_string},
                        ['checked' => $value]
                    );
                }
                break;
            case FORM_TYPE_NUMERIC_FIELD:
            case FORM_TYPE_NUMERIC_FIELD_LEGACY:
                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $value = (float)$forum_data[$data_field_key];
                } else {
                    $value = (int)$forum_data[$data_field_key];
                }

                if (my_strpos($data_field_key, 'newpoints_rate') === 0) {
                    $form_fields_rate[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                            $data_field_key,
                            $value,
                            $form_options
                        );
                } else {
                    $form_fields[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                            $data_field_key,
                            $value,
                            $form_options
                        );
                }
                break;
        }
    }

    if (empty($form_fields) && empty($form_fields_rate)) {
        return $current_hook_arguments;
    }

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_intermediate', $hook_arguments);

    if (!empty($form_fields)) {
        $current_hook_arguments['this']->output_row(
            $lang->newpoints_forums,
            '',
            "<div class=\"forum_settings_bit\">" . implode(
                "</div><div class=\"forum_settings_bit\">",
                $form_fields
            ) . '</div>'
        );
    }

    if (!empty($form_fields_rate)) {
        $current_hook_arguments['this']->output_row(
            $lang->newpoints_forums_rates,
            '',
            "<div class=\"forum_settings_bit\">" . implode(
                "</div><div class=\"forum_settings_bit\">",
                $form_fields_rate
            ) . '</div>'
        );
    }

    $hook_arguments = run_hooks('admin_user_groups_edit_graph_end', $hook_arguments);

    return $current_hook_arguments;
}

function admin_forum_management_edit_commit(): bool
{
    global $db, $mybb, $fid;

    $fields_data = FIELDS_DATA['forums'];

    $hook_arguments = [
        'fields_data' => &$fields_data,
        'data_fields' => &$fields_data,
    ];

    $hook_arguments = run_hooks('admin_forum_management_edit_commit_start', $hook_arguments);

    $updated_forum = [];

    foreach ($fields_data as $data_field_key => $data_field_data) {
        if (in_array($data_field_data['type'], ['BIGINT', 'INT', 'SMALLINT', 'TINYINT'])) {
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

function admin_forum_management_permission_groups(array &$groups): array
{
    global $hidefields;

    language_load();

    $fields_data = FIELDS_DATA['forumpermissions'];

    foreach ($fields_data as $field_name => $field_definition) {
        if (!empty($field_definition['form_options']) && !empty($field_definition['form_options']['disabled_for_guest_group'])) {
            global $usergroup;

            if ((int)$usergroup['gid'] === 1) {
                $hidefields[] = $field_name;
            }
        }

        $groups[$field_name] = 'newpoints';
    }

    return $groups;
}

function admin_formcontainer_output_row(array &$hook_arguments): array
{
    global $lang, $page;
    global $usergroup, $forum, $group;

    if ($page->active_module !== 'forum' ||
        $page->active_action !== 'management' ||
        !isset($lang->custom_permissions_for) ||
        !str_contains($hook_arguments['this']->_title, $lang->custom_permissions_for) ||
        !isset($usergroup['title']) ||
        !str_contains($hook_arguments['this']->_title, htmlspecialchars_uni($usergroup['title'])) ||
        !isset($forum['name']) ||
        !str_contains($hook_arguments['this']->_title, htmlspecialchars_uni($forum['name'])) ||
        empty($group) ||
        $group !== 'newpoints'
    ) {
        return $hook_arguments;
    }


    global $form, $lang;
    global $permission_data, $fields;

    $fields_data = FIELDS_DATA['forumpermissions'];

    $fields = [];

    (function () use (&$fields_data, &$permission_data, &$fields) {
        $hook_arguments = [
            'fields_data' => &$fields_data,
            'permission_data' => &$permission_data,
            'fields' => &$fields
        ];

        $hook_arguments = run_hooks('admin_forum_permissions_start', $hook_arguments);
    })();

    foreach ($fields_data as $field_name => $field_definition) {
        if (empty($field_definition['form_type'])) {
            continue;
        }

        $lang_field = str_replace('newpoints_', 'newpoints_field_newpoints_', $field_name);

        switch ($field_definition['form_type']) {
            case FORM_TYPE_NUMERIC_FIELD:
                $fields[] = "{$lang->{$lang_field}}<br /><small class=\"input\">{$lang->{"{$lang_field}_description"}}</small><br />" . $form->generate_numeric_field(
                        "permissions[{$field_name}]",
                        $permission_data[$field_name] ?? 0,
                        $field_definition['form_options']
                    );
                break;
            case FORM_TYPE_CHECK_BOX:
                $fields[] = $form->generate_check_box(
                    "permissions[{$field_name}]",
                    1,
                    $lang->{$lang_field},
                    array_merge(
                        $field_definition['form_options'] ?? [],
                        ['checked' => !empty($permission_data[$field_name]), 'id' => $field_name]
                    )
                );
                break;
        }
    }

    $hook_arguments['content'] = '<div class="forum_settings_bit">' . implode(
            '</div><div class="forum_settings_bit">',
            $fields
        ) . '</div>';

    return $hook_arguments;
}

function admin_forum_management_permissions_commit(): void
{
    global $mybb;

    if (!isset($mybb->input['permissions'])) {
        return;
    }

    $fields_data = FIELDS_DATA['forumpermissions'];

    $hook_arguments = [
        'fields_data' => &$fields_data,
        'permissions' => &$mybb->input['permissions'],
    ];

    $hook_arguments = run_hooks('admin_forum_permissions_commit', $hook_arguments);

    global $db;
    global $update_array;

    foreach ($fields_data as $field_name => $field_definition) {
        if (isset($mybb->input['permissions'][$field_name])) {
            $update_array[$field_name] = match ($field_definition['type']) {
                'BIGINT', 'INT', 'SMALLINT', 'TINYINT' => (int)$mybb->input['permissions'][$field_name],
                'FLOAT', 'DECIMAL' => (float)$mybb->input['permissions'][$field_name],
                default => $db->escape_string($mybb->input['permissions'][$field_name]),
            };
        } else {
            $update_array[$field_name] = 0;
        }
    }
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

    $fields_data = FIELDS_DATA['users'];

    echo '<div id="tab_newpoints">';

    $form_container = new FormContainer($lang->newpoints_users_title . ': ' . htmlspecialchars_uni($user['username']));

    $hook_arguments = [
        'fields_data' => &$fields_data,
        'data_fields' => &$fields_data,
    ];

    $hook_arguments = run_hooks('admin_user_users_edit_graph', $hook_arguments);

    foreach ($fields_data as $data_field_key => $data_field_data) {
        $data_field_data['form_type'] = $data_field_data['form_type'] ?? ($data_field_data['form_type'] ?? null);

        if (empty($data_field_data['form_type'])) {
            continue;
        }

        $setting_language_string = $data_field_key;

        if (strpos($data_field_key, 'newpoints_user_') !== 0) {
            $setting_language_string = 'newpoints_user_' . str_replace('newpoints_', '', $data_field_key);
        }

        $value = $mybb->get_input($data_field_key, MyBB::INPUT_INT);

        $form_options = [];

        if (isset($data_field_data['formOptions'])) {
            $data_field_data['form_options'] = array_merge(
                $data_field_data['formOptions'],
                $data_field_data['form_options'] ?? []
            );
        }

        if (isset($data_field_data['form_options']['min'])) {
            $form_options['min'] = $data_field_data['form_options']['min'];
        } else {
            $form_options['min'] = 0;
        }

        if (isset($data_field_data['form_options']['step'])) {
            $form_options['step'] = $data_field_data['form_options']['step'];
        } else {
            $form_options['step'] = 1;
        }

        if (isset($data_field_data['form_options']['max'])) {
            $form_options['max'] = $data_field_data['form_options']['max'];
        }

        switch ($data_field_data['form_type']) {
            case FORM_TYPE_CHECK_BOX:
            case FORM_TYPE_CHECK_BOX_LEGACY:
                $form_fields[] = $form->generate_check_box(
                    $data_field_key,
                    1,
                    $lang->{$setting_language_string},
                    ['checked' => $value]
                );
                break;
            case FORM_TYPE_NUMERIC_FIELD:
            case FORM_TYPE_NUMERIC_FIELD_LEGACY:
                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $value = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
                }

                $form_fields[] = $lang->{$setting_language_string} . $form->generate_numeric_field(
                        $data_field_key,
                        $value,
                        $form_options
                    );
                break;
            case FORM_TYPE_PHP_CODE;
            case FORM_TYPE_PHP_CODE_LEGACY;
                if (function_exists($data_field_data['functionName'])) {
                    $form_fields[] = $data_field_data['functionName'](
                        $data_field_key,
                        $data_field_data,
                        $value,
                        $setting_language_string
                    );
                }
                break;
        }
    }

    if (empty($form_fields)) {
        return false;
    }

    $hook_arguments = run_hooks('admin_user_users_edit_graph_intermediate', $hook_arguments);

    $form_container->output_row(
        "<span style='color: darkred;'>{$lang->newpoints_user_deprecated}:</span>",
    );

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

function admin_user_users_edit_start(): bool
{
    global $newpoints_user_update;

    $newpoints_user_update = true;

    return true;
}

function admin_tools_recount_rebuild_output_list(): bool
{
    global $lang;
    global $form_container, $form;

    $instances_select = $form->generate_select_box(
        'newpoints_recount_from_logs_instance_id',
        (function (): array {
            $instances = [
                0 => ''
            ];

            foreach (instance_get() as $instance_id => $instance_data) {
                try {
                    $instances[$instance_id] = instance_object($instance_id)->get_display_name_upper();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                    );
                }
            }

            return $instances;
        })()
    );

    $form_container->output_cell(
        "<label>{$lang->newpoints_recount_from_logs}</label><div class=\"description\">{$lang->newpoints_recount_from_logs_description}</div>{$instances_select}"
    );

    $form_container->output_cell(
        $form->generate_numeric_field('newpoints_recount_from_logs', 50, ['style' => 'width: 150px;', 'min' => 0])
    );

    $form_container->output_cell($form->generate_submit_button($lang->go, ['name' => 'do_recount_newpoints_from_logs'])
    );

    $form_container->construct_row();

    $instances_select = $form->generate_select_box(
        'newpoints_recount_from_settings_instance_id',
        (function (): array {
            $instances = [
                0 => ''
            ];

            foreach (instance_get() as $instance_id => $instance_data) {
                try {
                    $instances[$instance_id] = instance_object($instance_id)->get_display_name_upper();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                    );
                }
            }

            return $instances;
        })()
    );

    $form_container->output_cell(
        "<label>{$lang->newpoints_recount}</label><div class=\"description\">{$lang->newpoints_recount_desc}</div>{$instances_select}"
    );

    $form_container->output_cell(
        $form->generate_numeric_field('newpoints_recount_from_settings', 50, ['style' => 'width: 150px;', 'min' => 0])
    );

    $form_container->output_cell($form->generate_submit_button($lang->go, ['name' => 'do_recount_newpoints']));

    $form_container->construct_row();

    $instances_select = $form->generate_select_box(
        'newpoints_reset_instance_id',
        (function (): array {
            $instances = [
                0 => ''
            ];

            foreach (instance_get() as $instance_id => $instance_data) {
                try {
                    $instances[$instance_id] = instance_object($instance_id)->get_display_name_upper();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                    );
                }
            }

            return $instances;
        })()
    );

    $form_container->output_cell(
        "<label>{$lang->newpoints_reset}</label><div class=\"description\">{$lang->newpoints_reset_desc}</div>{$instances_select} {$lang->newpoints_reset_amount}:" . $form->generate_numeric_field(
            'newpoints_reset_amount',
            0,
            ['style' => 'width: 100px;', 'min' => 0]
        )
    );

    $form_container->output_cell(
        $form->generate_numeric_field('newpoints_reset', 50, ['style' => 'width: 150px;', 'min' => 0])
    );

    $form_container->output_cell($form->generate_submit_button($lang->go, ['name' => 'do_reset_newpoints']));

    $form_container->construct_row();

    return true;
}

function admin_tools_do_recount_rebuild(): bool
{
    global $mybb;

    if (isset($mybb->input['do_recount_newpoints_from_logs'])) {
        if ($mybb->get_input('page', MyBB::INPUT_INT) === 1) {
            log_admin_action('recount_from_logs');
        }

        $per_page = $mybb->get_input('newpoints_recount_from_logs', MyBB::INPUT_INT);

        if (!$per_page || $per_page <= 0) {
            $mybb->input['newpoints_recount_from_logs'] = 50;
        }

        recount_rebuild_newpoints_recount_from_logs();
    }

    if (isset($mybb->input['do_recount_newpoints'])) {
        if ($mybb->get_input('page', MyBB::INPUT_INT) === 1) {
            log_admin_action('recount');
        }

        $per_page = $mybb->get_input('newpoints_recount_from_settings', MyBB::INPUT_INT);

        if (!$per_page || $per_page <= 0) {
            $mybb->input['newpoints_recount_from_settings'] = 50;
        }

        recount_rebuild_newpoints_recount();
    }

    if (isset($mybb->input['do_reset_newpoints'])) {
        if ($mybb->get_input('page', MyBB::INPUT_INT) === 1) {
            log_admin_action('reset');
        }

        $per_page = $mybb->get_input('newpoints_reset', MyBB::INPUT_INT);

        if (!$per_page || $per_page <= 0) {
            $mybb->input['newpoints_reset'] = 50;
        }

        recount_rebuild_newpoints_reset();
    }

    return true;
}