<?php
/***************************************************************************
 *
 *   NewPoints plugin (/admin/modules/newpoints/settings.php)
 *	 Author: Pirata Nervo
 *   Copyright: � 2014 Pirata Nervo
 *   
 *   Website: http://www.mybb-plugins.com
 *
 *   NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
 *
 ***************************************************************************/
 
/****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.
	
	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.
	
	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

global $lang, $plugins, $page, $db, $mybb, $cache, $config;

$lang->load('newpoints');

$lang->load("config_settings", false, true);

// Change settings for a specified group.
if($mybb->input['action'] == "change")
{
	$plugins->run_hooks("newpoints_admin_settings_change");
	
	if($mybb->request_method == "post")
	{
		if(is_array($mybb->input['upsetting']))
		{
			$forum_group_select = array();
			$query = $db->simple_select('newpoints_settings', 'name', 'type IN (\'forumselect\', \'groupselect\')');
			while($name = $db->fetch_field($query, 'name'))
			{
				$forum_group_select[] = $name;
			}

			foreach($mybb->input['upsetting'] as $name => $value)
			{
				if(!empty($forum_group_select) && in_array($name, $forum_group_select))
				{
					if($value == 'all')
					{
						$value = -1;
					}
					elseif($value == 'custom')
					{
						if(isset($mybb->input['select'][$name]) && is_array($mybb->input['select'][$name]))
						{
							foreach($mybb->input['select'][$name] as &$val)
							{
								$val = (int)$val;
							}
							unset($val);

							$value = implode(',', (array)$mybb->input['select'][$name]);
						}
						else
						{
							$value = '';
						}
					}
					else
					{
						$value = '';
					}
				}

				$value = $db->escape_string($value);
				$db->update_query("newpoints_settings", array('value' => $value), "name='".$db->escape_string($name)."'");
				//$db->update_query("settings", array('value' => $value), "name='".$db->escape_string($name)."'");
			}
		}
		
		rebuild_settings();
		
		$array = array();
		newpoints_rebuild_settings_cache($array);
		
		$plugins->run_hooks("newpoints_admin_settings_change_commit");
			
		// Log admin action
		log_admin_action();

		flash_message($lang->success_settings_updated, 'success');
		admin_redirect("index.php?module=newpoints-settings");
	}
	
	// What type of page
	$cache_groups = $cache_settings = array();
	$mybb->input['plugin'] = trim($mybb->input['plugin']);
	if($mybb->input['plugin'])
	{
		// Cache settings
		$query = $db->simple_select("newpoints_settings", "*", "plugin='".$db->escape_string($mybb->input['plugin'])."'", array('order_by' => 'disporder'));
		while($setting = $db->fetch_array($query))
		{
			$cache_settings[$setting['plugin']][$setting['sid']] = $setting;
		}
		
		if(!$db->num_rows($query))
        {
            flash_message($lang->error_no_settings_found, 'error');
            admin_redirect("index.php?module=newpoints-settings");    
        }
		
		$groupinfo['plugin'] = $plugin = $mybb->input['plugin'];
		
		if ($mybb->input['plugin'] == "income" || $mybb->input['plugin'] == "main")
		{
			$lang_var = "newpoints_settings_".$mybb->input['plugin'];
			
			$groupinfo['title'] = $lang->$lang_var;
			$groupinfo['description'] = $lang->$lang_var."_description";
		}
		else {
			$groupinfo = newpoints_get_plugininfo($groupinfo['plugin']);
			$groupinfo['plugin'] = $plugin;
			$groupinfo['title'] = htmlspecialchars_uni($groupinfo['name']);
			$groupinfo['description'] = htmlspecialchars_uni($groupinfo['description']);
		}
		
		// Page header
		$page->add_breadcrumb_item($groupinfo['title']);
		$page->output_header($lang->board_settings." - {$groupinfo['title']}");
		
		$sub_tabs['newpoints_settings'] = array(
			'title'			=> $lang->newpoints_settings,
			'link'			=> 'index.php?module=newpoints-settings',
			'description'	=> $lang->newpoints_settings_description
		);
		
		$sub_tabs['newpoints_settings_change'] = array(
			'title'			=> $lang->newpoints_settings_change,
			'link'			=> 'index.php?module=newpoints-settings&amp;action=change',
			'description'	=> $lang->newpoints_settings_change_description
		);
		
		$page->output_nav_tabs($sub_tabs, 'newpoints_settings_change');
		
		$form = new Form("index.php?module=newpoints-settings&amp;action=change", "post", "change");
	
		echo $form->generate_hidden_field("gid", $groupinfo['gid']);
	}
	else
	{
		flash_message($lang->newpoints_select_plugin, 'error');
		admin_redirect("index.php?module=newpoints-settings");
	}

	// Build rest of page
	$buttons[] = $form->generate_submit_button($lang->save_settings);
	
	$form_container = new FormContainer($groupinfo['title']);
	
	if(empty($cache_settings[$groupinfo['plugin']]))
	{
		$form_container->output_cell($lang->error_no_settings_found);
		$form_container->construct_row();
		
		$form_container->end();
		echo '<br />';
		
		$form->end();
	
		$page->output_footer();
	}
	
	foreach($cache_settings[$groupinfo['plugin']] as $setting)
	{
		$options = "";
		$type = explode("\n", $setting['type']);
		$type[0] = trim($type[0]);
		$element_name = "upsetting[{$setting['name']}]";
		$element_id = "setting_{$setting['name']}";
		if($type[0] == "text" || $type[0] == "")
		{
			$setting_code = $form->generate_text_box($element_name, $setting['value'], array('id' => $element_id));
		}
		else if($type[0] == "numeric")
		{
			$setting_code = $form->generate_numeric_field($element_name, $setting['value'], array('id' => $element_id));
		}
		else if($type[0] == "textarea")
		{
			$setting_code = $form->generate_text_area($element_name, $setting['value'], array('id' => $element_id));
		}
		else if($type[0] == "yesno")
		{
			$setting_code = $form->generate_yes_no_radio($element_name, $setting['value'], true, array('id' => $element_id.'_yes', 'class' => $element_id), array('id' => $element_id.'_no', 'class' => $element_id));
		}
		else if($type[0] == "onoff")
		{
			$setting_code = $form->generate_on_off_radio($element_name, $setting['value'], true, array('id' => $element_id.'_on', 'class' => $element_id), array('id' => $element_id.'_off', 'class' => $element_id));
		}
		else if($type[0] == "cpstyle")
		{
			$dir = @opendir(constant('MYBB_ROOT').$config['admin_dir']."/styles");
			while($folder = readdir($dir))
			{
				if($folder != "." && $folder != ".." && @file_exists(constant('MYBB_ROOT').$config['admin_dir']."/styles/$folder/main.css"))
				{
					$folders[$folder] = ucfirst($folder);
				}
			}
			closedir($dir);
			ksort($folders);
			$setting_code = $form->generate_select_box($element_name, $folders, $setting['value'], array('id' => $element_id));
		}
		else if($type[0] == "language") 
		{
			$languages = $lang->get_languages();
			$setting_code = $form->generate_select_box($element_name, $languages, $setting['value'], array('id' => $element_id));
		}
		else if($type[0] == "adminlanguage") 
		{
			$languages = $lang->get_languages(1);
			$setting_code = $form->generate_select_box($element_name, $languages, $setting['value'], array('id' => $element_id));
		}
		else if($type[0] == "passwordbox")
		{
			$setting_code = $form->generate_password_box($element_name, $setting['value'], array('id' => $element_id));
		}
		else if($type[0] == "php")
		{
			$setting['type'] = substr($setting['type'], 3);
			eval("\$setting_code = \"".$setting['type']."\";");
		}
		else if($type[0] == "forumselect")
		{
			$selected_values = '';
			if($setting['value'] != '' && $setting['value'] != -1)
			{
				$selected_values = explode(',', (string)$setting['value']);

				foreach($selected_values as &$value)
				{
					$value = (int)$value;
				}
				unset($value);
			}

			$forum_checked = array('all' => '', 'custom' => '', 'none' => '');
			if($setting['value'] == -1)
			{
				$forum_checked['all'] = 'checked="checked"';
			}
			elseif($setting['value'] != '')
			{
				$forum_checked['custom'] = 'checked="checked"';
			}
			else
			{
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
							<td>".$form->generate_forum_select('select['.$setting['name'].'][]', $selected_values, array('id' => $element_id, 'multiple' => true, 'size' => 5))."</td>
						</tr>
					</table>
				</dd>
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"none\" {$forum_checked['none']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
			</dl>
			<script type=\"text/javascript\">
				checkAction('{$element_id}');
			</script>";
		}
		else if($type[0] == "groupselect")
		{
			$selected_values = '';
			if($setting['value'] != '' && $setting['value'] != -1)
			{
				$selected_values = explode(',', (string)$setting['value']);

				foreach($selected_values as &$value)
				{
					$value = (int)$value;
				}
				unset($value);
			}

			$group_checked = array('all' => '', 'custom' => '', 'none' => '');
			if($setting['value'] == -1)
			{
				$group_checked['all'] = 'checked="checked"';
			}
			elseif($setting['value'] != '')
			{
				$group_checked['custom'] = 'checked="checked"';
			}
			else
			{
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
							<td>".$form->generate_group_select('select['.$setting['name'].'][]', $selected_values, array('id' => $element_id, 'multiple' => true, 'size' => 5))."</td>
						</tr>
					</table>
				</dd>
				<dt><label style=\"display: block;\"><input type=\"radio\" name=\"{$element_name}\" value=\"none\" {$group_checked['none']} class=\"{$element_id}_forums_groups_check\" onclick=\"checkAction('{$element_id}');\" style=\"vertical-align: middle;\" /> <strong>{$lang->none}</strong></label></dt>
			</dl>
			<script type=\"text/javascript\">
				checkAction('{$element_id}');
			</script>";
		}
		else
		{
			for($i=0; $i < count($type); $i++)
			{
				$optionsexp = explode("=", $type[$i]);
				if(!$optionsexp[1])
				{
					continue;
				}
				$title_lang = "setting_{$setting['name']}_{$optionsexp[0]}";
				if($lang->$title_lang)
				{
					$optionsexp[1] = $lang->$title_lang;
				}
				
				if($type[0] == "select")
				{
					$option_list[$optionsexp[0]] = htmlspecialchars_uni($optionsexp[1]);
				}
				else if($type[0] == "radio")
				{
					if($setting['value'] == $optionsexp[0])
					{
						$option_list[$i] = $form->generate_radio_button($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, "checked" => 1, 'class' => $element_id));
					}
					else
					{
						$option_list[$i] = $form->generate_radio_button($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, 'class' => $element_id));
					}
				}
				else if($type[0] == "checkbox")
				{
					if($setting['value'] == $optionsexp[0])
					{
						$option_list[$i] = $form->generate_check_box($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, "checked" => 1, 'class' => $element_id));
					}
					else
					{
						$option_list[$i] = $form->generate_check_box($element_name, $optionsexp[0], htmlspecialchars_uni($optionsexp[1]), array('id' => $element_id.'_'.$i, 'class' => $element_id));
					}
				}
			}
			if($type[0] == "select")
			{
				$setting_code = $form->generate_select_box($element_name, $option_list, $setting['value'], array('id' => $element_id));
			}
			else
			{
				$setting_code = implode("<br />", $option_list);
			}
			$option_list = array();
		}
		// Do we have a custom language variable for this title or description?
		$title_lang = "setting_".$setting['name'];
		$desc_lang = $title_lang."_desc";
		if($lang->$title_lang)
		{
			$setting['title'] = $lang->$title_lang;
		}
		if($lang->$desc_lang)
		{
			$setting['description'] = $lang->$desc_lang;
		}
		$form_container->output_row(htmlspecialchars_uni($setting['title']), $setting['description'], $setting_code, '', array(), array('id' => 'row_'.$element_id));
	}
	$form_container->end();
	
	$form->output_submit_wrapper($buttons);
	echo '<br />';

	$form->end();
	
	$page->output_footer();
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("newpoints_admin_settings_start");
	
	$page->add_breadcrumb_item($lang->newpoints_settings, 'index.php?module=newpoints-settings');
	
	$page->output_header($lang->board_settings);
	if(isset($message))
	{
		$page->output_inline_message($message);
	}
		
	$sub_tabs['newpoints_settings'] = array(
		'title'			=> $lang->newpoints_settings,
		'link'			=> 'index.php?module=newpoints-settings',
		'description'	=> $lang->newpoints_settings_description
	);
	
	$sub_tabs['newpoints_settings_change'] = array(
		'title'			=> $lang->newpoints_settings_change,
		'link'			=> 'index.php?module=newpoints-settings&amp;action=change',
		'description'	=> $lang->newpoints_settings_change
	);
	
	$page->output_nav_tabs($sub_tabs, 'newpoints_settings');

	$table = new Table;
	$table->construct_header($lang->setting_groups);
	
	// default settings
	$group['settingcount'] = $db->fetch_field($db->simple_select("newpoints_settings", "COUNT(sid) as settings", "plugin='main'"), 'settings');
	$group_title = htmlspecialchars_uni($lang->newpoints_settings_main);
	$group_desc = htmlspecialchars_uni($lang->newpoints_settings_main_description);	
	$table->construct_cell("<strong><a href=\"index.php?module=newpoints-settings&amp;action=change&amp;plugin=main\">{$group_title}</a></strong> ({$group['settingcount']} {$lang->bbsettings})<br /><small>{$group_desc}</small>");
	$table->construct_row();
	
	$group['settingcount'] = $db->fetch_field($db->simple_select("newpoints_settings", "COUNT(sid) as settings", "plugin='income'"), 'settings');
	$group_title = htmlspecialchars_uni($lang->newpoints_settings_income);
	$group_desc = htmlspecialchars_uni($lang->newpoints_settings_income_description);	
	$table->construct_cell("<strong><a href=\"index.php?module=newpoints-settings&amp;action=change&amp;plugin=income\">{$group_title}</a></strong> ({$group['settingcount']} {$lang->bbsettings})<br /><small>{$group_desc}</small>");
	$table->construct_row();

	$plugins_cache = $cache->read("newpoints_plugins");

	$active_plugins = [];

	if(!empty($plugins_cache) && is_array($plugins_cache['active'])) {
		$active_plugins = $plugins_cache['active'];
	}
	
	if (!empty($active_plugins))
	{
		foreach($active_plugins as $plugin)
		{
			$group = newpoints_get_plugininfo($plugin);
			if ($group === false)
				continue;
	
			$group['title'] = $group['name'];
			$group['settingcount'] = $db->fetch_field($db->simple_select("newpoints_settings", "COUNT(sid) as settings", "plugin='".$db->escape_string($plugin)."'"), 'settings');
			
			if ($group['settingcount'] == 0) continue; // skip setting group is we have no settings
			
			$group_lang_var = "setting_group_{$group['name']}";
			if($lang->$group_lang_var)
			{
				$group_title = htmlspecialchars_uni($lang->$group_lang_var);
			}
			else
			{
				$group_title = htmlspecialchars_uni($group['title']);
			}
			
			$group_desc_lang_var = "setting_group_{$group['name']}_desc";
			if($lang->$group_desc_lang_var)
			{
				$group_desc = htmlspecialchars_uni($lang->$group_desc_lang_var);
			}
			else
			{
				$group_desc = htmlspecialchars_uni($group['description']);
			}
					
			$table->construct_cell("<strong><a href=\"index.php?module=newpoints-settings&amp;action=change&amp;plugin=".htmlspecialchars_uni($plugin)."\">{$group_title}</a></strong> ({$group['settingcount']} {$lang->bbsettings})<br /><small>{$group_desc}</small>");
			$table->construct_row();
		}
	}
	$table->output($lang->board_settings);
	
	echo '</div>';
	
	$page->output_footer();
}

function newpoints_get_plugininfo($plugin)
{
	global $mybb, $plugins, $theme, $db, $templates, $cache;

	// Ignore potentially missing plugins.
	if(!file_exists(constant('MYBB_ROOT')."inc/plugins/newpoints/".$plugin.".php"))
	{
		return false;
	}

	require_once constant('MYBB_ROOT')."inc/plugins/newpoints/".$plugin.".php";

	$info_func = "{$plugin}_info";
	if(!function_exists($info_func))
	{
		return false;
	}
	$plugin_info = $info_func();
	
	return $plugin_info;
}