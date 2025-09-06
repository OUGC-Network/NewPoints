<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/settings.php)
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

use function Newpoints\Core\instance_object;
use function Newpoints\Core\language_load;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\settings_rebuild;
use function Newpoints\Core\settings_rebuild_cache;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\url_handler_get;
use function Newpoints\Core\url_handler_set;

use const Newpoints\Core\INSTANCE_DEFAULT_ID;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb, $cache, $config;

$array = [];

settings_rebuild_cache($array);

language_load();

$lang->load('config_settings', false, true);

$instance_id = $mybb->get_input('instance_id', MyBB::INPUT_INT);

if ($instance_id < 1) {
    $instance_id = INSTANCE_DEFAULT_ID;
}

try {
    $instance_object = instance_object($instance_id);
} catch (InvalidArgumentException $e) {
    flash_message($e->getMessage(), 'error');

    admin_redirect('index.php?module=newpoints-instances');
}

url_handler_set('index.php');

url_handler_set(url_handler_build([
    'module' => 'newpoints-settings',
    'instance_id' => $instance_id,
]));

$instance_page_title = $lang->sprintf(
    $lang->newpoints_settings_instance,
    $instance_object->get_display_name_upper(),
    $instance_object->get_display_name_lower(),
);

$sub_tabs = [
    'newpoints_instances' => [
        'title' => $lang->newpoints_instances,
        'link' => url_handler_build(),
        'description' => $lang->newpoints_instances_description
    ],
    'newpoints_settings' => [
        'title' => $lang->newpoints_settings,
        'link' => url_handler_get(),
        'description' => $lang->sprintf(
            $lang->newpoints_settings_description,
            $instance_object->get_display_name_upper(),
            $instance_object->get_display_name_lower(),
        )
    ],
];

if ($mybb->get_input('action') == 'change') {
    $plugin_title = '';

    $plugin_code = trim($mybb->get_input('plugin'));

    $lang_var = 'setting_group_newpoints_' . $plugin_code;

    if (in_array($plugin_code, ['main', 'donations', 'stats', 'logs'], true)) {
        $plugin_title = $lang->{$lang_var};
    } elseif ($plugin_information = newpoints_get_plugininfo($plugin_code)) {
        $plugin_title = htmlspecialchars_uni($plugin_information['name']);
    } else {
        $plugin_title = htmlspecialchars_uni($lang->{$group_lang_var});
    }

    $sub_tabs['newpoints_settings_change'] = [
        'title' => $lang->newpoints_settings_change,
        'link' => url_handler_build(['action' => 'change', 'plugin' => $mybb->get_input('plugin')]),
        'description' => $lang->sprintf(
            $lang->newpoints_settings_change_description,
            $instance_object->get_display_name_upper(),
            $instance_object->get_display_name_lower(),
            $plugin_title
        )
    ];
}

