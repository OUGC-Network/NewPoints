<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/languages/english/newpoints.lang.php)
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

$l['newpoints_header_menu'] = 'NewPoints';

$l['newpoints_home'] = 'Home';
$l['newpoints_menu'] = 'Menu';
$l['newpoints_donate'] = 'Donate';
$l['newpoints_donated'] = 'You have successfully donated {1} to the selected user.';
$l['newpoints_user'] = 'User';
$l['newpoints_user_desc'] = 'Enter the user name of the user you want to send a donation.';
$l['newpoints_amount'] = 'Amount';
$l['newpoints_amount_desc'] = 'Enter the amount of points you want to send to the user.';
$l['newpoints_reason'] = 'Reason';
$l['newpoints_reason_desc'] = '(Optional) Enter a reason for the donation.';
$l['newpoints_submit'] = 'Submit';
$l['newpoints_donate_subject'] = 'New donation';
$l['newpoints_donate_message'] = 'Hello, I\'ve just sent you a donation of {1}.';
$l['newpoints_donate_message_reason'] = 'Hello, I\'ve just sent you a donation of {1}. Reason:[quote]{2}[/quote]';
$l['newpoints_donations_disabled'] = 'Donations have been disabled by the administrator.';
$l['newpoints_cant_donate_self'] = 'You can\'t send a donation to yourself.';
$l['newpoints_invalid_amount'] = 'You have entered an invalid amount of points.';
$l['newpoints_invalid_user'] = 'You have entered an invalid user name.';
$l['newpoints_donate_log'] = '{1}-{2}-{3}';
$l['newpoints_stats_disabled'] = 'Statistics have been disabled by the administrator.';
$l['newpoints_statistics'] = 'Statistics';
$l['newpoints_richest_users'] = 'Richest Users';
$l['newpoints_last_donations'] = 'Last Donations';
$l['newpoints_from'] = 'From';
$l['newpoints_to'] = 'To';
$l['newpoints_noresults'] = 'No results found.';
$l['newpoints_date'] = 'Date';
$l['newpoints_not_enough_points'] = 'You don\'t have enough points. Required: {1}';
$l['newpoints_amount_paid'] = 'Amount Paid';
$l['newpoints_source'] = 'Source';

$l['newpoints_home_desc'] = 'NewPoints is a complex points system for MyBB software.';
$l['newpoints_home_description_primary'] = 'There are some options on the menu on the left that you can use.';
$l['newpoints_home_description_header'] = 'How do you earn points?';
$l['newpoints_home_description_secondary'] = '';
$l['newpoints_home_description_footer'] = 'Contact your administrator if you have any questions.<br />This software was written by <strong>Pirata Nervo</strong> for <a href="https://mybb.com">MyBB</a>.';
$l['newpoints_home_user_rate_description'] = 'Your rate for earning points is <code>{1}</code> and your rate for spending points is <code>{2}</code>.';

$l['newpoints_action'] = 'Action';
$l['newpoints_chars'] = 'Chars';
$l['newpoints_max_donations_control'] = 'You have reached the maximum of {1} over the last 15 minutes. Please wait before making a new one.';

// Settings translation
$l['newpoints_income_source'] = 'Source';
$l['newpoints_income_amount'] = '{1} Received';
$l['newpoints_income_thread'] = 'New Thread';
$l['newpoints_income_thread_desc'] = 'Amount of points received for each new thread.';
$l['newpoints_income_thread_reply'] = 'New Thread Reply';
$l['newpoints_income_thread_reply_desc'] = 'Amount of points received for each reply to a thread.';
$l['newpoints_income_thread_rate'] = 'New Thread Rate';
$l['newpoints_income_thread_rate_desc'] = 'Amount of points received for each new thread rate received.';
$l['newpoints_income_post'] = 'New Post';
$l['newpoints_income_post_desc'] = 'Amount of points received for each new post with at least {1} characters.';
$l['newpoints_income_post_character'] = 'Post Character';
$l['newpoints_income_post_character_desc'] = 'Amount of points received for each character in a thread or post.';
$l['newpoints_income_page_view'] = 'Page View';
$l['newpoints_income_page_view_desc'] = 'Amount of points received for each page view.';
$l['newpoints_income_visit'] = 'Visit';
$l['newpoints_income_visit_desc'] = 'Amount of points received for each visit every {1} minutes.';
$l['newpoints_income_poll'] = 'New Poll';
$l['newpoints_income_poll_desc'] = 'Amount of points received for each new poll.';
$l['newpoints_income_poll_vote'] = 'New Poll Vote';
$l['newpoints_income_poll_vote_desc'] = 'Amount of points received for each poll vote.';
$l['newpoints_income_user_allowance'] = 'User Allowance';
$l['newpoints_income_user_allowance_desc'] = 'Amount of points received every {1} minutes.';
$l['newpoints_income_user_registration'] = 'New Registration';
$l['newpoints_income_user_registration_desc'] = 'Amount of points received when users register to the forum.';
$l['newpoints_income_user_referral'] = 'New Referral';
$l['newpoints_income_user_referral_desc'] = 'Amount of points received for each user referred to the forum.';
$l['newpoints_income_private_message'] = 'New Private Message';
$l['newpoints_income_private_message_desc'] = 'Amount of points received for each private message sent.';

$l['newpoints_search_user'] = 'Search for an user..';

$l['newpoints_task_ran'] = 'Backup NewPoints task ran';
$l['newpoints_task_main_ran'] = 'Main NewPoints task ran';

$l['newpoints_page_confirm_table_cancel_title'] = 'Confirm Cancel';
$l['newpoints_page_confirm_table_cancel_button'] = 'Cancel Order';

$l['newpoints_page_confirm_table_purchase_title'] = 'Confirm Purchase';
$l['newpoints_page_confirm_table_purchase_button'] = 'Purchase';

$l['newpoints_buttons_delete'] = 'Delete';
$l['newpoints_buttons_manage'] = 'Manage';

$l['newpoints_manage_page_breadcrumb'] = 'Manage';

// Logs
$l['newpoints_logs_page_title'] = 'Logs';
$l['newpoints_logs_page_breadcrumb'] = 'Logs';
$l['newpoints_logs_page_table_title'] = 'Logs';
$l['newpoints_logs_page_table_id'] = 'ID';
$l['newpoints_logs_page_table_action'] = 'Action';
$l['newpoints_logs_page_table_points'] = 'Points';
$l['newpoints_logs_page_table_action_user'] = 'User';
$l['newpoints_logs_page_table_action_primary'] = 'Primary';
$l['newpoints_logs_page_table_action_secondary'] = 'Secondary';
$l['newpoints_logs_page_table_action_tertiary'] = 'Tertiary';
$l['newpoints_logs_page_table_action_date'] = 'Date';
$l['newpoints_logs_page_table_action_options'] = 'Options';
$l['newpoints_logs_page_table_action_options_delete'] = 'Delete';
$l['newpoints_logs_page_table_empty'] = 'There are no logs to display.';

$l['newpoints_logs_page_filter_table_title'] = 'Filter';
$l['newpoints_logs_page_filter_table_actions'] = 'Actions';
$l['newpoints_logs_page_filter_table_user'] = 'User';

$l['newpoints_logs_page_errors_invalid_user_name'] = 'You have entered an invalid user name.';
$l['newpoints_logs_page_errors_no_logs_selected'] = 'You have selected an invalid log.';
$l['newpoints_logs_page_success_log_deleted'] = 'The selected log was successfully deleted.<br /><br />You will now be redirected back to the previous page.';