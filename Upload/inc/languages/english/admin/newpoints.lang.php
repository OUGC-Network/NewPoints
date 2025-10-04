<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/languages/english/admin/newpoints.lang.php)
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

$l['newpoints'] = 'NewPoints';
$l['newpoints_description'] = 'NewPoints is a complex but efficient points system for MyBB.';
$l['newpoints_submit_button'] = 'Submit';
$l['newpoints_reset_button'] = 'Reset';
$l['newpoints_error'] = 'An unknown error has occurred.';
$l['newpoints_continue_button'] = 'Continue';
$l['newpoints_click_continue'] = 'Click Continue to proceed.';
$l['newpoints_delete'] = 'Delete';
$l['newpoints_missing_fields'] = 'There is one or more missing fields.';
$l['newpoints_edit'] = 'Edit';

$l['setting_group_newpoints'] = 'NewPoints';
$l['setting_group_newpoints_desc'] = 'NewPoints is a complex but efficient points system for MyBB.';

///////////////// Plugins
$l['newpoints_plugins'] = 'Plugins';
$l['newpoints_plugins_description'] = 'Here you can manage NewPoints plugins.';
$l['newpoints_plugin_incompatible'] = 'This plugin is incompatible with NewPoints {1}';

$l['newpoints_plugins_check_updates'] = 'Check Updates';
$l['newpoints_plugins_check_updates_description'] = 'Here you can manage NewPoints plugins.';

$l['newpoints_plugins_error_version_check_no_supported_plugins'] = 'None of the plugins installed support version checking.';
$l['newpoints_plugins_error_communication_problem'] = 'There was a problem communicating with the MyBB modifications server. Please try again in a few minutes.';
$l['newpoints_plugins_error_communication_problem_no_input'] = 'Error code 1: No input specified.';
$l['newpoints_plugins_error_communication_problem_no_plugin_ids'] = 'Error code 2: No plugin ids specified.';
$l['newpoints_plugins_error_version_check_vulnerable'] = '[Vulnerable plugin]:';
$l['newpoints_plugins_error_version_vulnerable_notes'] = 'This submission has currently been marked as vulnerable by the MyBB Staff. We recommend complete removal of this modification. Please see the notes below: ';

$l['newpoints_plugins_success_plugins_up_to_date'] = 'Congratulations, all of your plugins are up to date.';

$l['newpoints_plugins_plugin'] = 'Plugin';
$l['newpoints_plugins_your_version'] = 'Your Version';
$l['newpoints_plugins_latest_version'] = 'Latest Version';
$l['newpoints_plugins_deactivate'] = 'Deactivate';
$l['newpoints_plugins_download'] = 'Download';
$l['newpoints_plugins_plugin_updates'] = 'Plugin Updates';

$l['active_plugin'] = 'Active Plugins';
$l['inactive_plugin'] = 'Inactive Plugins';
$l['activate'] = 'Activate';
$l['install_and_activate'] = 'Install &amp; Activate';
$l['uninstall'] = 'Uninstall';
$l['created_by'] = 'Created by';
$l['no_plugins'] = 'There are no plugins on your forum at this time.';
$l['no_active_plugins'] = 'There are no active plugins on your forum.';
$l['no_inactive_plugins'] = 'There are no inactive plugins available.';

///////////////// Settings
$l['newpoints_settings_instance'] = '{1} Settings';
$l['newpoints_settings'] = 'Settings';
$l['newpoints_settings_description'] = 'Here you can configure global settings.';
$l['newpoints_settings_instance_description'] = 'Here you can configure settings for the {1} instance.';
$l['newpoints_settings_change'] = 'Change';
$l['newpoints_settings_change_description'] = 'Change global settings.';
$l['newpoints_settings_change_instance_description'] = 'Change the {3} settings for the {1} instance.';
$l['newpoints_select_plugin'] = 'You must select a group.';