// Change settings for a specified group.
if ($mybb->get_input('action') == 'change') {
    run_hooks('admin_settings_change');

    if ($mybb->request_method == 'post') {
        $upsetting = $mybb->get_input('upsetting', MyBB::INPUT_ARRAY);

        $select = $mybb->get_input('select', MyBB::INPUT_ARRAY);

        if (!empty($upsetting)) {
            $checkbox_settings = $forum_group_select = [];

            $query = $db->simple_select(
                'newpoints_settings',
                'name, type',
                "type IN('forumselect', 'groupselect', 'checkbox') OR type LIKE 'checkbox%' AND instance_id='{$instance_id}'"
            );

            while ($multi_setting = $db->fetch_array($query)) {
                $options = [];

                if (substr($multi_setting['type'], 0, 8) == 'checkbox') {
                    $checkbox_settings[] = $multi_setting['name'];

                    if (empty($upsetting[$multi_setting['name']]) && isset($mybb->input["isvisible_{$multi_setting['name']}"])) {
                        $upsetting[$multi_setting['name']] = [];
                    }
                } else {
                    $forum_group_select[] = $multi_setting['name'];
                }
            }

            foreach ($upsetting as $name => $value) {
                if ($checkbox_settings && in_array($name, $checkbox_settings)) {
                    $value = '';

                    if (is_array($upsetting[$name])) {
                        $value = implode(',', $upsetting[$name]);
                    }
                } elseif (!empty($forum_group_select) && in_array($name, $forum_group_select)) {
                    if ($value == 'all') {
                        $value = -1;
                    } elseif ($value == 'custom') {
                        if (isset($select[$name]) && is_array($select[$name])) {
                            foreach ($select[$name] as &$val) {
                                $val = (int)$val;
                            }
                            unset($val);

                            $value = implode(',', $select[$name]);
                        } else {
                            $value = '';
                        }
                    } else {
                        $value = '';
                    }
                }

                $db->update_query(
                    'newpoints_settings',
                    ['value' => $db->escape_string($value)],
                    "name='{$db->escape_string($name)}' AND instance_id='{$instance_id}'"
                );
                //$db->update_query("settings", array('value' => $value), "name='".$db->escape_string($name)."'");
            }
        }

        rebuild_settings();

        settings_rebuild_cache();

        run_hooks('admin_settings_change_commit');

        // Log admin action
        log_admin_action();

        flash_message($lang->success_settings_updated, 'success');

        admin_redirect(url_handler_get());
    }

    $cache_groups = $cache_settings = [];

    $plugin_code = trim($mybb->get_input('plugin'));

    $group_key = '';

    if (!$plugin_code) {
        flash_message($lang->newpoints_select_plugin, 'error');

        admin_redirect(url_handler_get());
    }

    $plugin_description = '';

    $group_key = str_replace('newpoints_', '', $plugin_code);

    $query = $db->simple_select(
        'newpoints_settings',
        '*',
        "plugin='" . $db->escape_string($group_key) . "' AND instance_id='{$instance_id}'",
        ['order_by' => 'disporder']
    );

    if (!$db->num_rows($query)) {
        flash_message($lang->error_no_settings_found, 'error');

        admin_redirect(url_handler_get());
    }

    while ($setting = $db->fetch_array($query)) {
        $cache_settings[$setting['plugin']][$setting['sid']] = $setting;
    }

    if (in_array($plugin_code, ['main', 'donations', 'stats', 'logs'], true)) {
        $plugin_description = $lang->{$lang_var . '_desc'};
    } elseif ($plugin_information = newpoints_get_plugininfo($plugin_code)) {
        $plugin_description = htmlspecialchars_uni($plugin_description);
    } else {
        $setting_groups_objects = [];

        $setting_groups_objects = run_hooks('admin_settings_commit_start', $setting_groups_objects);

        if (!isset($setting_groups_objects[$plugin_code])) {
            flash_message($lang->error_no_settings_found, 'error');

            admin_redirect(url_handler_get());
        }

        $group_key = $plugin_code;

        $group_lang_var = "setting_group_newpoints_{$group_key}";

        $group_desc_lang_var = "setting_group_newpoints_{$group_key}_desc";

        $plugin_description = htmlspecialchars_uni($lang->{$group_desc_lang_var});
    }

    $page->add_breadcrumb_item($lang->newpoints_instances, 'index.php?module=newpoints-instances');

    $page->add_breadcrumb_item($instance_object->get_display_name_upper(), url_handler_get());

    // Page header
    $page->add_breadcrumb_item($plugin_title);

    $page->output_header($instance_page_title . " - {$plugin_title}");

    $page->output_nav_tabs($sub_tabs, 'newpoints_settings_change');

    $form = new Form(url_handler_build(['action' => 'change']), 'post', 'change');

    echo $form->generate_hidden_field('instance_id', $instance_id);

    // Build rest of page
    $buttons[] = $form->generate_submit_button($lang->save_settings);

    $form_container = new FormContainer($plugin_title);

    if (empty($cache_settings[$group_key])) {
        $form_container->output_cell($lang->error_no_settings_found);

        $form_container->construct_row();

        $form_container->end();
        echo '<br />';

        $form->end();

        $page->output_footer();
    }

    foreach ($cache_settings[$group_key] as $setting) {
        $options = '';

        $type = explode("\n", $setting['type']);

        $type[0] = trim($type[0]);

        $element_name = "upsetting[{$setting['name']}]";

        $element_id = "setting_{$setting['name']}";

        $setting_code = '';

        if ($type[0] == 'text' || $type[0] == '') {
            $setting_code = $form->generate_text_box($element_name, $setting['value'], ['id' => $element_id]);
        } elseif ($type[0] == 'numeric') {
            $setting_code = $form->generate_numeric_field(
                $element_name,
                $setting['value'],
                ['id' => $element_id]
            );
        } elseif ($type[0] == 'textarea') {
            $setting_code = $form->generate_text_area(
                $element_name,
                $setting['value'],
                ['id' => $element_id]
            );
        } elseif ($type[0] == 'yesno') {
            $setting_code = $form->generate_yes_no_radio(
                $element_name,
                $setting['value'],
                true,
                ['id' => $element_id . '_yes', 'class' => $element_id],
                ['id' => $element_id . '_no', 'class' => $element_id]
            );
        } elseif ($type[0] == 'onoff') {
            $setting_code = $form->generate_on_off_radio(
                $element_name,
                $setting['value'],
                true,
                ['id' => $element_id . '_on', 'class' => $element_id],
                ['id' => $element_id . '_off', 'class' => $element_id]
            );
        } elseif ($type[0] == 'cpstyle') {
            $dir = @opendir(MYBB_ROOT . $config['admin_dir'] . '/styles');

            while ($folder = readdir($dir)) {
                if ($folder != '.' && $folder != '..' && @file_exists(
                        MYBB_ROOT . $config['admin_dir'] . "/styles/$folder/main.css"
                    )) {
                    $folders[$folder] = ucfirst($folder);
                }
            }

            closedir($dir);

            ksort($folders);

            $setting_code = $form->generate_select_box(
                $element_name,
                $folders,
                $setting['value'],
                ['id' => $element_id]
            );
        } elseif ($type[0] == 'language') {
            $languages = $lang->get_languages();

            $setting_code = $form->generate_select_box(
                $element_name,
                $languages,
                $setting['value'],
                ['id' => $element_id]
            );
        } elseif ($type[0] == 'adminlanguage') {
            $languages = $lang->get_languages(1);

            $setting_code = $form->generate_select_box(
                $element_name,
                $languages,
                $setting['value'],
                ['id' => $element_id]
            );
        } elseif ($type[0] == 'passwordbox') {
            $setting_code = $form->generate_password_box(
                $element_name,
                $setting['value'],
                ['id' => $element_id]
            );
        } elseif ($type[0] == 'php') {
            $setting['type'] = substr($setting['type'], 3);

            eval("\$setting_code = \"" . $setting['type'] . "\";");
        } elseif ($type[0] == 'forumselect') {
            $selected_values = '';

            if ($setting['value'] != '' && $setting['value'] != -1) {
                $selected_values = explode(',', (string)$setting['value']);

                foreach ($selected_values as &$value) {
                    $value = (int)$value;
                }
                unset($value);
            }

            $forum_checked = ['all' => '', 'custom' => '', 'none' => ''];

            if ($setting['value'] == -1) {
                $forum_checked['all'] = 'checked="checked"';
            } elseif ($setting['value'] != '') {
                $forum_checked['custom'] = 'checked="checked"';
            } else {
                $forum_checked['none'] = 'checked="checked"';
            }

            print_selection_javascript();

            $setting_code = "
			<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"all\" {$forum_checked['all']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_forums}</strong></label></dt>
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"custom\" {$forum_checked['custom']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_forums}</strong></label></dt>
				<dd style=\"margin-top: 4px;\" id=\"{$element_id}_forums_groups_custom\" class=\"{$element_id}_forums_groups\">
					<table cellpadding=\"4\">
						<tr>
							<td valign=\"top\"><small>{$lang->forums_colon}</small></td>
							<td>" . $form->generate_forum_select(
                    'select[' . $setting['name'] . '][]',
                    $selected_values,
                    ['id' => $element_id, 'multiple' => true, 'size' => 5]
                ) . "</td>
						</tr>
					</table>
				</dd>
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"none\" {$forum_checked['none']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
			</dl>
			<script type=\"text/javascript\">
				checkAction('{$element_id}');
			</script>";
        } elseif ($type[0] == 'forumselectsingle') {
            $selected_value = (int)$setting['value']; // No need to check if empty, int will give 0

            $setting_code = $form->generate_forum_select(
                $element_name,
                $selected_value,
                ['id' => $element_id, 'main_option' => $lang->none]
            );
        } elseif ($type[0] == 'groupselect') {
            $selected_values = '';

            if ($setting['value'] != '' && $setting['value'] != -1) {
                $selected_values = explode(',', (string)$setting['value']);

                foreach ($selected_values as &$value) {
                    $value = (int)$value;
                }
                unset($value);
            }

            $group_checked = [
                'all' => '',
                'custom' => '',
                'none' => ''
            ];

            if ($setting['value'] == -1) {
                $group_checked['all'] = 'checked="checked"';
            } elseif ($setting['value'] != '') {
                $group_checked['custom'] = 'checked="checked"';
            } else {
                $group_checked['none'] = 'checked="checked"';
            }

            print_selection_javascript();

            $setting_code = "
			<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%\">
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"all\" {$group_checked['all']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->all_groups}</strong></label></dt>
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"custom\" {$group_checked['custom']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->select_groups}</strong></label></dt>
				<dd style=\"margin-top: 4px;\" id=\"{$element_id}_forums_groups_custom\" class=\"{$element_id}_forums_groups\">
					<table cellpadding=\"4\">
						<tr>
							<td valign=\"top\"><small>{$lang->groups_colon}</small></td>
							<td>" . $form->generate_group_select(
                    'select[' . $setting['name'] . '][]',
                    $selected_values,
                    [
                        'id' => $element_id,
                        'multiple' => true,
                        'size' => 5
                    ]
                ) . "</td>
						</tr>
					</table>
				</dd>
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"none\" {$group_checked['none']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
			</dl>
			<script type=\"text/javascript\">
				checkAction('{$element_id}');
			</script>";
        } elseif ($type[0] == 'groupselectsingle') {
            $selected_value = (int)$setting['value']; // No need to check if empty, int will give 0

            $setting_code = $form->generate_group_select(
                $element_name,
                $selected_value,
                ['id' => $element_id, 'main_option' => $lang->none]
            );
        } else {
            $typecount = count($type);

            $multi_values = [];

            if ($type[0] === 'checkbox') {
                $multi_values = explode(',', $setting['value']);
            }

            $option_list = [];

            for ($i = 0; $i < count($type); $i++) {
                $optionsexp = explode('=', $type[$i]);

                if (empty($optionsexp[1])) {
                    continue;
                }

                $title_lang = "setting_{$setting['name']}_{$optionsexp[0]}";

                $optionsexp[1] = $lang->{$title_lang};

                if ($type[0] == 'select') {
                    $option_list[$optionsexp[0]] = htmlspecialchars_uni(
                        $optionsexp[1]
                    );
                } elseif ($type[0] == 'radio') {
                    if ($setting['value'] == $optionsexp[0]) {
                        $option_list[$i] = $form->generate_radio_button(
                            $element_name,
                            $optionsexp[0],
                            htmlspecialchars_uni($optionsexp[1]),
                            [
                                'id' => $element_id . '_' . $i,
                                'checked' => 1,
                                'class' => $element_id
                            ]
                        );
                    } else {
                        $option_list[$i] = $form->generate_radio_button(
                            $element_name,
                            $optionsexp[0],
                            htmlspecialchars_uni($optionsexp[1]),
                            [
                                'id' => $element_id . '_' . $i,
                                'class' => $element_id
                            ]
                        );
                    }
                } elseif ($type[0] == 'checkbox') {
                    if (in_array($optionsexp[0], $multi_values)) {
                        $option_list[$i] = $form->generate_check_box(
                            "{$element_name}[]",
                            $optionsexp[0],
                            htmlspecialchars_uni($optionsexp[1]),
                            [
                                'id' => $element_id . '_' . $i,
                                'checked' => 1,
                                'class' => $element_id
                            ]
                        );
                        $option_list[$i] = $form->generate_check_box(
                            "{$element_name}[]",
                            $optionsexp[0],
                            htmlspecialchars_uni($optionsexp[1]),
                            ['id' => $element_id . '_' . $i, 'checked' => 1, 'class' => $element_id]
                        );
                    } else {
                        $option_list[$i] = $form->generate_check_box(
                            "{$element_name}[]",
                            $optionsexp[0],
                            htmlspecialchars_uni($optionsexp[1]),
                            [
                                'id' => $element_id . '_' . $i,
                                'class' => $element_id
                            ]
                        );
                    }
                }
            }

            if ($type[0] == 'select') {
                $setting_code = $form->generate_select_box(
                    $element_name,
                    $option_list,
                    $setting['value'],
                    ['id' => $element_id]
                );
            } else {
                $setting_code = implode('<br />', $option_list);

                if ($type[0] == 'checkbox') {
                    $setting_code .= $form->generate_hidden_field("isvisible_{$setting['name']}", 1);
                }
            }
        }

        // Do we have a custom language variable for this title or description?
        $title_lang = 'setting_' . $setting['name'];

        $desc_lang = $title_lang . '_desc';

        if (!empty($lang->{$title_lang})) {
            $setting['title'] = $lang->{$title_lang};
        }

        if (!empty($lang->{$desc_lang})) {
            $setting['description'] = $lang->{$desc_lang};
        }

        $form_container->output_row(
            htmlspecialchars_uni($setting['title']),
            $setting['description'],
            $setting_code,
            '',
            [],
            ['id' => 'row_' . $element_id]
        );
    }

    $form_container->end();

    $form->output_submit_wrapper($buttons);

    echo '<br />';

    $form->end();

    $page->output_footer();
} else {
    settings_rebuild();

    run_hooks('admin_settings_start');

    $page->add_breadcrumb_item($lang->newpoints_instances, 'index.php?module=newpoints-instances');

    $page->add_breadcrumb_item($instance_object->get_display_name_upper(), url_handler_get());

    $page->add_breadcrumb_item($lang->newpoints_settings, url_handler_get());

    $page->output_header($instance_page_title);

    if (isset($message)) {
        $page->output_inline_message($message);
    }

    $page->output_nav_tabs($sub_tabs, 'newpoints_settings');

    url_handler_set('index.php');

    url_handler_set(url_handler_build([
        'module' => 'newpoints-settings',
        'action' => 'change',
        'instance_id' => $instance_id,
    ]));

    $table = new Table();

    $table->construct_header($lang->setting_groups);

    foreach (['main', 'donations', 'stats', 'logs'] as $core_group) {
        $settings_count = $db->fetch_field(
            $db->simple_select(
                'newpoints_settings',
                'COUNT(sid) as settings',
                "plugin='{$core_group}' AND instance_id='{$instance_id}'"
            ),
            'settings'
        );

        $group_title = htmlspecialchars_uni($lang->{"setting_group_newpoints_{$core_group}"});

        $group_desc = htmlspecialchars_uni($lang->{"setting_group_newpoints_{$core_group}_desc"});

        $edit_url = url_handler_build([
            'plugin' => $core_group,
        ]);

        $table->construct_cell(
            "<strong><a href=\"{$edit_url}\">{$group_title}</a></strong> ({$settings_count} {$lang->bbsettings})<br /><small>{$group_desc}</small>"
        );

        $table->construct_row();
    }

    $plugins_cache = $cache->read('newpoints_plugins');

    $active_plugins = [];

    if (!empty($plugins_cache) && is_array($plugins_cache['active'])) {
        $active_plugins = $plugins_cache['active'];
    }

    $setting_groups_objects = [];

    $hook_arguments = [
        'setting_groups_objects' => &$setting_groups_objects,
        'active_plugins' => &$active_plugins
    ];

    $hook_arguments = run_hooks('admin_settings_intermediate', $hook_arguments);

    if (!empty($active_plugins)) {
        foreach ($active_plugins as $plugin) {
            if (!newpoints_get_plugininfo($plugin)) {
                continue;
            }

            $group_key = str_replace('newpoints_', '', $plugin);

            $settings_count = $db->fetch_field(
                $db->simple_select(
                    'newpoints_settings',
                    'COUNT(sid) as settings_count',
                    "plugin='{$db->escape_string($group_key)}' AND instance_id='{$instance_id}'"
                ),
                'settings_count'
            );

            if (empty($settings_count)) {
                continue;
            }

            $group_lang_var = "setting_group_newpoints_{$group_key}";

            if (!isset($lang->{$group_lang_var})) {
                //_dump($group_lang_var, $plugin);
            }

            $group_title = htmlspecialchars_uni($lang->{$group_lang_var});

            $group_lang_var_desc = "setting_group_newpoints_{$group_key}_desc";

            $group_desc = htmlspecialchars_uni($lang->{$group_lang_var_desc});

            $edit_url = url_handler_build([
                'plugin' => $plugin,
            ]);

            $table->construct_cell(
                "<strong><a href=\"{$edit_url}\">{$group_title}</a></strong> ({$settings_count} {$lang->bbsettings})<br /><small>{$group_desc}</small>"
            );

            $table->construct_row();
        }
    }

    foreach ($setting_groups_objects as $group_key => $group_data) {
        $settings_count = $db->fetch_field(
            $db->simple_select(
                'newpoints_settings',
                'COUNT(sid) as settings_count',
                "plugin='{$db->escape_string($group_key)}' AND instance_id='{$instance_id}'"
            ),
            'settings_count'
        );

        if (empty($settings_count)) {
            continue;
        }

        $group_lang_var = "setting_group_newpoints_{$group_key}";

        $group_title = htmlspecialchars_uni($lang->{$group_lang_var});

        $group_lang_var_desc = "setting_group_newpoints_{$group_key}_desc";

        $group_desc = htmlspecialchars_uni($lang->{$group_lang_var_desc});

        $edit_url = url_handler_build([
            'plugin' => $group_key,
        ]);

        $table->construct_cell(
            "<strong><a href=\"{$edit_url}\">{$group_title}</a></strong> ({$settings_count} {$lang->bbsettings})<br /><small>{$group_desc}</small>"
        );

        $table->construct_row();
    }

    $table->output($instance_page_title);

    echo '</div>';

    $page->output_footer();
}

function newpoints_get_plugininfo($plugin): array
{
    $plugin_file_path = MYBB_ROOT . "inc/plugins/newpoints/plugins/{$plugin}.php";

    if (!file_exists($plugin_file_path)) {
        return [];
    }

    require_once $plugin_file_path;

    $info_func = "{$plugin}_info";

    if (!function_exists($info_func)) {
        return [];
    }

    return $info_func();
}