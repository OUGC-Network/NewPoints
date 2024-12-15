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

$l['newpoints'] = 'NewPoints';
$l['newpoints_description'] = 'NewPoints plugin for MyBB - A complex but efficient points system for MyBB.';
$l['newpoints_submit_button'] = 'Submit';
$l['newpoints_reset_button'] = 'Reset';
$l['newpoints_error'] = 'An unknown error has occurred.';
$l['newpoints_continue_button'] = 'Continue';
$l['newpoints_click_continue'] = 'Click Continue to proceed.';
$l['newpoints_delete'] = 'Delete';
$l['newpoints_missing_fields'] = 'There is one or more missing fields.';
$l['newpoints_edit'] = 'Edit';

///////////////// Plugins
$l['newpoints_plugins'] = 'Plugins';
$l['newpoints_plugins_description'] = 'Here you can manage NewPoints plugins.';
$l['newpoints_plugin_incompatible'] = 'This plugin is incompatible with NewPoints {1}';

///////////////// Settings
$l['newpoints_settings'] = 'Settings';
$l['newpoints_settings_description'] = 'Here you can manage NewPoints settings.';
$l['newpoints_settings_change'] = 'Change';
$l['newpoints_settings_change_description'] = 'Change settings.';
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
$l['newpoints_log_pruneconfirm'] = ' Are you sure you want to prune log entries?';
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
$l['newpoints_recount'] = 'Recount User Newpoints';
$l['newpoints_recount_desc'] = 'When this is run, the Newpoints amount for each user will be updated to reflect its current live value based on the income settings.';
$l['newpoints_recount_success'] = ' The Newpoints amount for users have been rebuilt successfully.';
$l['newpoints_reset'] = 'Reset User Newpoints';
$l['newpoints_reset_desc'] = 'When this is run, the Newpoints amount for each user will be updated to reflect this value.';
$l['newpoints_invalid_user'] = 'Invalid user.';

///////////////// Stats
$l['newpoints_stats'] = 'Statistics';
$l['newpoints_stats_description'] = 'View your forum statistics.';
$l['newpoints_stats_lastdonations'] = 'Last Donations';
$l['newpoints_error_gathering'] = 'Could not gather any data.';
$l['newpoints_stats_richest_users'] = 'Richest Users';
$l['newpoints_stats_from'] = 'From';
$l['newpoints_stats_to'] = 'To';
$l['newpoints_stats_date'] = 'Date';
$l['newpoints_stats_user'] = 'User';
$l['newpoints_stats_points'] = 'Points';
$l['newpoints_stats_amount'] = 'Amount';

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
$l['setting_newpoints_donations_flood_minutes'] = 'Flood Control: Minutes';
$l['setting_newpoints_donations_flood_minutes_desc'] = 'Number of minutes to wait between maximum donations.';
$l['setting_newpoints_donations_flood_limit'] = 'Flood Control: Maximum Donations';
$l['setting_newpoints_donations_flood_limit_desc'] = 'Maximum donations a user can send per flood control threshold.';
$l['setting_newpoints_donations_send_private_message'] = 'Send a PM on donate?';
$l['setting_newpoints_donations_send_private_message_desc'] = 'Do you want it to automatically send a new private message to a user receiving a donation?';
$l['setting_newpoints_donations_stats_latest'] = 'Last Donations';
$l['setting_newpoints_donations_stats_latest_desc'] = 'Number of last donations to show.';
$l['setting_newpoints_donations_menu_order'] = 'Menu Order';
$l['setting_newpoints_donations_menu_order_desc'] = 'Order in the Newpoints menu item.';

$l['setting_group_newpoints_stats'] = 'Stats';
$l['setting_group_newpoints_stats_desc'] = 'These settings are related to the stats page.';
$l['setting_newpoints_stats_menu_order'] = 'Menu Order';
$l['setting_newpoints_stats_menu_order_desc'] = 'Order in the Newpoints menu item.';

$l['setting_group_newpoints_main'] = 'Main';
$l['setting_group_newpoints_main_desc'] = 'These settings come with NewPoints by default.';
$l['setting_newpoints_main_curname'] = 'Currency Name';
$l['setting_newpoints_main_curname_desc'] = 'Currency name to use in the forums.';
$l['setting_newpoints_main_curprefix'] = 'Currency Prefix';
$l['setting_newpoints_main_curprefix_desc'] = 'Currency prefix to render before the format of points.';
$l['setting_newpoints_main_cursuffix'] = 'Currency Suffix';
$l['setting_newpoints_main_cursuffix_desc'] = 'Currency suffix to render before the format of points.';
$l['setting_newpoints_main_decimal'] = 'Decimal Places';
$l['setting_newpoints_main_decimal_desc'] = 'Number of decimal spaces to use for the currency.';
$l['setting_newpoints_main_stats_richestusers'] = 'Stats: Richest Users';
$l['setting_newpoints_main_stats_richestusers_desc'] = 'Maximum number of richest users to display in the stats page.';
$l['setting_newpoints_main_group_rate_primary_only'] = 'Group Rate For Primary Group Only';
$l['setting_newpoints_main_group_rate_primary_only_desc'] = 'If you set this to yes, group rate rules will be calculated using only the primary user group. If you turn this off, all group rate rules wil be pondered and the closest value to <code>1</code> will always be used.';
$l['setting_newpoints_main_file'] = 'Main File Name';
$l['setting_newpoints_main_file_desc'] = 'If you rename the main Newpoints file, update this setting. Default: <code>newpoints.php</code>';