///////////////// Log
$l['newpoints_log'] = 'Log';
$l['newpoints_log_description'] = 'Manage log entries.';
$l['newpoints_log_action'] = 'Action';
$l['newpoints_log_data'] = 'Data';
$l['newpoints_log_user'] = 'User';
$l['newpoints_log_date'] = 'Date';
$l['newpoints_log_options'] = 'Options';
$l['newpoints_no_log_entries'] = 'Could not find any log entries.';
$l['newpoints_log_entries'] = 'Log entries';
$l['newpoints_log_notice'] = 'Note: some statistics are based off log entries.';
$l['newpoints_log_deleteconfirm'] = 'Are you sure you want to delete the selected log entry?';
$l['newpoints_log_invalid'] = 'Invalid log entry.';
$l['newpoints_log_deleted'] = 'Log entry successfully deleted.';
$l['newpoints_log_prune'] = 'Prune log entries';
$l['newpoints_older_than'] = 'Older than';
$l['newpoints_older_than_desc'] = 'Prune log entries older than the number of days you enter.';
$l['newpoints_log_pruned'] = 'Log entries successfully pruned.';
$l['newpoints_log_pruneconfirm'] = 'Are you sure you want to prune log entries?';
$l['newpoints_invalid_username'] = 'Invalid username selected.';
$l['newpoints_log_filter'] = 'Filters';
$l['newpoints_filter_username'] = 'Username';
$l['newpoints_filter_username_desc'] = 'Enter a username to filter by. This can be empty.';
$l['newpoints_filter_actions'] = 'Actions';
$l['newpoints_filter_actions_desc'] = 'Select the actions you want to filter.';
$l['newpoints_select_actions'] = 'Select Actions';
$l['newpoints_filter'] = 'Filters enabled:<br />{1}';
$l['newpoints_username'] = 'Username';

///////////////// Maintenance
$l['newpoints_recount_from_logs'] = 'Recount User NewPoints From Logs';
$l['newpoints_recount_from_logs_description'] = 'When this is run, the NewPoints amount for each user will be updated to reflect the arithmetic subtraction of charge logs from income logs.';
$l['newpoints_recount_from_logs_success'] = 'The user {2} have been rebuilt from logs successfully.';

$l['newpoints_recount'] = 'Recount User NewPoints from Settings';
$l['newpoints_recount_desc'] = 'When this is run, the NewPoints amount for each user will be updated to reflect its current live value based on the income settings.';
$l['newpoints_recount_success'] = 'The user {2} have been rebuilt from settings successfully.';
$l['newpoints_reset'] = 'Reset User NewPoints';
$l['newpoints_reset_success'] = 'The reset of user {2} was successful.';
$l['newpoints_reset_desc'] = 'When this is run, the NewPoints amount for each user will be updated to reflect this value.';
$l['newpoints_reset_amount'] = 'Amount per user';
$l['newpoints_invalid_user'] = 'Invalid user.';

///////////////// Forum Rules
$l['newpoints_forumrules'] = 'Forum Rules';
$l['newpoints_forumrules_description'] = 'Manage forum rules and options.';
$l['newpoints_forumrules_add'] = 'Add';
$l['newpoints_forumrules_add_description'] = 'Add a new rule.';
$l['newpoints_forumrules_edit'] = 'Edit';
$l['newpoints_forumrules_edit_description'] = 'Edit an existing rules.';
$l['newpoints_forumrules_delete'] = 'Delete';
$l['newpoints_forumrules_title'] = 'Forum Title';
$l['newpoints_forumrules_name'] = 'Rule Name';
$l['newpoints_forumrules_options'] = 'Options';
$l['newpoints_forumrules_none'] = 'Could not find any rules.';
$l['newpoints_forumrules_rules'] = 'Forum Rules';
$l['newpoints_forumrules_addrule'] = 'Add Forum Rule';
$l['newpoints_forumrules_editrule'] = 'Edit Forum Rule';
$l['newpoints_forumrules_forum'] = 'Forum';
$l['newpoints_forumrules_forum_desc'] = 'Select the forum affected by this rule.';
$l['newpoints_forumrules_name_desc'] = 'Enter the name of the rule.';
$l['newpoints_forumrules_desc'] = 'Description';
$l['newpoints_forumrules_desc_desc'] = 'Enter a description of the rule.';
$l['newpoints_forumrules_rate'] = 'Income Rate';
$l['newpoints_forumrules_rate_desc'] = 'Enter the income rate for the selected forum. Default is 1';
$l['newpoints_forumrules_added'] = 'A new forum rule has been successfully added.';
$l['newpoints_select_forum'] = 'Select a forum';
$l['newpoints_forumrules_notice'] = 'Note: forums without rules have an income rate of 1 and have no minimum points to view or post.';
$l['newpoints_forumrules_invalid'] = 'Invalid rule.';
$l['newpoints_forumrules_edited'] = 'The selected rule has been edited successfully';
$l['newpoints_forumrules_deleted'] = 'The selected rule has been deleted successfully';
$l['newpoints_forumrules_deleteconfirm'] = 'Are you sure you want to delete the selected rule?';

