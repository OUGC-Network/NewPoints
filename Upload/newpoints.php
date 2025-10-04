<?php

/***************************************************************************
 *
 *    NewPoints plugin (/newpoints.php)
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

use NewPoints\Core\Permissions;

use NewPoints\System\Url;

use function NewPoints\Core\alert_send;
use function NewPoints\Core\build_income_table;
use function NewPoints\Core\build_instances_select;
use function NewPoints\Core\get_setting;
use function NewPoints\Core\instance_get_enabled;
use function NewPoints\Core\instance_object;
use function NewPoints\Core\language_load;
use function NewPoints\Core\log_delete;
use function NewPoints\Core\log_error;
use function NewPoints\Core\log_get;
use function NewPoints\Core\main_file_name;
use function NewPoints\Core\page_build_menu;
use function NewPoints\Core\page_build_menu_options;
use function NewPoints\Core\post_parser;
use function NewPoints\Core\private_message_send;
use function NewPoints\Core\run_hooks;
use function NewPoints\Core\templates_get;
use function NewPoints\Core\users_get_by_username;

use const NewPoints\DECIMAL_DATA_TYPE_STEP;
use const NewPoints\Core\INCOME_TYPE_POST;
use const NewPoints\Core\INCOME_TYPE_POST_CHARACTER;
use const NewPoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const NewPoints\Core\INCOME_TYPE_THREAD;
use const NewPoints\Core\INCOME_TYPE_THREAD_REPLY;
use const NewPoints\Core\INCOME_TYPE_USER_REFERRAL;
use const NewPoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const NewPoints\Core\LOGGING_TYPE_INCOME;
use const NewPoints\Core\LOGGING_TYPE_CHARGE;

const IN_MYBB = 1;

const THIS_SCRIPT = 'newpoints.php';

define('THIS_SCRIPT_REAL', substr($_SERVER['SCRIPT_NAME'], -mb_strpos(strrev($_SERVER['SCRIPT_NAME']), '/')));

const NP_DISABLE_GUESTS = false;

$templatelist = 'newpoints_option, newpoints_menu, newpoints_home_income_row, newpoints_home_income_table, newpoints_home, newpoints_statistics_richest, newpoints_statistics_richest_user, newpoints_no_results, newpoints_statistics, newpoints_donate_form, newpoints_donate, newpoints_option_selected, newpoints_logs_table_row, newpoints_logs_table, newpoints_button_manage, newpoints_input_select_option, newpoints_input_select, newpoints_logs_filter_table, newpoints_page';

require_once './global.php';

if (!function_exists('\NewPoints\Core\language_load')) {
    error_no_permission();
}

$url = new Url();

$can_see_page = false;

if (!($instance_objects = instance_get_enabled())) {
    error_no_permission();
}

foreach ($instance_objects as $instance_id => $instance) {
    if ($instance->user_permissions[Permissions::CanSeePage]) {
        $can_see_page = true;
    }
}

unset($instance_id, $instance);

global $mybb, $plugins, $lang, $db, $templates;

$mybb->input['action'] = $mybb->get_input('action');

$newpoints_file = main_file_name();

run_hooks('begin');

// Allow guests here? Some plugins may allow guest access, and they may hook to newpoints_start
if (!$can_see_page) {
    error_no_permission();
}

language_load();

$options = page_build_menu_options();

$newpoints_menu = page_build_menu();

$newpoints_errors = '';

add_breadcrumb($lang->newpoints, $newpoints_file);

$newpoints_additional = '';

$current_user_id = (int)$mybb->user['uid'];

$newpoints_pagination = $newpoints_buttons = '';

run_hooks('start');

// Block guests here
if (!$current_user_id) {
    error_no_permission();
}

$instance_where_clauses = ["instance_id IN ('" . implode("','", array_keys($instance_objects)) . "')"];

$filter = $mybb->get_input('filter', MyBB::INPUT_ARRAY);

$filter['instances'] = array_filter(array_map('intval', $filter['instances'] ?? []));

if ($mybb->get_input('action') == 'stats') {
    add_breadcrumb($lang->newpoints_statistics, $url->build(['action' => 'stats']));

    $can_see_stats = false;

    foreach ($instance_objects as $instance_id => $instance) {
        if ($instance->user_permissions[Permissions::CanSeeStats]) {
            $can_see_stats = true;

            break;
        }
    }

    $fields = ['uid', 'username', 'usergroup', 'displaygroup'];

    $statistics_items = $statistics_items_left = [];

    $statistics_items_right = &$statistics_items;

    if (!$can_see_stats) {
        error($lang->newpoints_stats_disabled);
    }

    run_hooks('stats_start');

    foreach ($instance_objects as $instance_id => $instance) {
        if (!$instance->user_permissions[Permissions::CanSeeStats]) {
            continue;
        }

        $fields['users_column_name'] = $instance->users_column_get();

        $richest_users = '';

        // get richest users
        $query = $db->simple_select(
            'users',
            implode(',', $fields),
            '',
            [
                'order_by' => $instance->users_column_get(),
                'order_dir' => 'DESC',
                'limit' => (int)get_setting('stats_richest_users_limit')
            ]
        );

        $bgcolor = alt_trow(true);

        while ($user = $db->fetch_array($query)) {
            $username = build_profile_link(
                format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']),
                (int)$user['uid']
            );

            $newpoints_amount = $instance->points_format(
                (float)$user[$instance->users_column_get()]
            );

            run_hooks('stats_richest_users');

            $richest_users .= eval(templates_get('statistics_richest_user'));

            $bgcolor = alt_trow();
        }

        if (!$richest_users) {
            $colspan = 2;

            $no_results = $lang->newpoints_noresults;

            $richest_users = eval(templates_get('no_results'));
        }

        $table_title = $lang->sprintf(
            $lang->newpoints_richest_users,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower()
        );

        $statistics_items_left[] = eval(templates_get('statistics_richest'));
    }

    run_hooks('stats_middle');

    $where_clauses = $instance_where_clauses;

    $where_clauses[] = "instance_id IN ('" . implode("',',", array_map(function ($instance): int {
            return $instance->user_permissions[Permissions::CanSeeStats] ? $instance->instance_id : 0;
        }, $instance_objects)) . "')";

    $last_donations = '';

    // get latest donations
    $query = $db->simple_select(
        "newpoints_log l LEFT JOIN {$db->table_prefix}users u ON (u.uid=l.uid) LEFT JOIN {$db->table_prefix}users tu ON (tu.uid=l.log_primary_id)",
        'l.date, l.uid, l.username, l.points, l.instance_id, u.usergroup, u.displaygroup, l.log_primary_id, l.instance_id, tu.username AS from_username, tu.usergroup AS from_usergroup, tu.displaygroup AS from_displaygroup',
        implode(' AND ', array_merge($where_clauses, ["l.action='donation'"])),
        [
            'order_by' => 'l.date',
            'order_dir' => 'DESC',
            'limit' => (int)get_setting('stats_latest_donations')
        ]
    );

    $bgcolor = alt_trow(true);

    while ($donation = $db->fetch_array($query)) {
        $instance_id = (int)$donation['instance_id'];

        $instance = $instance_objects[$instance_id];

        $instance_name_upper = $instance->get_display_name_upper();

        $from_username = build_profile_link(
            format_name(
                htmlspecialchars_uni($donation['from_username'] ?? ''),
                $donation['from_usergroup'] ?? 0,
                $donation['from_displaygroup'] ?? 0,
            ),
            $donation['log_primary_id'] ?? 0
        );

        $to_username = build_profile_link(
            format_name(
                htmlspecialchars_uni($donation['username']),
                $donation['usergroup'],
                $donation['displaygroup']
            ),
            (int)$donation['uid']
        );

        $amount = $instance->points_format((float)($donation['points'] ?? 0));

        $date = my_date('normal', (int)$donation['date']);

        run_hooks('stats_last_donations');

        $last_donations .= eval(templates_get('statistics_donation'));

        $bgcolor = alt_trow();
    }

    if (!$last_donations) {
        $colspan = 4;

        $no_results = $lang->newpoints_noresults;

        $last_donations = eval(templates_get('no_results'));
    }

    $statistics_items_right[] = eval(templates_get('statistics_donation_row'));

    run_hooks('stats_end');

    $statistics_items_right = implode('', $statistics_items_right);

    $statistics_items_left = implode('', $statistics_items_left);

    $newpoints_content = eval(templates_get('statistics'));

    $page_title = $lang->newpoints_statistics;

    $page = eval(templates_get('page'));

    output_page($page);

    exit;
} elseif ($mybb->get_input('action') == 'donate') {
    $can_donate = false;

    foreach ($instance_objects as $instance_id => $instance) {
        if ($instance->user_permissions[Permissions::CanDonate]) {
            $can_donate = true;

            break;
        }
    }

    if (!$can_donate) {
        error_no_permission();
    }

    $errors = [];

    $to_user_id = $mybb->get_input('uid', MyBB::INPUT_INT);

    $to_user_data = [];

    $username_row = '';

    if ($to_user_id) {
        $to_user_data = get_user($to_user_id);

        if (!$to_user_data) {
            error($lang->newpoints_invalid_user);
        }

        if ($to_user_id === $current_user_id) {
            error($lang->newpoints_cant_donate_self);
        }
    } else {
        $username = htmlspecialchars_uni($mybb->get_input('username'));

        $username_row = eval(templates_get('donate_form_user'));
    }

    $instance_id = (int)$mybb->get_input('instance_id', MyBB::INPUT_INT);

    $table_title = $lang->newpoints_donate;

    if ($instance_id && $mybb->request_method !== 'post') {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->user_permissions[Permissions::CanDonate]) {
                error_no_permission();

                exit;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            error($lang->newpoints_invalid_instance);

            exit;
        }

        $table_title = $lang->sprintf(
            $lang->newpoints_donate_instance,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower()
        );
    }

    run_hooks('donate_start');

    if ($mybb->request_method === 'post') {
        verify_post_check($mybb->get_input('my_post_key'));

        $to_user_data = users_get_by_username($mybb->get_input('username'), 'uid,username');

        $to_user_id = (int)($to_user_data['uid'] ?? 0);

        run_hooks('do_donate_start');

        try {
            $instance = instance_object($instance_id);

            if (!$instance->user_permissions[Permissions::CanDonate]) {
                $errors[] = $lang->newpoints_invalid_instance;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            $errors[] = $lang->newpoints_invalid_instance;
        }

        if (!$to_user_data) {
            $errors[] = $lang->newpoints_invalid_user;
        }

        if ($to_user_id === $current_user_id) {
            $errors[] = $lang->newpoints_cant_donate_self;
        }

        $amount = 0;

        // todo, add permission for flood bypass
        if (!empty($instance->instance_id)) {
            $time_cut = TIME_NOW - ($instance->settings_get_value('donations_flood_minutes') * 60 * 60);

            $where_clauses = $instance_where_clauses;

            $where_clauses[] = "action='donation' AND date>'{$time_cut}' AND uid='{$current_user_id}' AND instance_id='{$instance->instance_id}'";

            $q = $db->simple_select(
                'newpoints_log',
                'COUNT(lid) as donations',
                implode(' AND ', $where_clauses)
            );

            $totaldonations = (int)$db->fetch_field($q, 'donations');

            if ($totaldonations >= $instance->settings_get_value('donations_flood_limit')) {
                $errors[] = $lang->sprintf(
                    $lang->newpoints_max_donations_control,
                    $instance->get_display_name_upper($totaldonations),
                    $instance->get_display_name_lower($totaldonations),
                    $totaldonations
                );
            }

            $amount = round(
                $mybb->get_input('amount', MyBB::INPUT_FLOAT),
                (int)$instance->get_instance_data()['decimal_digits']
            );
        }

        // do we have enough points?
        if ($amount <= 0 || $amount > $instance->get_user_column_value()) {
            $errors[] = $lang->newpoints_invalid_amount;
        }

        if (empty($errors)) {
            try {
                $instance->points_subtraction($amount)
                    ->logger->log_charge(
                        'donation_sent',
                        $amount,
                        $to_user_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $instance->instance_id,
                    $e->getMessage(),
                    user_id: $current_user_id,
                    post_id: $instance->get_post_id(),
                    thread_id: $instance->get_thread_id(),
                    forum_id: $instance->get_forum_id(),
                );
            }

            try {
                $user_instance = instance_object($instance->instance_id, $to_user_id)
                    ->points_addition($amount)
                    ->logger->log_income(
                        'donation',
                        $amount,
                        $current_user_id,
                    );

                if ($mybb->get_input('reason')) {
                    $message = $lang->sprintf(
                        $lang->newpoints_donate_message_reason,
                        $instance->points_format($amount),
                        htmlspecialchars_uni($mybb->get_input('reason'))
                    );
                } else {
                    $message = $lang->sprintf(
                        $lang->newpoints_donate_message,
                        $instance->points_format($amount)
                    );
                }

                private_message_send(
                    [
                        'subject' => $lang->newpoints_donate_subject,
                        'message' => $message,
                        'receivepms' => 1,
                        'touid' => $to_user_id
                    ],
                    $current_user_id,
                    admin_override: true,
                    instance_id: $instance->instance_id
                );

                alert_send(
                    $to_user_id,
                    $user_instance->get_log_id(),
                    'core',
                    'donation_received',
                    $this->instance->instance_id,
                );
            } catch (Exception $e) {
                log_error(
                    $instance->instance_id,
                    $e->getMessage(),
                    user_id: $to_user_id,
                );
            }

            run_hooks('do_donate_end');

            $link = $mybb->settings['bburl'] . '/' . $url->build(['action' => 'donate']);

            if ($post = get_post($mybb->get_input('pid', MyBB::INPUT_INT))) {
                $link = get_post_link($post['pid'], $post['tid']) . '#pid' . $post['pid'];
            }

            redirect(
                $link,
                $lang->sprintf(
                    $lang->newpoints_donated,
                    $instance->get_display_name_upper($amount),
                    $instance->get_display_name_lower($amount),
                    $instance->points_format($amount)
                )
            );
        }
    }

    $errors = $errors ? inline_error($errors) : '';

    // make sure wen're trying to send a donation to ourselves

    $amount = $mybb->get_input('amount', MyBB::INPUT_FLOAT) ?? '';

    $pid = $mybb->get_input('pid', MyBB::INPUT_INT);

    $input_hidden = $instances_row = '';

    $input_step = DECIMAL_DATA_TYPE_STEP;

    if ($instance_id && $mybb->request_method !== 'post') {
        $input_hidden = eval(templates_get('donate_form_input_instance'));
    } else {
        $instances_select = build_instances_select(filter: $filter);

        $instances_row = eval(templates_get('donate_form_select_instance'));
    }

    $form = eval(templates_get('donate_form'));

    if ($mybb->get_input('modal', 1)) {
        $code = $form;

        $modal = eval(templates_get('modal', false));

        echo $modal;

        exit;
    }

    $page = eval(templates_get('donate'));

    run_hooks('donate_end');

    output_page($page);

    exit;
} elseif ($mybb->get_input('action') == 'logs') {
    $url_params = ['action' => 'logs'];

    $is_manage_page = false;

    $mybb->input['manage'] = $mybb->get_input('manage', MyBB::INPUT_INT);

    $is_moderator = [];

    foreach ($instance_objects as $instance_id => $instance) {
        if (is_member($instance->settings_get_value('logs_manage_groups'))) {
            $is_moderator[$instance_id] = $instance_id;
        }
    }

    if ($mybb->input['manage'] && $is_moderator) {
        $url_params['manage'] = 1;

        $is_manage_page = true;
    }

    add_breadcrumb(
        $lang->newpoints_logs_page_breadcrumb,
        $url->build_absolute($url_params)
    );

    if ($is_manage_page) {
        add_breadcrumb(
            $lang->newpoints_manage_page_breadcrumb
        );
    }

    $page_url = $url->build($url_params);

    $per_page = (int)get_setting('logs_per_page');

    if ($per_page < 1) {
        $per_page = 10;
    }

    $errors = [];

    $where_clauses = array_map(function ($where_clause) {
        return 'l.' . $where_clause;
    }, $instance_where_clauses);

    if ($is_manage_page) {
        $where_clauses[] = "instance_id IN ('" . implode("','", $is_moderator) . "')";
    }

    if ($filter['instances']) {
        $where_clauses[] = "l.instance_id IN ('" . implode("','", $filter['instances']) . "')";

        $url_params['filter[instances]'] = $filter['instances'];
    }

    if ($mybb->request_method && $is_moderator && $is_manage_page) {
        if ($mybb->get_input('view') === 'delete') {
            $delete_where_clauses = array_merge(
                $instance_where_clauses,
                ["instance_id IN ('" . implode("','", $is_moderator) . "')"]
            );

            $log_id = $mybb->get_input('log_id', MyBB::INPUT_INT);

            $log_data = log_get($log_id, $delete_where_clauses);

            if ($log_data) {
                log_delete($log_id, $delete_where_clauses);

                redirect($page_url, $lang->newpoints_logs_page_success_log_deleted);
            } else {
                $errors[] = $lang->newpoints_logs_page_errors_no_logs_selected;
            }
        }
    }

    $filters = $mybb->get_input('filter', MyBB::INPUT_ARRAY);

    $filter_user_name = '';

    if ($is_moderator && $is_manage_page && !empty($filters['username'])) {
        $user_data = get_user_by_username($filters['username']);

        if (empty($user_data['uid'])) {
            $errors[] = $lang->newpoints_logs_page_errors_invalid_user_name;
        } else {
            $user_id = (int)$user_data['uid'];

            $where_clauses['user'] = "l.uid='{$user_id}'";

            $url_params['filter[username]'] = $filters['username'];

            $filter_user_name = htmlspecialchars_uni($filters['username']);
        }
    }

    if (!isset($where_clauses['user']) && !$is_manage_page) {
        $where_clauses['user'] = "l.uid='{$current_user_id}'";
    }

    if (isset($filters['actions'])) {
        $filter_actions = array_map([$db, 'escape_string'], $filters['actions']);

        $filter_actions = implode("','", $filter_actions);

        $where_clauses[] = "action IN ('{$filter_actions}')";

        foreach ($filters['actions'] as $action) {
            $url_params["filter[actions][{$action}]"] = $action;
        }
    }

    if ($errors) {
        $newpoints_errors = inline_error($errors);
    }

    $query = $db->simple_select('newpoints_log l', 'COUNT(lid) as total_logs', implode(' AND ', $where_clauses));

    $total_logs = (int)$db->fetch_field($query, 'total_logs');

    $current_page = $mybb->get_input('page', MyBB::INPUT_INT);

    $pages = $total_logs / $per_page;

    $pages = ceil($pages);

    if ($current_page > $pages || $current_page <= 0) {
        $current_page = 1;
    }

    if ($current_page) {
        $limit_start = ($current_page - 1) * $per_page;
    } else {
        $limit_start = 0;

        $current_page = 1;
    }

    if ($total_logs > $per_page) {
        $newpoints_pagination = multipage(
            $total_logs,
            $per_page,
            $current_page,
            $url->build($url_params)
        );

        if ($newpoints_pagination) {
            $newpoints_pagination = eval(templates_get('page_pagination'));
        }
    }

    $query = $db->simple_select(
        "newpoints_log l LEFT JOIN {$db->table_prefix}users u ON (u.uid=l.uid)",
        'l.lid, l.action, l.points, l.date, l.log_primary_id, l.log_secondary_id, l.log_tertiary_id, l.log_type, l.instance_id, u.uid, u.username, u.usergroup, u.displaygroup',
        implode(' AND ', $where_clauses),
        ['order_by' => 'lid desc, date', 'order_dir' => 'desc', 'limit' => $per_page, 'limit_start' => $limit_start]
    );

    $alternative_background = alt_trow(true);

    $logs_rows = '';

    $column_span = 9;

    $thead_user = $thead_options = '';

    if ($is_moderator && $is_manage_page) {
        $column_span += 2;

        $thead_user = eval(templates_get('logs_table_thead_user'));

        $delete_url = $url->build(array_merge($url_params, ['view' => 'delete']));

        $thead_options = eval(templates_get('logs_table_thead_delete'));
    }

    while ($log_data = $db->fetch_array($query)) {
        $log_id = (int)$log_data['lid'];

        $log_id = my_number_format($log_id);

        $log_action = htmlspecialchars_uni($log_data['action']);

        $instance_id = (int)$log_data['instance_id'];

        try {
            $log_points = instance_object($instance_id)
                ->points_format((float)$log_data['points']);

            $log_instance_name_upper = instance_object($instance_id)->get_display_name_upper();
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }

        $log_date = my_date('normal', $log_data['date']);

        $log_primary = $log_secondary = $log_tertiary = $log_type = '-';

        switch ($log_data['log_type']) {
            case LOGGING_TYPE_INCOME:
                $log_type = $lang->newpoints_logs_page_table_action_type_income;
                break;
            case LOGGING_TYPE_CHARGE:
                $log_type = $lang->newpoints_logs_page_table_action_type_charge;
                break;
        }

        run_hooks('logs_log_row');

        switch ($log_data['action']) {
            case 'donation':
            case 'donation_sent':
                $donation_user_data = get_user($log_data['log_primary_id']);

                $log_primary = build_profile_link(
                    htmlspecialchars_uni($donation_user_data['username'] ?? ''),
                    $donation_user_data['uid'] ?? 0
                );
                break;
        }

        switch ($log_data['action']) {
            case 'donation':
                $log_action = $lang->newpoints_logs_page_table_action_donation_received;
                break;
            case 'donation_sent':
                $log_action = $lang->newpoints_logs_page_table_action_donation_sent;
                break;
        }

        foreach (
            [
                INCOME_TYPE_THREAD => function (array &$log_data) use (&$log_primary, &$log_secondary): array {
                    global $mybb, $lang;

                    $thread_data = get_thread($log_data['log_primary_id']);

                    $forum_data = get_forum($log_data['log_secondary_id']);

                    if (!empty($thread_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_THREAD_REPLY => function (array &$log_data) use (
                    &$log_primary,
                    &$log_secondary,
                    &$log_tertiary
                ): array {
                    global $mybb, $lang;

                    $thread_data = get_thread($log_data['log_primary_id']);

                    $post_data = get_forum($log_data['log_secondary_id']);

                    $forum_data = get_forum($log_data['log_tertiary_id']);

                    if (!empty($thread_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($post_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_post,
                            $mybb->settings['bburl'],
                            get_post_link($post_data['pid']) . "#pid{$post_data['pid']}",
                            post_parser()->parse_badwords($post_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_tertiary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_POST => function (array &$log_data) use (
                    &$log_primary,
                    &$log_secondary,
                    &$log_tertiary
                ): array {
                    global $mybb, $lang;

                    $post_data = get_forum($log_data['log_primary_id']);

                    $thread_data = get_thread($log_data['log_secondary_id']);

                    $forum_data = get_forum($log_data['log_tertiary_id']);

                    if (!empty($post_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_post,
                            $mybb->settings['bburl'],
                            get_post_link($post_data['pid']) . "#pid{$post_data['pid']}",
                            post_parser()->parse_badwords($post_data['subject'])
                        );
                    }

                    if (!empty($thread_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_tertiary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_POST_CHARACTER => function (array &$log_data) use (
                    &$log_primary,
                    &$log_secondary,
                    &$log_tertiary
                ): array {
                    global $mybb, $lang;

                    $thread_data = get_thread($log_data['log_primary_id']);

                    $post_data = get_forum($log_data['log_secondary_id']);

                    $forum_data = get_forum($log_data['log_tertiary_id']);

                    if (!empty($thread_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_thread,
                            $mybb->settings['bburl'],
                            get_thread_link($thread_data['tid']),
                            post_parser()->parse_badwords($thread_data['subject'])
                        );
                    }

                    if (!empty($post_data)) {
                        $log_secondary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_post,
                            $mybb->settings['bburl'],
                            get_post_link($post_data['pid']) . "#pid{$post_data['pid']}",
                            post_parser()->parse_badwords($post_data['subject'])
                        );
                    }

                    if (!empty($forum_data)) {
                        $log_tertiary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_forum,
                            $mybb->settings['bburl'],
                            get_forum_link($forum_data['fid']),
                            htmlspecialchars_uni(strip_tags($forum_data['name']))
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_USER_REGISTRATION => function (array &$log_data): array {
                    return $log_data;
                },
                INCOME_TYPE_USER_REFERRAL => function (array &$log_data) use (&$log_primary): array {
                    global $mybb, $lang;

                    $user_data = get_user($log_data['log_primary_id']);

                    if (!empty($user_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_user,
                            build_profile_link(
                                format_name(
                                    htmlspecialchars_uni($user_data['username']),
                                    $user_data['usergroup'],
                                    $user_data['displaygroup']
                                ),
                                $user_data['uid']
                            )
                        );
                    }

                    return $log_data;
                },
                INCOME_TYPE_PRIVATE_MESSAGE => function (array &$log_data) use (&$log_primary): array {
                    global $mybb, $lang, $db;

                    $private_message_id = (int)($log_data['log_primary_id'] ?? (
                        $log_data['log_secondary_id'] ?? (
                        $log_data['log_tertiary_id'] ?? 0
                    )
                    ));

                    if (!empty($private_message_id)) {
                        $query = $db->simple_select(
                            'privatemessages',
                            'toid',
                            "pmid='{$private_message_id}'"
                        );

                        $user_data = get_user($db->fetch_field($query, 'toid'));
                    }

                    if (!empty($user_data)) {
                        $log_primary = $lang->sprintf(
                            $lang->newpoints_logs_page_table_log_user,
                            build_profile_link(
                                format_name(
                                    htmlspecialchars_uni($user_data['username']),
                                    $user_data['usergroup'],
                                    $user_data['displaygroup']
                                ),
                                $user_data['uid']
                            )
                        );
                    }

                    return $log_data;
                },
            ] as $income_type => $log_function
        ) {
            if ($log_data['action'] === "income_{$income_type}") {
                $language_variable = "newpoints_logs_page_table_action_income_{$income_type}";

                $log_action = $lang->{$language_variable};

                if (is_callable($log_function)) {
                    $log_data = $log_function($log_data);
                }
            }
        }

        $column_user = $column_options = '';

        if ($is_moderator && $is_manage_page) {
            $user_name = '';

            if (!empty($log_data['uid'])) {
                $user_name = build_profile_link(
                    format_name(
                        htmlspecialchars_uni($log_data['username']),
                        $log_data['usergroup'],
                        $log_data['displaygroup']
                    ),
                    $log_data['uid']
                );
            }

            $column_user = eval(templates_get('logs_table_row_user'));

            $column_options = eval(templates_get('logs_table_row_delete'));
        }

        $logs_rows .= eval(templates_get('logs_table_row'));

        $alternative_background = alt_trow();
    }

    if (!$logs_rows) {
        $logs_rows = eval(templates_get('logs_table_empty'));
    }

    $page_title = $lang->newpoints_logs_page_title;

    $newpoints_content = eval(templates_get('logs_table'));

    $action_types = [];

    $query = $db->simple_select(
        'newpoints_log',
        'action',
        implode(' AND ', $instance_where_clauses),
        ['group_by' => 'action']
    );

    while ($action = $db->fetch_field($query, 'action')) {
        $action_types[htmlspecialchars_uni($action)] = htmlspecialchars_uni($action);

        switch ($action) {
            case 'donation':
                $action_types[htmlspecialchars_uni(
                    $action
                )] = $lang->newpoints_logs_page_table_action_donation_received;
                break;
            case 'donation_sent':
                $action_types[htmlspecialchars_uni($action)] = $lang->newpoints_logs_page_table_action_donation_sent;
                break;
        }

        foreach (
            [
                INCOME_TYPE_THREAD,
                INCOME_TYPE_THREAD_REPLY,
                INCOME_TYPE_POST,
                INCOME_TYPE_POST_CHARACTER,
                INCOME_TYPE_USER_REGISTRATION,
                INCOME_TYPE_USER_REFERRAL,
                INCOME_TYPE_PRIVATE_MESSAGE,
            ] as $income_type
        ) {
            if ($action === "income_{$income_type}") {
                $language_variable = "newpoints_logs_page_table_action_income_{$income_type}";

                $action_types[$action] = $lang->{$language_variable};
            }
        }
    }

    if ($is_moderator && !$is_manage_page) {
        $manage_url = $url->build(array_merge($url_params, ['manage' => 1]));

        $newpoints_buttons = eval(templates_get('button_manage'));
    }

    $instances_select = build_instances_select('filter[instances][]', true, filter: $filter);

    run_hooks('logs_end');

    $actions_select = (function () use ($action_types, $filters): string {
        $select_name = 'filter[actions][]';

        $select_options = '';

        $select_multiple = 'multiple="multiple"';

        foreach ($action_types as $option_value => $option_name) {
            $selected_element = '';

            if (isset($filters['actions']) && in_array($option_value, $filters['actions'])) {
                $selected_element = 'selected="selected"';
            }

            $select_options .= eval(templates_get('input_select_option'));
        }

        return eval(templates_get('input_select'));
    })();

    $newpoints_additional = eval(templates_get('logs_filter_table'));

    $page_contents = eval(templates_get('page'));

    output_page($page_contents);

    exit;
} elseif (empty($mybb->input['action'])) {
    $latest_transactions = [];

    $income_tables = '';

    run_hooks('home_start');

    foreach ($instance_objects as $instance_id => $instance) {
        $user_group_rate_addition = $instance->get_user_permission_rate_addition();

        $user_group_rate_subtraction = $instance->get_user_permission_rate_substraction();

        $user_rate_description = $lang->sprintf(
            $lang->newpoints_home_user_rate_description,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
            $user_group_rate_addition,
            $user_group_rate_subtraction
        );

        $income_settings = build_income_table($instance);

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        run_hooks('home_intermediate');

        #Deprecated
        $newpoints_home_desc = $lang->newpoints_home_desc;

        $description_header = $lang->sprintf(
            $lang->newpoints_home_description_header,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
        );

        $income_tables .= eval(templates_get('home_income'));
    }

    run_hooks('home_end');

    $latest_transactions = implode(' ', $latest_transactions);

    $page = eval(templates_get('home'));

    output_page($page);

    exit;
}

run_hooks('terminate');

exit;