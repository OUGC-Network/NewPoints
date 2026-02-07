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

use NewPoints\System\Url;

use function NewPoints\Admin\build_permissions_row;
use function NewPoints\Admin\db_verify_columns;
use function NewPoints\Admin\retrieve_single_forum_permissions_row;
use function NewPoints\Admin\retrieve_single_group_permissions_row;
use function NewPoints\Admin\save_quick_forum_permissions;
use function NewPoints\Admin\save_quick_group_permissions;
use function NewPoints\Core\cache_update_instances;
use function NewPoints\Core\instance_get;
use function NewPoints\Core\instance_insert;
use function NewPoints\Core\instance_object;
use function NewPoints\Core\instance_update;
use function NewPoints\Core\language_load;
use function NewPoints\Core\log_error;
use function NewPoints\Core\main_file_name;
use function NewPoints\Core\run_hooks;

use const NewPoints\Core\FIELDS_DATA;
use const NewPoints\Core\FORM_TYPE_CHECK_BOX;
use const NewPoints\Core\FORM_TYPE_NUMERIC_FIELD;
use const NewPoints\Core\INSTANCE_DEFAULT_ID;
use const NewPoints\Core\TABLES_DATA;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb;

language_load();

$url = new Url('index.php');

$url = $url->set_url($url->build([
    'module' => 'newpoints-instances',
]));

$sub_tabs = [
    'newpoints_instances' => [
        'title' => $lang->newpoints_instances,
        'link' => $url->build(),
        'description' => $lang->newpoints_instances_description
    ],
];

$instance_id = $mybb->get_input('instance_id', MyBB::INPUT_INT);

if (!$mybb->get_input('action') || $mybb->get_input('action') === 'add') {
    $sub_tabs['newpoints_instances_add'] = [
        'title' => $lang->newpoints_instances_add,
        'link' => $url->build(['action' => 'add']),
        'description' => $lang->newpoints_instances_add_description
    ];
}

if ($mybb->get_input('action') === 'edit') {
    $sub_tabs['newpoints_instances_edit'] = [
        'title' => $lang->newpoints_instances_edit,
        'link' => $url->build(['action' => 'edit', 'instance_id' => $instance_id]),
        'description' => $lang->newpoints_instances_edit_description
    ];
}

$page->extra_header .= <<<EOL
<style type="text/css">
    .user_settings_bit label {
        font-weight: normal;
    }
</style>
EOL;

$tables_data = TABLES_DATA;

$groups_cache = (array)$mybb->cache->read('usergroups');

$forums_cache = (array)$mybb->cache->read('forums');

$existing_instances = instance_get(query_fields: ['users_column_name']);

run_hooks('admin_instances_begin');