///////////////// Group Rules
$l['newpoints_grouprules'] = 'User Group Rules';
$l['newpoints_grouprules_description'] = 'Manage usergroup rules and options.';
$l['newpoints_grouprules_add'] = 'Add';
$l['newpoints_grouprules_add_description'] = 'Add a new rule.';
$l['newpoints_grouprules_edit'] = 'Edit';
$l['newpoints_grouprules_edit_description'] = 'Edit an existing rules.';
$l['newpoints_grouprules_delete'] = 'Delete';
$l['newpoints_grouprules_title'] = 'Group Title';
$l['newpoints_grouprules_name'] = 'Rule Name';
$l['newpoints_grouprules_options'] = 'Options';
$l['newpoints_grouprules_none'] = 'Could not find any rules.';
$l['newpoints_grouprules_rules'] = 'Group Rules';
$l['newpoints_grouprules_addrule'] = 'Add Group Rule';
$l['newpoints_grouprules_editrule'] = 'Edit Group Rule';
$l['newpoints_grouprules_group'] = 'User Group';
$l['newpoints_grouprules_group_desc'] = 'Select the group affected by this rule.';
$l['newpoints_grouprules_name_desc'] = 'Enter the name of the rule.';
$l['newpoints_grouprules_desc'] = 'Description';
$l['newpoints_grouprules_desc_desc'] = 'Enter a description of the rule.';
$l['newpoints_grouprules_rate'] = 'Income Rate';
$l['newpoints_grouprules_rate_desc'] = 'Enter the income rate for the selected group. Default is 1';
$l['newpoints_grouprules_added'] = 'A new user group rule has been successfully added.';
$l['newpoints_select_group'] = 'Select a group';
$l['newpoints_grouprules_notice'] = 'Note: groups without rules have an income rate of 1 and have do not have auto payments set.';
$l['newpoints_grouprules_invalid'] = 'Invalid rule.';
$l['newpoints_grouprules_edited'] = 'The selected rule has been edited successfully';
$l['newpoints_grouprules_deleted'] = 'The selected rule has been deleted successfully';
$l['newpoints_grouprules_deleteconfirm'] = 'Are you sure you want to delete the selected rule?';

$l['newpoints_instances'] = 'Instances';
$l['newpoints_instances_description'] = 'Manage NewPoints instances.';
$l['newpoints_instances_title'] = 'NewPoints Instances';
$l['newpoints_instances_thead_id'] = 'ID';
$l['newpoints_instances_thead_id'] = 'ID';
$l['newpoints_instances_thead_name'] = 'Name';
$l['newpoints_instances_thead_column'] = 'Users Column';
$l['newpoints_instances_thead_main_file'] = 'Main File';
$l['newpoints_instances_thead_enabled'] = 'Enabled';
$l['newpoints_instances_thead_options_settings'] = 'Settings';
$l['newpoints_instances_thead_options_rebuild_columns'] = 'Rebuild Columns';

$l['newpoints_instances_rebuild_columns_success'] = 'The users columns for the {1} instance has been created successfully.';

$l['newpoints_instances_add'] = 'Add';
$l['newpoints_instances_add_description'] = 'Add a new NewPoints instance.';

$l['newpoints_instances_edit'] = 'Edit';
$l['newpoints_instances_edit_description'] = 'Update a new NewPoints instance.';

///////////////// Upgrades
$l['newpoints_upgrades'] = 'Upgrades';
$l['newpoints_upgrades_description'] = 'Upgrade NewPoints from here.';
$l['newpoints_upgrades_name'] = 'Name';
$l['newpoints_upgrades_run'] = 'Run';
$l['newpoints_upgrades_confirm_run'] = 'Are you sure you want to run the selected upgrade file?';
$l['newpoints_run'] = 'Run';
$l['newpoints_no_upgrades'] = 'No upgrades found.';
$l['newpoints_upgrades_notice'] = 'You should backup your database before running an upgrade script.<br /><small>Only run upgrade files if you\'re sure about what you\'re doing</small>';
$l['newpoints_upgrades_ran'] = 'Upgrade script ran successfully.';
$l['newpoints_upgrades_newversion'] = 'New version';