$l['setting_group_newpoints_income'] = 'Income';
$l['setting_group_newpoints_income_desc'] = 'These settings come with NewPoints by default and are related to income generated by posting, voting in polls, etc...';
$l['setting_newpoints_income_newpost'] = 'New Post';
$l['setting_newpoints_income_newpost_desc'] = 'Amount of points received on new post.';
$l['setting_newpoints_income_newthread'] = 'New Thread';
$l['setting_newpoints_income_newthread_desc'] = 'Amount of points received on new thread.';
$l['setting_newpoints_income_newpoll'] = 'New Poll';
$l['setting_newpoints_income_newpoll_desc'] = 'Amount of points received on new poll.';
$l['setting_newpoints_income_perchar'] = 'Per Character';
$l['setting_newpoints_income_perchar_desc'] = 'Amount of points received per character (in new thread and new post).';
$l['setting_newpoints_income_minchar'] = 'Minimum Characters';
$l['setting_newpoints_income_minchar_desc'] = 'Minimum characters required in order to receive the amount of points per character.';
$l['setting_newpoints_income_newreg'] = 'New Registration';
$l['setting_newpoints_income_newreg_desc'] = 'Amount of points received by the user when registering.';
$l['setting_newpoints_income_pervote'] = 'Per Poll Vote';
$l['setting_newpoints_income_pervote_desc'] = 'Amount of points received by the user who votes.';
$l['setting_newpoints_income_perreply'] = 'New Post';
$l['setting_newpoints_income_perreply_desc'] = 'Amount of points received by the author of the thread, when someone replies to it.';
$l['setting_newpoints_income_pmsent'] = 'Per PM Sent';
$l['setting_newpoints_income_pmsent_desc'] = 'Amount of points received everytime a user sends a private message.';
$l['setting_newpoints_income_perrate'] = 'Per Rate';
$l['setting_newpoints_income_perrate_desc'] = 'Amount of points received everytime a user rates a thread.';
$l['setting_newpoints_income_pageview'] = 'Per Page View';
$l['setting_newpoints_income_pageview_desc'] = 'Amount of points received everytime a user views a page.';
$l['setting_newpoints_income_visit'] = 'Per Visit';
$l['setting_newpoints_income_visit_desc'] = 'Amount of points received everytime a user visits the forum. ("visits"=new MyBB session (expires after 15 minutes))';
$l['setting_newpoints_income_visit_minutes'] = 'Visit Time';
$l['setting_newpoints_income_visit_minutes_desc'] = 'Time in minutes that the user must wait to receive the points again.';
$l['setting_newpoints_income_referral'] = 'Per Referral';
$l['setting_newpoints_income_referral_desc'] = 'Amount of points received everytime a user is referred. (the referred user is who receives the points)';

$l['newpoints_confirmation_plugin_activation'] = 'Are you sure you wish to activate this plugin?';
$l['newpoints_confirmation_plugin_deactivation'] = 'Are you sure you wish to deactivate this plugin?';
$l['newpoints_confirmation_plugin_installation'] = 'Are you sure you wish to install this plugin?';
$l['newpoints_confirmation_plugin_uninstallation'] = 'Are you sure you wish to uninstall this plugin?';

$l['newpoints_groups_tab'] = 'Newpoints';
$l['newpoints_groups_users'] = 'Users Configuration';
$l['newpoints_user_groups_can_get_points'] = 'Can get points?';
$l['newpoints_user_groups_can_see_page'] = 'Can see main page?';
$l['newpoints_user_groups_can_see_stats'] = 'Can see the stats page?';
$l['newpoints_user_groups_can_donate'] = 'Can donate points?';
$l['newpoints_user_groups_rate'] = 'Group Rate<br /><small class="input">The income rate for this group. Default is <code>1</code>.</small><br />';
$l['newpoints_user_groups_allowance'] = 'Allowance Points<br /><small class="input">Set an amount of points to paid to users from this group.</small><br />';
$l['newpoints_user_groups_allowance_period'] = 'Allowance Interval<br /><small class="input">Number of seconds between each allowance payment.</small><br />';
$l['newpoints_user_groups_allowance_primary_only'] = 'Grant allowance if this is the user primary group only?';

$l['newpoints_forums'] = 'Newpoints';
$l['newpoints_forums_rate'] = 'Forum Rate<br /><small class="input">The income rate for this forum. Default is <code>1</code>.</small><br />';
$l['newpoints_forums_view_lock_points'] = 'Minimum Points To View<br /><small class="input">Set an amount of points users must have in order to view this forum.</small><br />';
$l['newpoints_forums_post_lock_points'] = 'Minimum Points To Post<br /><small class="input">Set an amount of points users must have in order to post in this forum.</small><br />';

$l['newpoints_task_ran'] = 'Backup NewPoints task ran';
$l['newpoints_task_main_ran'] = 'Main NewPoints task ran';

$l['newpoints_users_tab'] = 'Newpoints';
$l['newpoints_users_title'] = 'Newpoints Information';
$l['newpoints_user_newpoints'] = 'Newpoints<br /><small class="input">Update the curren Newpoints for this user.</small><br />';

$l['group_newpoints'] = 'Newpoints';
$l['newpoints_field_newpoints_can_get_points'] = 'Can get points posting in this forum?';