if ($mybb->input['action'] == 'clear_group_permission') {
    $permission_id = $mybb->get_input('permission_id', MyBB::INPUT_INT);

    try {
        $instance = instance_object($instance_id);
    } catch (Exception $e) {
        log_error($instance_id, $e->getMessage());

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');

        exit;
    }

    $permission_data = $instance->permissions_group_get(
        ["instance_id='{$instance->instance_id}'", "permission_id='{$permission_id}'"]
    );

    if (!empty($mybb->input['no']) || !$permission_data) {
        admin_redirect(
            $url->build(
                ['action' => 'edit', 'instance_id' => $instance->instance_id]
            ) . '#tab_group_permissions'
        );
    }

    run_hooks('admin_instances_clear_group_permission_start');

    if ($mybb->request_method === 'post') {
        $instance->permissions_group_delete($permission_id);

        run_hooks('admin_instances_clear_group_permission_commit');

        $instance->cache_update_group_permissions();

        flash_message($lang->newpoints_admin_instances_permissions_clear_success, 'success');

        admin_redirect(
            $url->build(
                ['action' => 'edit', 'instance_id' => $instance->instance_id]
            ) . '#tab_group_permissions'
        );
    } else {
        $page->output_confirm_action(
            $url->build(
                [
                    'action' => 'clear_group_permission',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permission_id,
                    'my_post_key' => $mybb->post_code
                ]
            ),
            $lang->newpoints_admin_instances_permissions_clear_confirm
        );
    }
} elseif ($mybb->input['action'] == 'clear_forum_permission') {
    $permission_id = $mybb->get_input('permission_id', MyBB::INPUT_INT);

    try {
        $instance = instance_object($instance_id);
    } catch (Exception $e) {
        log_error(
            $instance_id,
            $e->getMessage(),
        );

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');

        exit;
    }

    $permission_data = $instance->permissions_forum_get(
        ["instance_id='{$instance->instance_id}'", "permission_id='{$permission_id}'"]
    );

    if (!empty($mybb->input['no']) || !$permission_data) {
        admin_redirect(
            $url->build(
                ['action' => 'edit', 'instance_id' => $instance->instance_id]
            ) . '#tab_forum_permissions'
        );
    }

    run_hooks('admin_instances_clear_forum_permission_start');

    if ($mybb->request_method === 'post') {
        $instance->permissions_forum_delete($permission_id);

        run_hooks('admin_instances_clear_forum_permission_commit');

        $instance->cache_update_forum_permissions();

        flash_message($lang->newpoints_admin_instances_permissions_clear_success, 'success');

        admin_redirect(
            $url->build(
                ['action' => 'edit', 'instance_id' => $instance->instance_id]
            ) . '#tab_forum_permissions'
        );
    } else {
        $page->output_confirm_action(
            $url->build(
                [
                    'action' => 'clear_forum_permission',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permission_id,
                    'my_post_key' => $mybb->post_code
                ]
            ),
            $lang->newpoints_admin_instances_permissions_clear_confirm
        );
    }
} elseif ($mybb->get_input('action') == 'group_permissions') {
    try {
        $instance = instance_object($instance_id);
    } catch (Exception $e) {
        log_error(
            $instance_id,
            $e->getMessage(),
        );

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');

        exit;
    }

    $permissions_cache = $instance->cache_get_group_permissions();

    $group_id = $mybb->get_input('group_id', MyBB::INPUT_INT);

    $permission_id = $mybb->get_input('permission_id', MyBB::INPUT_INT);

    $where_clauses = ["instance_id='{$instance->instance_id}'"];

    if ($group_id) {
        $where_clauses[] = "group_id='{$group_id}'";
    }

    if ($permission_id) {
        $where_clauses[] = "permission_id='{$permission_id}'";
    }

    run_hooks('admin_instances_permissions_start');

    $permission_data = $instance->permissions_group_get(
        $where_clauses,
        array_keys($tables_data['newpoints_group_permissions']),
        ['limit' => 1]
    );

    if (!$group_id && !empty($permission_data['instance_id'])) {
        $permission_id = (int)$permission_data['permission_id'];

        $instance->instance_id = (int)$permission_data['instance_id'];

        $group_id = $permission_data['group_id'];
    }

    $input_permissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

    $permissions_url = $url->build(
            ['action' => 'edit', 'instance_id' => $instance->instance_id]
        ) . '#tab_group_permissions';

    $is_modal = $mybb->get_input('ajax', MyBB::INPUT_INT);

    if ($mybb->request_method === 'post') {
        $insert_data = $field_list = [];

        foreach ($tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
            if (!isset($field_definition['is_permission'])) {
                continue;
            }

            if (isset($input_permissions[$field_name])) {
                $insert_data[$field_name] = $db->escape_string($input_permissions[$field_name]);
            } else {
                $insert_data[$field_name] = $field_definition['default'];
            }
        }

        if (!$permission_id) {
            $insert_data['instance_id'] = $instance->instance_id;

            $insert_data['group_id'] = $group_id;
        }

        $input_permissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

        run_hooks('admin_instances_permissions_commit');

        if ($permission_id) {
            $instance->permissions_group_update($insert_data, $permission_id);
        } else {
            $instance->permissions_group_insert($insert_data);
        }

        $instance->cache_update_group_permissions();

        log_admin_action($instance->instance_id, $instance->get_display_name_upper());

        if ($is_modal) {
            echo json_encode(
                "<script type=\"text/javascript\">$('#row_{$group_id}').html('" . str_replace(["'", "\t", "\n"],
                    ["\\'", '', ''],
                    retrieve_single_group_permissions_row($group_id, $instance->instance_id)
                ) . "'); QuickPermEditor.init('group_' + {$group_id})</script>"
            );

            die;
        } else {
            flash_message($lang->newpoints_admin_instances_permissions_form_custom_permissions_success, 'success');

            admin_redirect($permissions_url);
        }
    }

    if (!$is_modal) {
        $page->add_breadcrumb_item($lang->newpoints_breadcrumb_instances, $url->build());

        $page->add_breadcrumb_item($instance->get_display_name_upper(), $url->get_url());

        $page->add_breadcrumb_item($lang->newpoints_breadcrumb_instances_custom_permissions);

        $page->extra_header .= "<script src=\"jscripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

        $permissions_url = $url->build(
                [
                    'action' => 'group_permissions',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permission_id,
                    'group_id' => $group_id
                ]
            ) . '#tab_group_permissions';

        $sub_tabs['group_permissions'] = [
            'title' => $lang->newpoints_admin_instances_permissions_form_custom_permissions,
            'link' => $permissions_url,
            'description' => $lang->newpoints_admin_instances_permissions_form_custom_permissions_description
        ];

        $page->output_header($lang->newpoints_admin_instances_permissions_form_custom_permissions);

        $page->output_nav_tabs($sub_tabs, 'group_permissions');
    } else {
        echo "
		<div class=\"modal\" style=\"width: auto\">
		<script src=\"jscripts/tabs.js\" type=\"text/javascript\"></script>\n
		<script type=\"text/javascript\">
<!--
$(function() {
	$(\"#modal_form\").on(\"click\", \"#save_permissions\", function(e) {
		e.preventDefault();

		var datastring = $(\"#modal_form\").serialize();
		$.ajax({
			type: \"POST\",
			url: $(\"#modal_form\").attr('action'),
			data: datastring,
			dataType: \"json\",
			success: function(data) {
				$(data).filter(\"script\").each(function(e) {
					eval($(this).text());
				});
				$.modal.close();
			},
			error: function(){
			}
		});
	});
});
// -->
		</script>
		<div style=\"overflow-y: auto; max-height: 400px\">";
    }

    if (!empty($mybb->input['permission_id']) || (!empty($mybb->input['group_id']) && !empty($mybb->input['instance_id']))) {
        if (!$is_modal) {
            $permissions_url = $url->build(
                    [
                        'action' => 'group_permissions',
                        'instance_id' => $instance->instance_id,
                    ]
                ) . '#tab_group_permissions';

            $form = new Form($permissions_url, 'post');
        } else {
            $permissions_url = $url->build(
                    [
                        'action' => 'group_permissions',
                        'instance_id' => $instance->instance_id,
                        'permission_id' => $permission_id,
                        'group_id' => $group_id,
                        'ajax' => 1
                    ]
                ) . '#tab_group_permissions';

            $form = new Form(
                $permissions_url, 'post', 'modal_form'
            );
        }

        echo $form->generate_hidden_field('use_custom_permissions', '1');

        $permission_data = $instance->permissions_group_get(
            $where_clauses,
            array_keys($tables_data['newpoints_group_permissions']),
            ['limit' => 1]
        );

        if (!empty($permission_data['permission_id'])) {
            $permission_data['use_custom_permissions'] = 1;
        } elseif (!isset($permission_data['permission_id'])) {
            $permission_data = usergroup_permissions($group_id);

            foreach ($permission_data as $permission_key => $permission_value) {
                if (str_starts_with($permission_key, 'newpoints_')) {
                    $permission_data[str_replace('newpoints_', '', $permission_key)] = $permission_value;
                }
            }
        } else {
            $permission_data = $permissions_cache[$instance->instance_id][$group_id];
        }

        if ($group_id) {
            echo $form->generate_hidden_field('group_id', $group_id);
        }

        if ($permission_id) {
            echo $form->generate_hidden_field('permission_id', $permission_id);
        }

        $field_list = [];

        foreach ($tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
            if (!isset($field_definition['is_permission'])) {
                continue;
            }

            $language_key = str_replace('newpoints_', '', $field_name);

            $field_list[$field_definition['form_category']][$field_name] = $lang->{'newpoints_permission_group_' . $language_key};
        }

        $permission_tabs = [];

        foreach (array_keys($field_list) as $tab_key) {
            $language_key = str_replace('newpoints_', '', $tab_key);

            $permission_tabs[$tab_key] = $lang->{'newpoints_permission_group_' . $language_key};
        }

        if ($is_modal) {
            $page->output_tab_control($permission_tabs, false, 'tabs2');
        } else {
            $page->output_tab_control($permission_tabs);
        }

        $existing_permissions = [];

        if (isset($permissions_cache[$instance->instance_id])) {
            foreach ($permissions_cache[$instance->instance_id] as $instance_permissions) {
                $existing_permissions[$instance_permissions['group_id']] = $instance_permissions;
            }
        }

        if (!$existing_permissions) {
            $default_checked = true;
        }

        foreach (array_keys($field_list) as $tab_key) {
            $lang_group = 'group_' . $tab_key;

            echo "<div id=\"tab_" . $tab_key . "\">\n";

            $form_container = new FormContainer(
                "\"" . htmlspecialchars_uni(
                    $groups_cache[$group_id]['title']
                ) . "\" " . $lang->newpoints_admin_instances_permissions_form_custom_permissions
            );

            $fields = [];

            foreach ($field_list[$tab_key] as $permission_name => $permission_title) {
                $field_definition = $tables_data['newpoints_group_permissions'][$permission_name];

                $language_key = str_replace('newpoints_', '', $permission_name);

                $form_options = ($field_definition['form_options'] ?? []);

                $permission_data[$permission_name] = match ($field_definition['type']) {
                    'BIGINT', 'INT', 'SMALLINT', 'TINYINT' => (int)$permission_data[$permission_name],
                    'FLOAT', 'DECIMAL' => (float)$permission_data[$permission_name],
                    default => htmlspecialchars_uni($permission_data[$permission_name]),
                };

                switch ($field_definition['form_type']) {
                    case FORM_TYPE_NUMERIC_FIELD:
                        $form_input = '<div class="permissions_bit">';

                        $form_input .= $lang->{'newpoints_permission_group_' . $language_key};

                        $form_input .= '<br /><small class="input">';

                        $form_input .= $lang->{'newpoints_permission_group_' . $language_key . '_description'};

                        $form_input .= '</small><br />';

                        $form_input .= $form->generate_numeric_field(
                            "permissions[{$permission_name}]",
                            $permission_data[$permission_name],
                            array_merge(
                                $form_options,
                                ['id' => $permission_name, 'class' => $field_definition['form_class'] ?? '']
                            )
                        );

                        $form_input .= '</div>';

                        $fields[] = $form_input;

                        break;
                    case FORM_TYPE_CHECK_BOX:
                        $fields[] = $form->generate_check_box(
                            "permissions[{$permission_name}]",
                            1,
                            $permission_title,
                            array_merge(
                                $form_options,
                                ['checked' => !empty($permission_data[$permission_name]), 'id' => $permission_name]
                            )
                        );

                        break;
                }
            }

            $form_container->output_row(
                '',
                '',
                "<div class=\"forum_settings_bit\">" . implode(
                    "</div><div class=\"forum_settings_bit\">",
                    $fields
                ) . '</div>'
            );

            $form_container->end();

            echo '</div>';
        }

        if ($is_modal) {
            $form->output_submit_wrapper([
                $form->generate_submit_button(
                    $lang->cancel,
                    ['onclick' => '$.modal.close(); return false;']
                ),
                $form->generate_submit_button(
                    $lang->newpoints_admin_instances_permissions_form_save_groups,
                    ['id' => 'save_permissions']
                )
            ]);

            $form->end();

            echo '</div>';

            echo '</div>';
        } else {
            $form->output_submit_wrapper(
                [$form->generate_submit_button($lang->newpoints_admin_instances_permissions_form_save_groups)]
            );

            $form->end();
        }
    }

    run_hooks('admin_instances_permissions_end');

    if ($is_modal) {
        exit;
    }

    $page->output_footer();
} elseif ($mybb->get_input('action') == 'forum_permissions') {
    try {
        $instance = instance_object($instance_id);
    } catch (Exception $e) {
        log_error(
            $instance_id,
            $e->getMessage(),
        );

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');

        exit;
    }

    $permissions_cache = $instance->cache_get_forum_permissions();

    $forum_id = $mybb->get_input('forum_id', MyBB::INPUT_INT);

    $permission_id = $mybb->get_input('permission_id', MyBB::INPUT_INT);

    $where_clauses = ["instance_id='{$instance->instance_id}'"];

    if ($forum_id) {
        $where_clauses[] = "forum_id='{$forum_id}'";
    }

    if ($permission_id) {
        $where_clauses[] = "permission_id='{$permission_id}'";
    }

    run_hooks('admin_instances_permissions_start');

    $permission_data = $instance->permissions_forum_get(
        $where_clauses,
        array_keys($tables_data['newpoints_forum_permissions']),
        ['limit' => 1]
    );

    if (!$forum_id && !empty($permission_data['instance_id'])) {
        $permission_id = (int)$permission_data['permission_id'];

        $instance->instance_id = (int)$permission_data['instance_id'];

        $forum_id = $permission_data['forum_id'];
    }

    $input_permissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

    $permissions_url = $url->build(
            ['action' => 'edit', 'instance_id' => $instance->instance_id]
        ) . '#tab_forum_permissions';

    $is_modal = $mybb->get_input('ajax', MyBB::INPUT_INT);

    if ($mybb->request_method === 'post') {
        $insert_data = $field_list = [];

        foreach ($tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
            if (!isset($field_definition['is_permission'])) {
                continue;
            }

            if (isset($input_permissions[$field_name])) {
                $insert_data[$field_name] = $db->escape_string($input_permissions[$field_name]);
            } else {
                $insert_data[$field_name] = $field_definition['default'];
            }
        }

        if (!$permission_id) {
            $insert_data['instance_id'] = $instance->instance_id;

            $insert_data['forum_id'] = $forum_id;
        }

        $input_permissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

        run_hooks('admin_instances_permissions_commit');

        if ($permission_id) {
            $instance->permissions_forum_update($insert_data, $permission_id);
        } else {
            $instance->permissions_forum_insert($insert_data);
        }

        $instance->cache_update_forum_permissions();

        log_admin_action($instance->instance_id, $instance->get_display_name_upper());

        if ($is_modal) {
            echo json_encode(
                "<script type=\"text/javascript\">$('#row_{$forum_id}').html('" . str_replace(["'", "\t", "\n"],
                    ["\\'", '', ''],
                    retrieve_single_forum_permissions_row($forum_id, $instance->instance_id)
                ) . "'); QuickPermEditor.init('forum_' + {$forum_id})</script>"
            );

            die;
        } else {
            flash_message($lang->newpoints_admin_instances_permissions_form_custom_permissions_success, 'success');

            admin_redirect($permissions_url);
        }
    }

    if (!$is_modal) {
        $page->add_breadcrumb_item($lang->newpoints_breadcrumb_instances, $url->build());

        $page->add_breadcrumb_item($instance->get_display_name_upper(), $url->get_url());

        $page->add_breadcrumb_item($lang->newpoints_breadcrumb_instances_custom_permissions);

        $page->extra_header .= "<script src=\"jscripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

        $permissions_url = $url->build(
                [
                    'action' => 'forum_permissions',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permission_id,
                    'forum_id' => $forum_id
                ]
            ) . '#tab_forum_permissions';

        $sub_tabs['forum_permissions'] = [
            'title' => $lang->newpoints_admin_instances_permissions_form_custom_permissions,
            'link' => $permissions_url,
            'description' => $lang->newpoints_admin_instances_permissions_form_custom_permissions_description
        ];

        $page->output_header($lang->newpoints_admin_instances_permissions_form_custom_permissions);

        $page->output_nav_tabs($sub_tabs, 'forum_permissions');
    } else {
        echo "
		<div class=\"modal\" style=\"width: auto\">
		<script src=\"jscripts/tabs.js\" type=\"text/javascript\"></script>\n
		<script type=\"text/javascript\">
<!--
$(function() {
	$(\"#modal_form\").on(\"click\", \"#save_permissions\", function(e) {
		e.preventDefault();

		var datastring = $(\"#modal_form\").serialize();
		$.ajax({
			type: \"POST\",
			url: $(\"#modal_form\").attr('action'),
			data: datastring,
			dataType: \"json\",
			success: function(data) {
				$(data).filter(\"script\").each(function(e) {
					eval($(this).text());
				});
				$.modal.close();
			},
			error: function(){
			}
		});
	});
});
// -->
		</script>
		<div style=\"overflow-y: auto; max-height: 400px\">";
    }

    if (!empty($mybb->input['permission_id']) || (!empty($mybb->input['forum_id']) && !empty($mybb->input['instance_id']))) {
        if (!$is_modal) {
            $permissions_url = $url->build(
                    [
                        'action' => 'forum_permissions',
                        'instance_id' => $instance->instance_id,
                    ]
                ) . '#tab_forum_permissions';

            $form = new Form($permissions_url, 'post');
        } else {
            $permissions_url = $url->build(
                    [
                        'action' => 'forum_permissions',
                        'instance_id' => $instance->instance_id,
                        'permission_id' => $permission_id,
                        'forum_id' => $forum_id,
                        'ajax' => 1
                    ]
                ) . '#tab_forum_permissions';

            $form = new Form(
                $permissions_url, 'post', 'modal_form'
            );
        }

        echo $form->generate_hidden_field('use_custom_permissions', '1');

        $permission_data = $instance->permissions_forum_get(
            $where_clauses,
            array_keys($tables_data['newpoints_forum_permissions']),
            ['limit' => 1]
        );

        if (!empty($permission_data['permission_id'])) {
            $permission_data['use_custom_permissions'] = 1;
        } elseif (!isset($permission_data['permission_id'])) {
            $permission_data = $forums_cache[$forum_id];

            foreach ($permission_data as $permission_key => $permission_value) {
                if (str_starts_with($permission_key, 'newpoints_')) {
                    $permission_data[str_replace('newpoints_', '', $permission_key)] = $permission_value;
                }
            }
        } else {
            $permission_data = $permissions_cache[$instance->instance_id][$forum_id];
        }

        if ($forum_id) {
            echo $form->generate_hidden_field('forum_id', $forum_id);
        }

        if ($permission_id) {
            echo $form->generate_hidden_field('permission_id', $permission_id);
        }

        $field_list = [];

        foreach ($tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
            if (!isset($field_definition['is_permission'])) {
                continue;
            }

            $language_key = str_replace('newpoints_', '', $field_name);

            $field_list[$field_definition['form_category']][$field_name] = $lang->{'newpoints_permissions_forum_' . $language_key};
        }

        $permission_tabs = [];

        foreach (array_keys($field_list) as $tab_key) {
            $language_key = str_replace('newpoints_', '', $tab_key);

            $permission_tabs[$tab_key] = $lang->{'newpoints_forums_' . $language_key};
        }

        if ($is_modal) {
            $page->output_tab_control($permission_tabs, false, 'tabs2');
        } else {
            $page->output_tab_control($permission_tabs);
        }

        $existing_permissions = [];

        if (isset($permissions_cache[$instance->instance_id])) {
            foreach ($permissions_cache[$instance->instance_id] as $instance_permissions) {
                if (!empty($instance_permissions['forum_id'])) {
                    $existing_permissions[$instance_permissions['forum_id']] = $instance_permissions;
                }
            }
        }

        if (!$existing_permissions) {
            $default_checked = true;
        }

        foreach (array_keys($field_list) as $tab_key) {
            $lang_forum = 'forum_' . $tab_key;

            echo "<div id=\"tab_" . $tab_key . "\">\n";

            $form_container = new FormContainer(
                "\"" . strip_tags(
                    $forums_cache[$forum_id]['name']
                ) . "\" " . $lang->newpoints_admin_instances_permissions_form_custom_permissions
            );

            $fields = [];

            foreach ($field_list[$tab_key] as $permission_name => $permission_title) {
                $field_definition = $tables_data['newpoints_forum_permissions'][$permission_name];

                $language_key = str_replace('newpoints_', '', $permission_name);

                switch ($field_definition['form_type']) {
                    case FORM_TYPE_NUMERIC_FIELD:
                        $form_input = '<div class="permissions_bit">';

                        $form_input .= $lang->{'newpoints_permissions_forum_' . $language_key};

                        $form_input .= '<br /><small class="input">';

                        $form_input .= $lang->{'newpoints_permissions_forum_' . $language_key . '_description'};

                        $form_input .= '</small><br />';

                        $form_input .= $form->generate_numeric_field(
                            "permissions[{$permission_name}]",
                            isset($permission_data[$permission_name]) ? (float)$permission_data[$permission_name] : 0,
                            array_merge(
                                $field_definition['form_options'] ?? [],
                                ['id' => $permission_name, 'class' => $field_definition['form_class'] ?? '']
                            )
                        );

                        $form_input .= '</div>';

                        $fields[] = $form_input;

                        break;
                    case FORM_TYPE_CHECK_BOX:
                        $fields[] = $form->generate_check_box(
                            "permissions[{$permission_name}]",
                            1,
                            $permission_title,
                            ['checked' => !empty($permission_data[$permission_name]), 'id' => $permission_name]
                        );

                        break;
                }
            }

            $form_container->output_row(
                '',
                '',
                "<div class=\"forum_settings_bit\">" . implode(
                    "</div><div class=\"forum_settings_bit\">",
                    $fields
                ) . '</div>'
            );

            $form_container->end();

            echo '</div>';
        }

        if ($is_modal) {
            $form->output_submit_wrapper([
                $form->generate_submit_button(
                    $lang->cancel,
                    ['onclick' => '$.modal.close(); return false;']
                ),
                $form->generate_submit_button(
                    $lang->newpoints_admin_instances_permissions_form_save_forums,
                    ['id' => 'save_permissions']
                )
            ]);

            $form->end();

            echo '</div>';

            echo '</div>';
        } else {
            $form->output_submit_wrapper(
                [$form->generate_submit_button($lang->newpoints_admin_instances_permissions_form_save_forums)]
            );

            $form->end();
        }
    }

    run_hooks('admin_instances_permissions_end');

    if ($is_modal) {
        exit;
    }

    $page->output_footer();
} elseif ($mybb->get_input('action') == 'add' || $mybb->get_input('action') == 'edit') {
    $is_add_page = $mybb->get_input('action') === 'add';

    $permissions_cache = [];

    if (!$is_add_page) {
        try {
            $instance = instance_object($instance_id);
        } catch (Exception $e) {
            flash_message($e->getMessage(), 'error');

            admin_redirect('index.php?module=newpoints-instances');

            exit;
        }

        $permissions_cache = $instance->cache_get_group_permissions();
    }

    $error_messages = [];

    if ($mybb->request_method === 'post') {
        $default_permissions = array_filter($tables_data['newpoints_group_permissions'], function ($field_definition) {
            return isset($field_definition['is_permission']);
        });

        switch ($mybb->get_input('type')) {
            case 'main':
                $insert_data = [];

                foreach ($tables_data['newpoints_instances'] as $field_name => $field_definition) {
                    if (!isset($field_definition['form_type']) ||
                        $field_definition['form_category'] !== $mybb->get_input('type')) {
                        continue;
                    }

                    if (isset($mybb->input[$field_name])) {
                        $insert_data[$field_name] = match ($field_definition['type']) {
                            'BIGINT', 'INT', 'SMALLINT', 'TINYINT' => (int)$mybb->input[$field_name],
                            'FLOAT', 'DECIMAL' => (float)$mybb->input[$field_name],
                            default => $db->escape_string($mybb->input[$field_name]),
                        };
                    } elseif ($field_definition['form_type'] === FORM_TYPE_NUMERIC_FIELD) {
                        $insert_data[$field_name] = $field_definition['default'];
                    }
                }

                if (isset($insert_data['users_column_name']) && (!$insert_data['users_column_name'] || in_array(
                            $insert_data['users_column_name'],
                            array_column($existing_instances, 'users_column_name')
                        ) && (function (
                            string $instance_users_column_name
                        ) use ($instance_id, $existing_instances): bool {
                            $duplicated_users_column_name = false;

                            foreach ($existing_instances as $instance_data) {
                                if ($instance_data['users_column_name'] === $instance_users_column_name && $instance_data['instance_id'] !== $instance_id) {
                                    $duplicated_users_column_name = true;
                                }
                            }

                            return $duplicated_users_column_name;
                        })(
                            $insert_data['users_column_name']
                        ))) {
                    $error_messages[] = $lang->newpoints_admin_instances_error_duplicated_users_column_name;
                }

                if (!$is_add_page &&
                    !$db->field_exists($instance->get_instance_data()['users_column_name'], 'users')) {
                    $insert_data['is_enabled'] = 0;
                }

                global $db;

                run_hooks('admin_instances_add_edit_commit_main');

                cache_update_instances();

                if (!$error_messages) {
                    if ($is_add_page) {
                        $instance_id = instance_insert($insert_data);
                    } else {
                        instance_update($insert_data, $instance->instance_id);
                    }

                    cache_update_instances();

                    if ($is_add_page) {
                        log_admin_action(['instance_id' => $instance_id]);

                        flash_message($lang->newpoints_admin_instances_success_new_instance, 'success');
                    } else {
                        log_admin_action(['instance_id' => $instance->instance_id, 'type' => $mybb->get_input('type')]);

                        flash_message($lang->newpoints_admin_instances_success_updated_instance, 'success');
                    }

                    admin_redirect(
                        $url->build(
                            [
                                'action' => 'edit',
                                'type' => $mybb->get_input('type'),
                                'instance_id' => $instance_id
                            ]
                        ) . '#tab_main'
                    );
                }
                break;
            case 'group_permissions':
            case 'forum_permissions':

                $insert_data = [];

                if (!empty($mybb->input['default_permissions'])) {
                    $inherit = $mybb->input['default_permissions'];
                } else {
                    $inherit = [];
                }

                if ($mybb->get_input('type') === 'group_permissions') {
                    $dragging_type = 'group_';
                } else {
                    $dragging_type = 'forum_';
                }

                foreach ($mybb->input as $permission_name => $permission_value) {
                    // Make sure we're only skipping inputs that don't start with "fields_".$dragging_type and aren't fields_default_ or fields_inherit_
                    if (!str_contains($permission_name, 'fields_' . $dragging_type) ||
                        (str_contains($permission_name, 'fields_default_') ||
                            str_contains($permission_name, 'fields_inherit_'))) {
                        continue;
                    }

                    $object_id = (int)str_replace('fields_' . $dragging_type, '', $permission_name);

                    if ($mybb->input['fields_default_' . $object_id] == $permission_value &&
                        $mybb->input['fields_inherit_' . $object_id]) {
                        $inherit[$object_id] = 1;

                        continue;
                    }

                    $inherit[$object_id] = 0;

                    // If it isn't an array then it came from the javascript form
                    if (!is_array($permission_value)) {
                        $permission_value = explode(',', $permission_value);

                        $permission_value = array_flip($permission_value);

                        foreach ($permission_value as $field_name => $value) {
                            $permission_value[$field_name] = 1;
                        }
                    }

                    if ($mybb->get_input('type') === 'group_permissions') {
                        $permissions_fields = $tables_data['newpoints_group_permissions'];
                    } else {
                        $permissions_fields = $tables_data['newpoints_forum_permissions'];
                    }

                    foreach ($permissions_fields as $field_name => $field_definition) {
                        if (!isset($field_definition['is_permission']) || empty($field_definition['dragging_permission'])) {
                            continue;
                        }

                        if (isset($permission_value[$field_name])) {
                            $insert_data[$field_name][$object_id] = match ($field_definition['type']) {
                                'BIGINT', 'INT', 'SMALLINT', 'TINYINT' => (int)$permission_value[$field_name],
                                'FLOAT', 'DECIMAL' => (float)$permission_value[$field_name],
                                default => $db->escape_string($permission_value[$field_name]),
                            };
                        } else {
                            $insert_data[$field_name][$object_id] = $field_definition['default'];
                        }
                    }
                }

                if ($mybb->get_input('type') === 'group_permissions') {
                    save_quick_group_permissions($instance_id, $insert_data);

                    log_admin_action(['instance_id' => $instance_id]);

                    if (!$is_add_page) {
                        $instance->cache_update_group_permissions();
                    }

                    flash_message($lang->newpoints_admin_instances_success_instance_edit_permissions_groups, 'success');

                    admin_redirect(
                        $url->build(
                            ['action' => 'edit', 'instance_id' => $instance_id]
                        ) . '#tab_group_permissions'
                    );
                } else {
                    save_quick_forum_permissions($instance_id, $insert_data);

                    log_admin_action(['instance_id' => $instance_id]);

                    if (!$is_add_page) {
                        $instance->cache_update_forum_permissions();
                    }

                    flash_message($lang->newpoints_admin_instances_success_instance_edit_permissions_forums, 'success');

                    admin_redirect(
                        $url->build(
                            ['action' => 'edit', 'instance_id' => $instance_id]
                        ) . '#tab_forum_permissions'
                    );
                }

                break;
        }
    }

    $page->add_breadcrumb_item($lang->newpoints_breadcrumb_instances, $url->build());

    if (!$is_add_page) {
        $page->add_breadcrumb_item($instance->get_display_name_upper(), $url->get_url());
    }

    $page->add_breadcrumb_item(
        $lang->newpoints_breadcrumb_instances_edit,
        $url->build(['action' => $is_add_page ? 'add' : 'edit', 'instance_id' => $instance_id])
    );

    $page->extra_header .= "<script src=\"jscripts/quick_perm_editor.js\" type=\"text/javascript\"></script>\n";

    $page->output_header($lang->newpoints_admin_instances_edit);

    if ($error_messages) {
        $page->output_inline_error($error_messages);
    }

    $permission_tabs = [
        'main' => $lang->newpoints_admin_instances_edit_tabs_main,
    ];

    if (!$is_add_page) {
        $permission_tabs['group_permissions'] = $lang->newpoints_admin_instances_edit_tabs_permissions;

        $permission_tabs['forum_permissions'] = $lang->newpoints_admin_instances_edit_tabs_forum_permissions;
    }

    run_hooks('admin_instances_add_edit_start');

    if ($is_add_page) {
        $page->output_nav_tabs($sub_tabs, 'newpoints_instances_add');
    } else {
        $page->output_nav_tabs($sub_tabs, 'newpoints_instances_edit');
    }

    $page->output_tab_control($permission_tabs);

    $group_permissions = $permissions_cache[$instance->instance_id] ?? [];

    $mybb->input = array_merge($is_add_page ? [] : $instance->get_data(), $mybb->input);

    $row_objects = [
        'main' => [
            'single' => [],
            'grouped' => [],
        ],
    ];

    foreach ($tables_data['newpoints_instances'] as $field_name => $field_definition) {
        if (!isset($field_definition['form_category'])) {
            continue;
        }

        if (isset($field_definition['form_section'])) {
            $row_objects[$field_definition['form_category']]['grouped'][$field_definition['form_section']][$field_name] = $field_definition;
        } else {
            $row_objects[$field_definition['form_category']]['single'][$field_name] = $field_definition;
        }
    }

    //main options tab
    echo "<div id=\"tab_main\">\n";

    $form = new Form(
        $url->build(
            ['action' => $is_add_page ? 'add' : 'edit', 'type' => 'main', 'instance_id' => $instance_id]
        ) . '#tab_main',
        'post',
        $is_add_page ? 'add' : 'edit'
    );

    $form_container = new FormContainer($lang->newpoints_admin_instances_edit_tabs_main);

    foreach ($row_objects['main']['single'] as $field_name => $field_definition) {
        $language_key = str_replace('newpoints_', '', $field_name);

        $form_container->output_row(
            $lang->{'newpoints_admin_instances_edit_' . $language_key},
            $lang->{'newpoints_admin_instances_edit_' . $language_key . '_description'},
            build_permissions_row(
                $instance_id,
                $form,
                $field_name,
                $field_definition,
                $language_key,
                'Main'
            )
        );
    }

    foreach ($row_objects['main']['grouped'] as $form_section => $form_objects) {
        $sectionKey = ucfirst($form_section);

        $setting_code = '';

        foreach ($form_objects as $field_name => $field_definition) {
            $language_key = str_replace('newpoints_', '', $field_name);

            $setting_code .= build_permissions_row(
                $instance_id,
                $form,
                $field_name,
                $field_definition,
                $language_key,
                'Main',
                true,
            );
        }

        $form_container->output_row(
            $lang->{'newpoints_admin_instances_edit_' . $sectionKey},
            '',
            $setting_code
        );
    }

    $form_container->end();

    $form->output_submit_wrapper([
        $form->generate_submit_button($lang->newpoints_admin_instances_edit_button_submit),
        $form->generate_reset_button($lang->newpoints_admin_instances_edit_button_reset)
    ]);

    $form->end();

    echo "</div>\n";

    if ($is_add_page) {
        run_hooks('admin_instances_add_edit_end');

        $page->output_footer();

        exit;
    }

    echo "<div id=\"tab_group_permissions\">\n";

    $form = new Form(
        $url->build(
            [
                'action' => 'edit',
                'type' => 'group_permissions',
                'instance_id' => $instance->instance_id
            ]
        ),
        'post',
        'group_permissions'
    );

    echo $form->generate_hidden_field('instance_id', $instance->instance_id);

    $existing_permissions = [];

    foreach (
        $instance->permissions_group_get(
            ["instance_id='{$instance->instance_id}'"],
            array_keys($tables_data['newpoints_group_permissions'])
        ) as $existing
    ) {
        $existing_permissions[$existing['group_id']] = $existing;
    }

    $field_list = [];

    foreach ($tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
        if (!isset($field_definition['is_permission']) || empty($field_definition['dragging_permission'])) {
            continue;
        }

        $language_key = str_replace('newpoints_', '', $field_name);

        $field_list[$field_name] = $lang->{'newpoints_permission_group_' . $language_key};
    }

    $group_ids = [];

    $form_container = new FormContainer(
        $lang->newpoints_admin_instances_permissions_form_group_permissions
    );

    $form_container->output_row_header(
        $lang->newpoints_admin_instances_permissions_form_group,
        ['class' => 'align_center', 'style' => 'width: 30%']
    );

    $form_container->output_row_header(
        $lang->newpoints_admin_instances_permissions_form_allowed_actions,
        ['class' => 'align_center']
    );

    $form_container->output_row_header(
        $lang->newpoints_admin_instances_permissions_form_disallowed_actions,
        ['class' => 'align_center']
    );

    $form_container->output_row_header(
        $lang->controls,
        ['class' => 'align_center', 'style' => 'width: 120px', 'colspan' => 2]
    );

    $input_permissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

    if ($mybb->request_method == 'post') {
        foreach ($groups_cache as $group_data) {
            $group_id = (int)$group_data['gid'];

            if (isset($mybb->input['fields_group_' . $group_id])) {
                $input_permissions = $mybb->input['fields_group_' . $group_id];

                if (!is_array($input_permissions)) {
                    // Converting the comma separated list from Javascript form into a variable
                    $input_permissions = explode(',', $input_permissions);
                }
                foreach ($input_permissions as $input_permission) {
                    $input_permissions[$group_id][$input_permission] = 1;
                }
            }
        }
    }

    foreach ($groups_cache as $group_data) {
        $group_id = (int)$group_data['gid'];

        $permissions = [];

        if (isset($mybb->input['default_permissions'])) {
            if ($mybb->input['default_permissions'][$group_id]) {
                if ($existing_permissions[$group_id]) {
                    $permissions = $existing_permissions[$group_id];

                    $default_checked = false;
                } elseif (is_array(
                        $permissions_cache
                    ) && $permissions_cache[$instance->instance_id][$group_id]) {
                    $permissions = $permissions_cache[$instance->instance_id][$group_id];

                    $default_checked = true;
                } elseif (is_array(
                        $permissions_cache
                    ) && $permissions_cache[$instance->instance_id][$group_id]) {
                    $permissions = $permissions_cache[$instance->instance_id][$group_id];

                    $default_checked = true;
                }
            }

            if (!$permissions) {
                $default_checked = true;
            }
        } else {
            if (isset($existing_permissions) &&
                is_array($existing_permissions) &&
                !empty($existing_permissions[$group_id])) {
                $permissions = $existing_permissions[$group_id];

                $default_checked = false;
            } elseif (is_array(
                    $permissions_cache
                ) && !empty($permissions_cache[$instance->instance_id][$group_id])) {
                $permissions = $permissions_cache[$instance->instance_id][$group_id];

                $default_checked = true;
            } elseif (is_array(
                    $permissions_cache
                ) && !empty($permissions_cache[$instance->instance_id][$group_id])) {
                $permissions = $permissions_cache[$instance->instance_id][$group_id];

                $default_checked = true;
            }

            if (!$permissions) {
                $permissions = $group_data;

                foreach ($permissions as $permission_key => $permission_value) {
                    if (str_starts_with($permission_key, 'newpoints_')) {
                        $permissions[str_replace('newpoints_', '', $permission_key)] = $permission_value;
                    }
                }

                $default_checked = true;
            }
        }

        $checked_permissions = [];

        foreach ($field_list as $permission_name => $permission_title) {
            if ($input_permissions) {
                if (isset($input_permissions[$group_id][$permission_name])) {
                    $checked_permissions[$permission_name] = 1;
                } else {
                    $checked_permissions[$permission_name] = 0;
                }
            } elseif (!empty($permissions[$permission_name])) {
                $checked_permissions[$permission_name] = 1;
            } else {
                $checked_permissions[$permission_name] = 0;
            }
        }

        $group_title = htmlspecialchars_uni($group_data['title']);

        if (!empty($default_checked)) {
            $inheritedText = $lang->newpoints_admin_instances_permissions_form_inherited;
        } else {
            $inheritedText = $lang->newpoints_admin_instances_permissions_form_custom;
        }

        $form_container->output_cell(
            "<strong>{$group_title}</strong> <small style=\"vertical-align: middle;\">({$inheritedText})</small>"
        );

        $field_select = "<div class=\"quick_perm_fields\">\n";

        $field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_group_{$group_id}\">\n";

        foreach ($checked_permissions as $permission_name => $permission_value) {
            if ($permission_value) {
                $field_select .= "<li id=\"field-{$permission_name}\">{$field_list[$permission_name]}</li>";
            }
        }

        $field_select .= "</ul></div>\n";

        $field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_group_{$group_id}\">\n";

        foreach ($checked_permissions as $permission_name => $permission_value) {
            if (!$permission_value) {
                $field_select .= "<li id=\"field-{$permission_name}\">{$field_list[$permission_name]}</li>";
            }
        }
        $field_select .= "</ul></div></div>\n";
        $field_select .= $form->generate_hidden_field(
            'fields_group_' . $group_id,
            implode(',', array_keys($checked_permissions, '1')),
            ['id' => 'fields_group_' . $group_id]
        );
        $field_select .= $form->generate_hidden_field(
            'fields_inherit_' . $group_id,
            isset($default_checked) ? (int)$default_checked : 0,
            ['id' => 'fields_inherit_' . $group_id]
        );
        $field_select .= $form->generate_hidden_field(
            'fields_default_' . $group_id,
            implode(',', array_keys($checked_permissions, '1')),
            ['id' => 'fields_default_' . $group_id]
        );
        $field_select = str_replace("'", "\\'", $field_select);
        $field_select = str_replace("\n", '', $field_select);

        $field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('" . str_replace('/', '\/', $field_select) . "');
//]]>
</script>\n";

        $field_options = $field_selected = [];

        foreach ($field_list as $permission_name => $permission_title) {
            $field_options[$permission_name] = $permission_title;

            if ($checked_permissions[$permission_name]) {
                $field_selected[] = $permission_name;
            }
        }

        $field_select .= '<noscript>' . $form->generate_select_box(
                'fields_group_' . $group_id . '[]',
                $field_options,
                $field_selected,
                ['id' => 'fields_group_' . $group_id . '[]', 'multiple' => true]
            ) . "</noscript>\n";

        $form_container->output_cell($field_select, ['colspan' => 2]);

        $permissions_url = $url->build(
                [
                    'action' => 'group_permissions',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permissions['permission_id'] ?? 0,
                    'group_id' => $group_id
                ]
            ) . '#tab_group_permissions';

        $modal_url = $url->build(
            [
                'action' => 'group_permissions',
                'instance_id' => $instance->instance_id,
                'permission_id' => $permissions['permission_id'] ?? 0,
                'group_id' => $group_id,
                'ajax' => 1
            ]
        );

        if (empty($default_checked)) {
            $form_container->output_cell(
                "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_edit}</a>",
                ['class' => 'align_center']
            );

            $clear_group_permissions_url = $url->build(
                [
                    'action' => 'clear_group_permission',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permissions['permission_id'],
                    'my_post_key' => $mybb->post_code
                ]
            );

            $form_container->output_cell(
                "<a href=\"{$clear_group_permissions_url}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->newpoints_admin_instances_permissions_form_confirm_clear}')\">{$lang->newpoints_admin_instances_permissions_form_clear}</a>",
                ['class' => 'align_center']
            );
        } else {
            $form_container->output_cell(
                "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_set}</a>",
                ['class' => 'align_center', 'colspan' => 2]
            );
        }

        $form_container->construct_row(['id' => 'row_' . $group_id]);

        $group_ids[] = $group_id;

        unset($default_checked);
    }

    $form_container->end();

    $form->output_submit_wrapper(
        [$form->generate_submit_button($lang->newpoints_admin_instances_permissions_form_button_submit_groups)]
    );

    $form->end();

    // Write in our JS based field selector
    echo "<script type=\"text/javascript\">\n<!--\n";

    foreach ($group_ids as $group_id) {
        echo '$(function() { QuickPermEditor.init(\'group_\' + ' . $group_id . "); });\n";
    }

    echo "// -->\n</script>\n";

    echo "</div>\n";

    echo "<div id=\"tab_forum_permissions\">\n";

    $form = new Form(
        $url->build(
            [
                'action' => 'edit',
                'type' => 'forum_permissions',
                'instance_id' => $instance->instance_id
            ]
        ),
        'post',
        'forum_permissions'
    );

    echo $form->generate_hidden_field('instance_id', $instance->instance_id);

    $existing_permissions = [];

    foreach (
        $instance->permissions_forum_get(
            ["instance_id='{$instance->instance_id}'"],
            array_keys($tables_data['newpoints_forum_permissions'])
        ) as $existing
    ) {
        $existing_permissions[$existing['forum_id']] = $existing;
    }

    $field_list = [];

    foreach ($tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
        if (!isset($field_definition['is_permission']) || empty($field_definition['dragging_permission'])) {
            continue;
        }

        $language_key = str_replace('newpoints_', '', $field_name);

        $field_list[$field_name] = $lang->{'newpoints_permissions_forum_' . $language_key};
    }

    $forum_ids = [];

    $form_container = new FormContainer(
        $lang->newpoints_admin_instances_permissions_form_forum_permissions
    );

    $form_container->output_row_header(
        $lang->newpoints_admin_instances_permissions_form_forum,
        ['class' => 'align_center', 'style' => 'width: 30%']
    );

    $form_container->output_row_header(
        $lang->newpoints_admin_instances_permissions_form_allowed_actions,
        ['class' => 'align_center']
    );

    $form_container->output_row_header(
        $lang->newpoints_admin_instances_permissions_form_disallowed_actions,
        ['class' => 'align_center']
    );

    $form_container->output_row_header(
        $lang->controls,
        ['class' => 'align_center', 'style' => 'width: 120px', 'colspan' => 2]
    );

    $input_permissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

    if ($mybb->request_method == 'post') {
        foreach ($forums_cache as $forum_data) {
            $forum_id = (int)$forum_data['fid'];

            if (isset($mybb->input['fields_forum_' . $forum_id])) {
                $input_permissions = $mybb->input['fields_forum_' . $forum_id];

                if (!is_array($input_permissions)) {
                    // Converting the comma separated list from Javascript form into a variable
                    $input_permissions = explode(',', $input_permissions);
                }
                foreach ($input_permissions as $input_permission) {
                    $input_permissions[$forum_id][$input_permission] = 1;
                }
            }
        }
    }

    foreach ($forums_cache as $forum_data) {
        $forum_id = (int)$forum_data['fid'];

        $permissions = [];

        if (isset($mybb->input['default_permissions'])) {
            if ($mybb->input['default_permissions'][$forum_id]) {
                if ($existing_permissions[$forum_id]) {
                    $permissions = $existing_permissions[$forum_id];

                    $default_checked = false;
                } elseif (is_array(
                        $permissions_cache
                    ) && $permissions_cache[$instance->instance_id][$forum_id]) {
                    $permissions = $permissions_cache[$instance->instance_id][$forum_id];

                    $default_checked = true;
                } elseif (is_array(
                        $permissions_cache
                    ) && $permissions_cache[$instance->instance_id][$forum_id]) {
                    $permissions = $permissions_cache[$instance->instance_id][$forum_id];

                    $default_checked = true;
                }
            }

            if (!$permissions) {
                $default_checked = true;
            }
        } else {
            if (isset($existing_permissions) &&
                is_array($existing_permissions) &&
                !empty($existing_permissions[$forum_id])) {
                $permissions = $existing_permissions[$forum_id];

                $default_checked = false;
            } elseif (is_array(
                    $permissions_cache
                ) && !empty($permissions_cache[$instance->instance_id][$forum_id])) {
                $permissions = $permissions_cache[$instance->instance_id][$forum_id];

                $default_checked = true;
            } elseif (is_array(
                    $permissions_cache
                ) && !empty($permissions_cache[$instance->instance_id][$forum_id])) {
                $permissions = $permissions_cache[$instance->instance_id][$forum_id];

                $default_checked = true;
            }

            if (!$permissions) {
                $permissions = $forum_data;

                foreach ($permissions as $permission_key => $permission_value) {
                    if (str_starts_with($permission_key, 'newpoints_')) {
                        $permissions[str_replace('newpoints_', '', $permission_key)] = $permission_value;
                    }
                }

                $default_checked = true;
            }
        }

        $checked_permissions = [];

        foreach ($field_list as $permission_name => $permission_title) {
            if ($input_permissions) {
                if (isset($input_permissions[$forum_id][$permission_name])) {
                    $checked_permissions[$permission_name] = 1;
                } else {
                    $checked_permissions[$permission_name] = 0;
                }
            } elseif (!empty($permissions[$permission_name])) {
                $checked_permissions[$permission_name] = 1;
            } else {
                $checked_permissions[$permission_name] = 0;
            }
        }

        $forum_name = strip_tags($forum_data['name']);

        if (!empty($default_checked)) {
            $inheritedText = $lang->newpoints_admin_instances_permissions_form_inherited;
        } else {
            $inheritedText = $lang->newpoints_admin_instances_permissions_form_custom;
        }

        $form_container->output_cell(
            "<strong>{$forum_name}</strong> <small style=\"vertical-align: middle;\">({$inheritedText})</small>"
        );

        $field_select = "<div class=\"quick_perm_fields\">\n";

        $field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_forum_{$forum_id}\">\n";

        foreach ($checked_permissions as $permission_name => $permission_value) {
            if ($permission_value) {
                $field_select .= "<li id=\"field-{$permission_name}\">{$field_list[$permission_name]}</li>";
            }
        }

        $field_select .= "</ul></div>\n";

        $field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_forum_{$forum_id}\">\n";

        foreach ($checked_permissions as $permission_name => $permission_value) {
            if (!$permission_value) {
                $field_select .= "<li id=\"field-{$permission_name}\">{$field_list[$permission_name]}</li>";
            }
        }
        $field_select .= "</ul></div></div>\n";
        $field_select .= $form->generate_hidden_field(
            'fields_forum_' . $forum_id,
            implode(',', array_keys($checked_permissions, '1')),
            ['id' => 'fields_forum_' . $forum_id]
        );
        $field_select .= $form->generate_hidden_field(
            'fields_inherit_' . $forum_id,
            isset($default_checked) ? (int)$default_checked : 0,
            ['id' => 'fields_inherit_' . $forum_id]
        );
        $field_select .= $form->generate_hidden_field(
            'fields_default_' . $forum_id,
            implode(',', array_keys($checked_permissions, '1')),
            ['id' => 'fields_default_' . $forum_id]
        );
        $field_select = str_replace("'", "\\'", $field_select);
        $field_select = str_replace("\n", '', $field_select);

        $field_select = "<script type=\"text/javascript\">
//<![CDATA[
document.write('" . str_replace('/', '\/', $field_select) . "');
//]]>
</script>\n";

        $field_options = $field_selected = [];

        foreach ($field_list as $permission_name => $permission_title) {
            $field_options[$permission_name] = $permission_title;

            if ($checked_permissions[$permission_name]) {
                $field_selected[] = $permission_name;
            }
        }

        $field_select .= '<noscript>' . $form->generate_select_box(
                'fields_forum_' . $forum_id . '[]',
                $field_options,
                $field_selected,
                ['id' => 'fields_forum_' . $forum_id . '[]', 'multiple' => true]
            ) . "</noscript>\n";

        $form_container->output_cell($field_select, ['colspan' => 2]);

        $permissions_url = $url->build(
                [
                    'action' => 'forum_permissions',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permissions['permission_id'] ?? 0,
                    'forum_id' => $forum_id
                ]
            ) . '#tab_forum_permissions';

        $modal_url = $url->build(
            [
                'action' => 'forum_permissions',
                'instance_id' => $instance->instance_id,
                'permission_id' => $permissions['permission_id'] ?? 0,
                'forum_id' => $forum_id,
                'ajax' => 1
            ]
        );

        if (empty($default_checked)) {
            $form_container->output_cell(
                "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_edit}</a>",
                ['class' => 'align_center']
            );

            $clear_forum_permissions_url = $url->build(
                [
                    'action' => 'clear_forum_permission',
                    'instance_id' => $instance->instance_id,
                    'permission_id' => $permissions['permission_id'],
                    'my_post_key' => $mybb->post_code
                ]
            );

            $form_container->output_cell(
                "<a href=\"{$clear_forum_permissions_url}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->newpoints_admin_instances_permissions_form_confirm_clear}')\">{$lang->newpoints_admin_instances_permissions_form_clear}</a>",
                ['class' => 'align_center']
            );
        } else {
            $form_container->output_cell(
                "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_set}</a>",
                ['class' => 'align_center', 'colspan' => 2]
            );
        }

        $form_container->construct_row(['id' => 'row_' . $forum_id]);

        $forum_ids[] = $forum_id;

        unset($default_checked);
    }

    $form_container->end();

    $form->output_submit_wrapper(
        [$form->generate_submit_button($lang->newpoints_admin_instances_permissions_form_button_submit_forums)]
    );

    $form->end();

    // Write in our JS based field selector
    echo "<script type=\"text/javascript\">\n<!--\n";

    foreach ($forum_ids as $forum_id) {
        echo '$(function() { QuickPermEditor.init(\'forum_\' + ' . $forum_id . "); });\n";
    }

    echo "// -->\n</script>\n";

    echo "</div>\n";

    run_hooks('admin_instances_add_edit_end');

    $page->output_footer();
} elseif ($mybb->get_input('action') === 'rebuild_columns') {
    try {
        $instance = instance_object($instance_id);
    } catch (InvalidArgumentException $e) {
        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');

        exit;
    }

    $fields_data = [
        'users' => [
            $instance->users_column_get() => FIELDS_DATA['users']['newpoints']
        ]
    ];

    run_hooks('admin_instances_rebuild_columns_start');

    db_verify_columns($fields_data);

    flash_message(
        $lang->sprintf(
            $lang->newpoints_instances_rebuild_columns_success,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
        ),
        'success'
    );

    admin_redirect('index.php?module=newpoints-instances');
} else {
    $page->add_breadcrumb_item($lang->newpoints_breadcrumb_instances, $url->build());

    $page->output_header($lang->newpoints_instances);

    $page->output_nav_tabs($sub_tabs, 'newpoints_instances');

    run_hooks('admin_instances_start');

    $table = new Table();

    $table->construct_header($lang->newpoints_instances_thead_id, ['width' => '1%', 'class' => 'align_center']);

    $table->construct_header($lang->newpoints_instances_thead_name);

    $table->construct_header($lang->newpoints_instances_thead_column, ['width' => '20%', 'class' => 'align_center']);

    $table->construct_header($lang->newpoints_instances_thead_main_file, ['width' => '20%', 'class' => 'align_center']);

    $table->construct_header($lang->newpoints_instances_thead_enabled, ['width' => '5%', 'class' => 'align_center']);

    $table->construct_header($lang->options, ['width' => '15%', 'class' => 'align_center']);

    $settings_url = new Url('index.php');

    $settings_url = $settings_url->set_url($settings_url->build([
        'module' => 'newpoints-settings',
    ]));

    $permissions_settings = check_admin_permissions(['module' => $page->active_module, 'action' => 'settings'], false);

    foreach (
        instance_get(query_fields: ['users_column_name', 'is_enabled']) as $instance_id => $instance_data
    ) {
        try {
            $instance = instance_object($instance_id);
        } catch (InvalidArgumentException|Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $column_statuses = [
            $instance_data['users_column_name'] => $instance->users_column_exists(),
        ];

        run_hooks('admin_instances_row_end');

        $table->construct_cell($instance->instance_id, ['class' => 'align_center']);

        $edit_url = (new Url('index.php'))
            ->build([
                'module' => 'newpoints-instances',
                'action' => 'edit',
                'instance_id' => $instance->instance_id,
            ]);

        $table->construct_cell("<a href=\"{$edit_url}\">{$instance->get_display_name_upper()}</a>");

        $missing_columns = false;

        array_walk($column_statuses, function (&$column_exists, $column_name) use (&$missing_columns) {
            if ($column_exists) {
                $column_exists = "<code style='color: darkgreen;'>{$column_name}</code>";
            } else {
                $missing_columns = true;

                $column_exists = "<code style='color: darkred;'>{$column_name}</code>";
            }
        });

        $table->construct_cell(
            implode('<br />', $column_statuses),
            ['class' => 'align_center']
        );

        $newpoints_file = main_file_name();

        if ($main_file_exists = file_exists(MYBB_ROOT . $newpoints_file)) {
            $table->construct_cell(
                "<code style='color: darkgreen;'>" . $newpoints_file . '</code>',
                ['class' => 'align_center']
            );
        } else {
            $table->construct_cell(
                "<code style='color: darkred;'>" . $newpoints_file . '</code>',
                ['class' => 'align_center']
            );
        }

        if ($instance_data['is_enabled']) {
            $phrase = $lang->disable;

            $icon = "on.png\" alt=\"({$lang->alt_enabled})\" title=\"{$lang->alt_enabled}";
        } else {
            $phrase = $lang->enable;

            $icon = "off.png\" alt=\"({$lang->alt_disabled})\" title=\"{$lang->alt_disabled}";
        }

        $url_params = [];

        $table->construct_cell(
            "<img src=\"styles/{$page->style}/images/icons/bullet_{$icon}\" style=\"vertical-align: middle;\" />",
            ['class' => 'align_center']
        );

        $popup = new PopupMenu("instance_{$instance->instance_id}", $lang->options);

        if ($permissions_settings) {
            $popup->add_item(
                $lang->newpoints_instances_thead_options_settings,
                $settings_url->build(['instance_id' => $instance->instance_id]),
            );
        }

        $popup->add_item(
            $lang->edit,
            $edit_url,
        );

        if ($instance->instance_id !== INSTANCE_DEFAULT_ID) {
            if ($missing_columns) {
                $popup->add_item(
                    $lang->newpoints_instances_thead_options_rebuild_columns,
                    (new Url('index.php'))
                        ->build([
                            'module' => 'newpoints-instances',
                            'action' => 'rebuild_columns',
                            'instance_id' => $instance->instance_id,
                        ]),
                );
            }
        }

        if ($instance->instance_id !== INSTANCE_DEFAULT_ID) {
            $popup->add_item(
                $lang->delete,
                (new Url('index.php'))
                    ->build([
                        'module' => 'newpoints-instances',
                        'action' => 'delete',
                        'instance_id' => $instance->instance_id,
                    ]),
            );
        }

        $table->construct_cell($popup->fetch(), ['class' => 'align_center']);

        $table->construct_row();
    }

    run_hooks('admin_instances_end');

    $table->output($lang->newpoints_instances_title);

    $page->output_footer();
}

// todo review hooks here