$l['newpoints_plugin_library'] = 'This plugin requires <a href="{1}">PluginLibrary</a> version {2} or later to be uploaded to your forum.';

$l['setting_group_newpoints_donations'] = 'Donations';
$l['setting_group_newpoints_donations_desc'] = 'These settings are related to donations.';
$l['setting_newpoints_donations_menu_order'] = 'Menu Order';
$l['setting_newpoints_donations_menu_order_desc'] = 'Order for the donations page page in the NewPoints menu item.';
$l['setting_newpoints_donations_flood_minutes'] = 'Flood Control: Minutes';
$l['setting_newpoints_donations_flood_minutes_desc'] = 'Number of minutes to wait between maximum donations.';
$l['setting_newpoints_donations_flood_limit'] = 'Flood Control';
$l['setting_newpoints_donations_flood_limit_desc'] = 'Maximum donations a user can send per flood control threshold.';

$l['setting_group_newpoints_stats'] = 'Stats';
$l['setting_group_newpoints_stats_desc'] = 'These settings are related to the stats page.';
$l['setting_newpoints_stats_menu_order'] = 'Menu Order';
$l['setting_newpoints_stats_menu_order_desc'] = 'Order for the stat page in the NewPoints menu item.';
$l['setting_newpoints_stats_richest_users_limit'] = 'Richest Users';
$l['setting_newpoints_stats_richest_users_limit_desc'] = 'Maximum number of richest users to display in the stats page.';
$l['setting_newpoints_stats_latest_donations'] = 'Last Donations';
$l['setting_newpoints_stats_latest_donations_desc'] = 'Number of last donations to show.';

$l['setting_group_newpoints_main'] = 'Main';
$l['setting_group_newpoints_main_desc'] = 'These settings come with NewPoints by default.';
$l['setting_newpoints_main_group_rate_primary_only'] = 'Group Rate For Primary Group Only (Deprecated)';
$l['setting_newpoints_main_group_rate_primary_only_desc'] = 'If you set this to yes, group rate rules will be calculated using only the primary user group. If you turn this off, all group rate rules wil be pondered and the closest value to <code>1</code> will always be used.';
$l['setting_newpoints_main_script_name'] = 'Script Name';
$l['setting_newpoints_main_script_name_desc'] = 'Script for the NewPoints plugin to use. Default: <code>newpoints.php</code>.';
$l['setting_newpoints_main_plugins_repositories'] = 'Plugins Repositories';
$l['setting_newpoints_main_plugins_repositories_desc'] = 'Insert your custom plugin repositories for updates. Leave as default if unsure. Default <code>community.mybb.com</code>';
$l['setting_newpoints_main_disable_backups'] = 'Disable Backups';
$l['setting_newpoints_main_disable_backups_desc'] = 'Disable automatic backups. Not recommended. Backing up requires a task to run.';
$l['setting_newpoints_main_disable_plugins'] = 'Disable Plugins';
$l['setting_newpoints_main_disable_plugins_desc'] = 'Disable NewPoints plugins from running globally.';

$l['setting_group_newpoints_logs'] = 'Logs';
$l['setting_group_newpoints_logs_desc'] = 'These settings are related to logs.';

$l['setting_newpoints_logs_manage_groups'] = 'Manage Groups';
$l['setting_newpoints_logs_manage_groups_desc'] = 'Select the groups that can manage the logs.';
$l['setting_newpoints_logs_per_page'] = 'Logs Per Page';
$l['setting_newpoints_logs_per_page_desc'] = 'Number of logs to show per page in the logs page.';
$l['setting_newpoints_logs_menu_order'] = 'Logs Menu Order';
$l['setting_newpoints_logs_menu_order_desc'] = 'Order for the logs page in the NewPoints menu item.';

$l['newpoints_confirmation_plugin_activation'] = 'Are you sure you wish to activate this plugin?';
$l['newpoints_confirmation_plugin_deactivation'] = 'Are you sure you wish to deactivate this plugin?';
$l['newpoints_confirmation_plugin_installation'] = 'Are you sure you wish to install this plugin?';
$l['newpoints_confirmation_plugin_uninstallation'] = 'Are you sure you wish to uninstall this plugin?';

