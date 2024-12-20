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
use function Newpoints\Core\settings_rebuild_cache;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb, $cache, $config;

language_load();

$lang->load('config_settings', false, true);

$sub_tabs['newpoints_settings'] = [
    'title' => $lang->newpoints_settings,
    'link' => 'index.php?module=newpoints-settings',
    'description' => $lang->newpoints_settings_description
];

if ($mybb->get_input('action') == 'change') {
    $sub_tabs['newpoints_settings_change'] = [
        'title' => $lang->newpoints_settings_change,
        'link' => 'index.php?module=newpoints-settings&amp;action=change',
        'description' => $lang->newpoints_settings_change_description
    ];
}

// Change settings for a specified group.
if ($mybb->get_input('action') == 'change') {
    run_hooks('admin_settings_change');

    if ($mybb->request_method == 'post') {
        $upsetting = $mybb->get_input('upsetting', MyBB::INPUT_ARRAY);

        $select = $mybb->get_input('select', MyBB::INPUT_ARRAY);

        if (!empty($upsetting)) {
            $forum_group_select = [];
            $query = $db->simple_select('newpoints_settings', 'name', "type IN('forumselect', 'groupselect')");
            while ($name = $db->fetch_field($query, 'name')) {
                $forum_group_select[] = $name;
            }

            foreach ($upsetting as $name => $value) {
                if (!empty($forum_group_select) && in_array($name, $forum_group_select)) {
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

                $value = $db->escape_string($value);
                $db->update_query(
                    'newpoints_settings',
                    ['value' => $value],
                    "name='" . $db->escape_string($name) . "'"
                );
                //$db->update_query("settings", array('value' => $value), "name='".$db->escape_string($name)."'");
            }
        }

        rebuild_settings();

        $array = [];
        settings_rebuild_cache($array);

        run_hooks('admin_settings_change_commit');

        // Log admin action
        log_admin_action();

        flash_message($lang->success_settings_updated, 'success');
        admin_redirect('index.php?module=newpoints-settings');
    }

    // What type of page
    $cache_groups = $cache_settings = [];

    $mybb->input['plugin'] = trim($mybb->get_input('plugin'));

    $group_key = '';

    if ($mybb->get_input('plugin')) {
        $groupinfo = [];

        $groupinfo['plugin'] = $plugin = $mybb->get_input('plugin');

        $group_key = str_replace('newpoints_', '', $plugin);

        // Cache settings
        $query = $db->simple_select(
            'newpoints_settings',
            '*',
            "plugin='" . $db->escape_string($group_key) . "'",
            ['order_by' => 'disporder']
        );

        if (!$db->num_rows($query)) {
            flash_message($lang->error_no_settings_found, 'error');
            admin_redirect('index.php?module=newpoints-settings');
        }

        while ($setting = $db->fetch_array($query)) {
            $cache_settings[$setting['plugin']][$setting['sid']] = $setting;
        }

        if (in_array($plugin, ['main', 'donations', 'stats', 'logs'], true)) {
            $lang_var = 'setting_group_newpoints_' . $mybb->get_input('plugin');

            $groupinfo['title'] = $lang->$lang_var;
            $groupinfo['description'] = $lang->$lang_var . '_description';
        } elseif ($groupinfo = newpoints_get_plugininfo($groupinfo['plugin'])) {
            $groupinfo['plugin'] = $plugin;
            $groupinfo['title'] = htmlspecialchars_uni($groupinfo['name']);
            $groupinfo['description'] = htmlspecialchars_uni($groupinfo['description']);
        } else {
            $setting_groups_objects = [];

            $setting_groups_objects = run_hooks('admin_settings_commit_start', $setting_groups_objects);

            if (!isset($setting_groups_objects[$plugin])) {
                flash_message($lang->error_no_settings_found, 'error');
                admin_redirect('index.php?module=newpoints-settings');
            }

            $groupinfo['plugin'] = $group_key = $plugin;

            $group_lang_var = "setting_group_{$group_key}";

            if (!empty($lang->{$group_lang_var})) {
                $groupinfo['title'] = htmlspecialchars_uni($lang->{$group_lang_var});
            } else {
                $groupinfo['title'] = htmlspecialchars_uni($group_key);
            }

            $group_desc_lang_var = "setting_group_{$group_key}_desc";

            if (!empty($lang->{$group_desc_lang_var})) {
                $groupinfo['description'] = htmlspecialchars_uni($lang->{$group_desc_lang_var});
            } else {
                $groupinfo['description'] = '';
            }
        }

        // Page header
        $page->add_breadcrumb_item($groupinfo['title']);
        $page->output_header($lang->board_settings . " - {$groupinfo['title']}");

        $page->output_nav_tabs($sub_tabs, 'newpoints_settings_change');

        $form = new Form('index.php?module=newpoints-settings&amp;action=change', 'post', 'change');
    } else {
        flash_message($lang->newpoints_select_plugin, 'error');
        admin_redirect('index.php?module=newpoints-settings');
    }

    // Build rest of page
    $buttons[] = $form->generate_submit_button($lang->save_settings);

    $form_container = new FormContainer($groupinfo['title']);

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
        } else {
            $option_list = [];

            for ($i = 0; $i < count($type); $i++) {
                $optionsexp = explode('=', $type[$i]);

                if (empty($optionsexp[1])) {
                    continue;
                }

                $title_lang = "setting_{$setting['name']}_{$optionsexp[0]}";

                if (($lang->$title_lang)) {
                    $optionsexp[1] = $lang->$title_lang;
                }

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
                    if ($setting['value'] == $optionsexp[0]) {
                        $option_list[$i] = $form->generate_check_box(
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
                        $option_list[$i] = $form->generate_check_box(
                            $element_name,
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
    run_hooks('admin_settings_start');

    $page->add_breadcrumb_item($lang->newpoints_settings, 'index.php?module=newpoints-settings');

    $page->output_header($lang->board_settings);
    if (isset($message)) {
        $page->output_inline_message($message);
    }

    $page->output_nav_tabs($sub_tabs, 'newpoints_settings');

    $table = new Table();

    $table->construct_header($lang->setting_groups);

    foreach (['main', 'donations', 'stats', 'logs'] as $core_group) {
        $settingcount = $db->fetch_field(
            $db->simple_select('newpoints_settings', 'COUNT(sid) as settings', "plugin='{$core_group}'"),
            'settings'
        );

        $group_title = htmlspecialchars_uni($lang->{"setting_group_newpoints_{$core_group}"});

        $group_desc = htmlspecialchars_uni($lang->{"setting_group_newpoints_{$core_group}_desc"});

        $table->construct_cell(
            "<strong><a href=\"index.php?module=newpoints-settings&amp;action=change&amp;plugin={$core_group}\">{$group_title}</a></strong> ({$settingcount} {$lang->bbsettings})<br /><small>{$group_desc}</small>"
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
            $plugin_info = newpoints_get_plugininfo($plugin);

            if ($plugin_info === false) {
                continue;
            }

            $group_key = str_replace('newpoints_', '', $plugin);

            $settings_count = $db->fetch_field(
                $db->simple_select(
                    'newpoints_settings',
                    'COUNT(sid) as settings_count',
                    "plugin='{$db->escape_string($group_key)}'"
                ),
                'settings_count'
            );

            if (empty($settings_count)) {
                continue;
            }

            $group_lang_var = "setting_group_{$group_key}";

            if (!empty($lang->{$group_lang_var})) {
                $group_title = htmlspecialchars_uni($lang->{$group_lang_var});
            } else {
                $group_title = htmlspecialchars_uni($group_key);
            }

            $group_lang_var_desc = "setting_group_{$group_key}_desc";

            if (!empty($lang->{$group_lang_var_desc})) {
                $group_desc = htmlspecialchars_uni($lang->{$group_lang_var_desc});
            } else {
                $group_desc = htmlspecialchars_uni($plugin_info['description']);
            }


            $group_lang_var = "setting_group_{$group_key}";

            if (!empty($lang->$group_lang_var)) {
                $group_title = htmlspecialchars_uni($lang->$group_lang_var);
            } else {
                $group_title = htmlspecialchars_uni($plugin_info['name']);
            }

            $table->construct_cell(
                "<strong><a href=\"index.php?module=newpoints-settings&amp;action=change&amp;plugin=" . htmlspecialchars_uni(
                    $plugin
                ) . "\">{$group_title}</a></strong> ({$settings_count} {$lang->bbsettings})<br /><small>{$group_desc}</small>"
            );
            $table->construct_row();
        }
    }

    foreach ($setting_groups_objects as $group_key => $group_data) {
        $settings_count = $db->fetch_field(
            $db->simple_select(
                'newpoints_settings',
                'COUNT(sid) as settings_count',
                "plugin='{$db->escape_string($group_key)}'"
            ),
            'settings_count'
        );

        if (empty($settings_count)) {
            continue;
        }

        $group_lang_var = "setting_group_{$group_key}";

        if (!empty($lang->{$group_lang_var})) {
            $group_title = htmlspecialchars_uni($lang->{$group_lang_var});
        } else {
            $group_title = htmlspecialchars_uni($group_key);
        }

        $group_lang_var_desc = "setting_group_{$group_key}_desc";

        if (!empty($lang->{$group_lang_var_desc})) {
            $group_desc = htmlspecialchars_uni($lang->{$group_lang_var_desc});
        } else {
            $group_desc = '';
        }

        $table->construct_cell(
            "<strong><a href=\"index.php?module=newpoints-settings&amp;action=change&amp;plugin=" . htmlspecialchars_uni(
                $group_key
            ) . "\">{$group_title}</a></strong> ({$settings_count} {$lang->bbsettings})<br /><small>{$group_desc}</small>"
        );

        $table->construct_row();
    }

    $table->output($lang->board_settings);

    echo '</div>';

    $page->output_footer();
}

function newpoints_get_plugininfo($plugin)
{
    global $mybb, $plugins, $theme, $db, $templates, $cache;

    $plugin_file_path = MYBB_ROOT . "inc/plugins/newpoints/plugins/{$plugin}.php";

    // Ignore potentially missing plugins.
    if (!file_exists($plugin_file_path)) {
        return false;
    }

    require_once $plugin_file_path;

    $info_func = "{$plugin}_info";

    if (!function_exists($info_func)) {
        return false;
    }

    $plugin_info = $info_func();

    return $plugin_info;
}