$l['newpoints_groups_tab'] = 'NewPoints';

$l['newpoints_groups_users'] = 'Users Configuration';
$l['newpoints_groups_users_rate'] = 'Rate Configuration';
$l['newpoints_groups_users_income'] = 'Income Configuration';

$l['newpoints_user_groups_can_get_points'] = 'Can get income points?';
$l['newpoints_user_groups_can_see_page'] = 'Can see main page?';
$l['newpoints_user_groups_can_see_stats'] = 'Can see the stats page?';
$l['newpoints_user_groups_can_donate'] = 'Can donate points?';

$l['newpoints_user_groups_rate_addition'] = 'Group Rate for Additions <code style="color: darkorange;">Highest from all groups. Ratio from 1.</code><br /><small class="input">The income rate for this group, used when adding points to users (i.e: income earnings). Default is <code>1</code>.</small><br />';
$l['newpoints_user_groups_rate_subtraction'] = 'Group Rate for Subtraction <code style="color: darkorange;">Lowest from all groups. Percentage.</code><br /><small class="input">The income rate for this group, used when subtracting points from users (i.e: selling, purchasing, etc). Default is <code>100</code>.</small><br />';

$l['newpoints_user_groups_income_thread'] = 'New Thread<br /><small class="input">Amount of points received for each new thread.</small><br />';
$l['newpoints_user_groups_income_thread_reply'] = 'New Thread Reply<br /><small class="input">Amount of points received for each reply to a thread.</small><br />';
$l['newpoints_user_groups_income_thread_rate'] = 'New Thread Rate<br /><small class="input">Amount of points received for each new thread rate received.</small><br />';
$l['newpoints_user_groups_income_post'] = 'New Post<br /><small class="input">Amount of points received for each new post.</small><br />';
$l['newpoints_user_groups_income_post_minimum_characters'] = 'Minimum Characters<br /><small class="input">Minimum characters required in order to receive the amount of points per character for new threads or posts.</small><br />';
$l['newpoints_user_groups_income_post_character'] = 'Post Character<br /><small class="input">Amount of points received for each character in a thread or post.</small><br />';
$l['newpoints_user_groups_income_page_view'] = 'Page View<br /><small class="input">Amount of points received for each page view.</small><br />';
$l['newpoints_user_groups_income_visit'] = 'Visit<br /><small class="input">Amount of points received for each visit.</small><br />';
$l['newpoints_user_groups_income_visit_minutes'] = 'Visit Interval<br /><small class="input">Time in minutes that the user must wait to receive the points again.</small><br />';
$l['newpoints_user_groups_income_poll'] = 'New Poll<br /><small class="input">Amount of points received for each new poll.</small><br />';
$l['newpoints_user_groups_income_poll_vote'] = 'New Poll Vote<br /><small class="input">Amount of points received for each poll vote.</small><br />';
$l['newpoints_user_groups_income_user_allowance'] = 'User Allowance<br /><small class="input">Amount of points received.</small><br />';
$l['newpoints_user_groups_income_user_allowance_minutes'] = 'User Allowance Interval<br /><small class="input">Time in minutes that the user must wait to receive the points again.</small><br />';
$l['newpoints_user_groups_income_user_allowance_primary_only'] = 'Grant allowance if this is the user primary group only?';
$l['newpoints_user_groups_income_user_registration'] = 'New Registration<br /><small class="input">Amount of points received when users register to the forum.</small><br />';
$l['newpoints_user_groups_income_user_referral'] = 'New Referral<br /><small class="input">Amount of points received for each user referred to the forum.</small><br />';
$l['newpoints_user_groups_income_private_message'] = 'New Private Message<br /><small class="input">Amount of points received for each private message sent.</small><br />';

$l['newpoints_permission_group_general'] = 'General';
$l['newpoints_permission_group_rates'] = 'Rates';
$l['newpoints_permission_group_income'] = 'Income';

$l['newpoints_permission_group_can_get_points'] = 'Can get income points?';
$l['newpoints_permission_group_can_get_points_description'] = '';
$l['newpoints_permission_group_can_see_page'] = 'Can see main page?';
$l['newpoints_permission_group_can_see_page_description'] = '';
$l['newpoints_permission_group_can_see_stats'] = 'Can see the stats page?';
$l['newpoints_permission_group_can_see_stats_description'] = '';
$l['newpoints_permission_group_can_donate'] = 'Can donate points?';
$l['newpoints_permission_group_can_donate_description'] = '';

$l['newpoints_permission_group_rate_addition'] = 'Group Rate for Additions <code style="color: darkorange;">Highest from all groups. Ratio from 1.</code>';
$l['newpoints_permission_group_rate_addition_description'] = 'The income rate for this group, used when adding points to users (i.e: income earnings). Default is <code>1</code>.';
$l['newpoints_permission_group_rate_subtraction'] = 'Group Rate for Subtraction <code style="color: darkorange;">Lowest from all groups. Percentage.</code>';
$l['newpoints_permission_group_rate_subtraction_description'] = 'The income rate for this group, used when subtracting points from users (i.e: selling, purchasing, etc). Default is <code>100</code>.';

$l['newpoints_permission_group_income_thread'] = 'New Thread';
$l['newpoints_permission_group_income_thread_description'] = 'Amount of points received for each new thread.';
$l['newpoints_permission_group_income_thread_reply'] = 'New Thread Reply';
$l['newpoints_permission_group_income_thread_reply_description'] = 'Amount of points received for each reply to a thread.';
$l['newpoints_permission_group_income_thread_rate'] = 'New Thread Rate';
$l['newpoints_permission_group_income_thread_rate_description'] = 'Amount of points received for each new thread rate received.';
$l['newpoints_permission_group_income_post'] = 'New Post';
$l['newpoints_permission_group_income_post_description'] = 'Amount of points received for each new post.';
$l['newpoints_permission_group_income_post_minimum_characters'] = 'Minimum Characters';
$l['newpoints_permission_group_income_post_minimum_characters_description'] = 'Minimum characters required in order to receive the amount of points per character for new threads or posts.';
$l['newpoints_permission_group_income_post_character'] = 'Post Character';
$l['newpoints_permission_group_income_post_character_description'] = 'Amount of points received for each character in a thread or post.';
$l['newpoints_permission_group_income_page_view'] = 'Page View';
$l['newpoints_permission_group_income_page_view_description'] = 'Amount of points received for each page view.';
$l['newpoints_permission_group_income_visit'] = 'Visit';
$l['newpoints_permission_group_income_visit_description'] = 'Amount of points received for each visit.';
$l['newpoints_permission_group_income_visit_minutes'] = 'Visit Interval';
$l['newpoints_permission_group_income_visit_minutes_description'] = 'Time in minutes that the user must wait to receive the points again.';
$l['newpoints_permission_group_income_poll'] = 'New Poll';
$l['newpoints_permission_group_income_poll_description'] = 'Amount of points received for each new poll.';
$l['newpoints_permission_group_income_poll_vote'] = 'New Poll Vote';
$l['newpoints_permission_group_income_poll_vote_description'] = 'Amount of points received for each poll vote.';
$l['newpoints_permission_group_income_user_allowance'] = 'User Allowance';
$l['newpoints_permission_group_income_user_allowance_description'] = 'Amount of points received.';
$l['newpoints_permission_group_income_user_allowance_minutes'] = 'User Allowance Interval';
$l['newpoints_permission_group_income_user_allowance_minutes_description'] = 'Time in minutes that the user must wait to receive the points again.';
$l['newpoints_permission_group_income_user_allowance_primary_only'] = 'Grant allowance if this is the user primary group only?';
$l['newpoints_permission_group_income_user_registration'] = 'New Registration';
$l['newpoints_permission_group_income_user_registration_description'] = 'Amount of points received when users register to the forum.';
$l['newpoints_permission_group_income_user_referral'] = 'New Referral';
$l['newpoints_permission_group_income_user_referral_description'] = 'Amount of points received for each user referred to the forum.';
$l['newpoints_permission_group_income_private_message'] = 'New Private Message';
$l['newpoints_permission_group_income_private_message_description'] = 'Amount of points received for each private message sent.';

$l['newpoints_permissions_forum_can_get_points'] = 'Can get points posting in this forum?';
$l['newpoints_permissions_forum_rate_addition'] = 'Forum Rate <code style="color: darkorange;">Ratio from 1.</code>';
$l['newpoints_permissions_forum_rate_addition_description'] = 'The income rate for this forum. Default is <code>1</code>.';
$l['newpoints_permissions_forum_view_lock_points'] = 'Minimum Points To View <code style="color: darkorange;">Lowest from all groups.</code>';
$l['newpoints_permissions_forum_view_lock_points_description'] = 'Set an amount of points users must have in order to view this forum.';
$l['newpoints_permissions_forum_post_lock_points'] = 'Minimum Points To Post <code style="color: darkorange;">Lowest from all groups.</code>';
$l['newpoints_permissions_forum_post_lock_points_description'] = 'Set an amount of points users must have in order to post in this forum.';

$l['newpoints_forums'] = 'NewPoints';
$l['newpoints_forum_setting_can_get_points'] = 'Can get points posting in this forum?';
$l['newpoints_forum_setting_rate_addition'] = 'Forum Rate <code style="color: darkorange;">Ratio from 1.</code><br /><small class="input">The income rate for this forum. Default is <code>1</code>.</small><br />';
$l['newpoints_forum_setting_view_lock_points'] = 'Minimum Points To View <code style="color: darkorange;">Lowest from all groups.</code><br /><small class="input">Set an amount of points users must have in order to view this forum.</small><br />';
$l['newpoints_forum_setting_post_lock_points'] = 'Minimum Points To Post <code style="color: darkorange;">Lowest from all groups.</code><br /><small class="input">Set an amount of points users must have in order to post in this forum.</small><br />';

$l['newpoints_forums'] = 'NewPoints';
$l['newpoints_field_newpoints_can_get_points'] = 'Can get points posting in this forum?';
$l['newpoints_field_newpoints_rate_addition'] = 'Forum Rate <code style="color: darkorange;">Ratio from 1.</code>';
$l['newpoints_field_newpoints_rate_addition_description'] = 'The income rate for this forum. Default is <code>1</code>.';
$l['newpoints_field_newpoints_view_lock_points'] = 'Minimum Points To View <code style="color: darkorange;">Lowest from all groups.</code>';
$l['newpoints_field_newpoints_view_lock_points_description'] = 'Set an amount of points users must have in order to view this forum.';
$l['newpoints_field_newpoints_post_lock_points'] = 'Minimum Points To Post <code style="color: darkorange;">Lowest from all groups.</code>';
$l['newpoints_field_newpoints_post_lock_points_description'] = 'Set an amount of points users must have in order to post in this forum.';

$l['newpoints_users_amount'] = 'NewPoints Amount';

$l['newpoints_forums_rates'] = 'NewPoints Rates Configuration';

$l['newpoints_task_ran'] = 'Backup NewPoints task ran';
$l['newpoints_task_main_ran'] = 'Main NewPoints task ran';

$l['newpoints_users_tab'] = 'NewPoints';
$l['newpoints_users_title'] = 'NewPoints Information';
$l['newpoints_user_deprecated'] = 'This section is deprecated and the <a href="https://community.mybb.com/mods.php?action=view&pid=1623">Quick Edit</a> plugin is recommended instead.<br />You may still update some NewPoints data for this user here.';

$l['newpoints_user_newpoints'] = 'NewPoints<br /><small class="input">Update the curren NewPoints for this user.</small><br />';

$l['group_newpoints'] = 'NewPoints';

$l = array_merge($l, [
    'newpoints_admin_instances_success_new_instance' => 'The NewPoints instance was successfully added.',
    'newpoints_admin_instances_success_updated_instance' => 'The NewPoints instance settings were successfully updated.',
    'newpoints_admin_instances_success_instance_edit_permissions_groups' => 'The instance custom group permissions were successfully edited.',
    'newpoints_admin_instances_success_instance_edit_permissions_forums' => 'The instance custom forum permissions were successfully edited.',

    'newpoints_admin_instances_error_duplicated_users_column_name' => 'The selected users column name is already in use.',

    'newpoints_admin_instances_edit_tabs_main' => 'Main',
    'newpoints_admin_instances_edit_tabs_permissions' => 'Group Permissions',
    'newpoints_admin_instances_edit_tabs_forum_permissions' => 'Forum Permissions',

    'newpoints_admin_instances_edit' => 'Edit',
    'newpoints_admin_instances_edit_description' => 'Edit a new instance.',

    'newpoints_admin_instances_edit_currency_name_singular' => 'Name (Singular)',
    'newpoints_admin_instances_edit_currency_name_singular_description' => 'Enter the Name for this instance (singular).',
    'newpoints_admin_instances_edit_currency_name_plural' => 'Name (Plural)',
    'newpoints_admin_instances_edit_currency_name_plural_description' => 'Enter the Name for this instance (plural).',
    'newpoints_admin_instances_edit_currency_prefix' => 'Currency Prefix',
    'newpoints_admin_instances_edit_currency_prefix_description' => 'Currency prefix to append before formatted points.',
    'newpoints_admin_instances_edit_currency_suffix' => 'Currency Suffix',
    'newpoints_admin_instances_edit_currency_suffix_description' => 'Currency suffix to append after formatted points.',
    'newpoints_admin_instances_edit_decimal_digits' => 'Decimal Digits',
    'newpoints_admin_instances_edit_decimal_digits_description' => 'Number of decimal spaces to use for the currency.',
    'newpoints_admin_instances_edit_users_column_name' => 'Users Column Name',
    'newpoints_admin_instances_edit_users_column_name_description' => 'Enter the name of the column in the users table that will store the NewPoints for this instance.',
    'newpoints_admin_instances_edit_enable_notifications_private_message' => 'Enable Private Message Notifications?',
    'newpoints_admin_instances_edit_enable_notifications_private_message_description' => 'If you enable this, users will receive a private message when they gain or lose points in this instance.',
    'newpoints_admin_instances_edit_enable_notifications_alert' => 'Enable MyAlerts Notifications?',
    'newpoints_admin_instances_edit_enable_notifications_alert_description' => 'If you enable this, users will receive a MyAlerts notification when they gain or lose points in this instance.',
    'newpoints_admin_instances_edit_is_enabled' => 'Enabled?',
    'newpoints_admin_instances_edit_is_enabled_description' => 'Select whether you want this instance to be enabled or disabled.',

    'newpoints_admin_instances_edit_display_order' => 'Display Order',
    'newpoints_admin_instances_edit_display_order_description' => 'Enter the display order for this instance.',

    'newpoints_admin_instances_edit_button_submit' => 'Submit',
    'newpoints_admin_instances_edit_button_reset' => 'Reset',

    'newpoints_admin_instances_permissions_form_group' => 'Group',
    'newpoints_admin_instances_permissions_form_group_permissions' => 'Group Permissions',
    'newpoints_admin_instances_permissions_form_allowed_actions' => 'Overview: Allowed Actions',
    'newpoints_admin_instances_permissions_form_disallowed_actions' => 'Overview: Disallowed Actions',
    'newpoints_admin_instances_permissions_form_inherited' => 'inherited',
    'newpoints_admin_instances_permissions_form_custom' => 'custom',
    'newpoints_admin_instances_permissions_form_edit' => 'Edit Custom Permissions',
    'newpoints_admin_instances_permissions_form_clear' => 'Clear Custom Permissions',
    'newpoints_admin_instances_permissions_form_set' => 'Set Custom Permissions',
    'newpoints_admin_instances_permissions_form_save_groups' => 'Save Group Permissions',

    'newpoints_admin_instances_permissions_form_custom_permissions' => 'Custom Permissions',
    'newpoints_admin_instances_permissions_form_custom_permissions_description' => 'Here you can modify the full custom permissions for an individual group for a single instance.',

    'newpoints_admin_instances_permissions_form_custom_permissions_success' => 'The instance custom group permissions have been saved successfully.',

    'newpoints_admin_instances_permissions_form_confirm_clear' => 'Are you sure you wish to clear this custom permission?',

    'newpoints_admin_instances_permissions_form_button_submit_groups' => 'Save Group Permissions',

    'newpoints_admin_instances_permissions_clear_confirm' => 'Are you sure you wish to clear this custom permission?',
    'newpoints_admin_instances_permissions_clear_success' => 'The custom group permissions for this instance have been cleared successfully.',

    'newpoints_admin_instances_permissions_form_forum' => 'Forum',
    'newpoints_admin_instances_permissions_form_forum_permissions' => 'Forum Permissions',
    'newpoints_admin_instances_permissions_form_save_forums' => 'Save Forum Permissions',

    'newpoints_admin_instances_permissions_form_button_submit_forums' => 'Save Forum Permissions',

    'newpoints_forums_general' => 'General',
    'newpoints_forums_rates' => 'Rates',
    'newpoints_forums_income' => 'Income',
]);