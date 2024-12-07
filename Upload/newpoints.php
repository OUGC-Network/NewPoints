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

use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\Core\log_add;
use function Newpoints\Core\main_file_name;
use function Newpoints\Core\page_build_menu;
use function Newpoints\Core\page_build_menu_options;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_format;
use function Newpoints\Core\private_message_send;
use function Newpoints\Core\templates_get;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\url_handler_set;
use function Newpoints\Core\users_get_by_username;

const IN_MYBB = 1;

const THIS_SCRIPT = 'newpoints.php';

const NP_DISABLE_GUESTS = false;

$templatelist = 'newpoints_option, newpoints_menu, newpoints_home_income_row, newpoints_home_income_table, newpoints_home, newpoints_statistics_richest_user, newpoints_no_results, newpoints_statistics, newpoints_donate_form, newpoints_donate, newpoints_option_selected';

require_once './global.php';

if (!function_exists('\Newpoints\Core\language_load')) {
    error_no_permission();
}

global $mybb, $plugins, $lang, $db, $templates;

$mybb->input['action'] = $mybb->get_input('action');

$newpoints_file = main_file_name();

url_handler_set($newpoints_file);

run_hooks('begin');

// Allow guests here? Some plugins may allow guest access and they may hook to newpoints_start
if (empty($mybb->usergroup['newpoints_can_see_page'])) {
    error_no_permission();
}

language_load();

$options = page_build_menu_options();

$newpoints_menu = page_build_menu();

$newpoints_errors = '';

add_breadcrumb($lang->newpoints, $newpoints_file);

run_hooks('start');

// Block guests here
if (!$mybb->user['uid']) {
    error_no_permission();
}

// no action=home
if (!$mybb->get_input('action')) {
    $income_settings = '';

    run_hooks('home_start');

    // get income settings' titles, descriptions and its value
    $query = $db->simple_select('newpoints_settings', '*', 'plugin=\'income\'');
    while ($setting = $db->fetch_array($query)) {
        $lang_var = 'setting_' . $setting['name'];
        $lang_var_desc = $lang_var . '_desc';
        if (!empty($lang->{$lang_var})) {
            $setting['title'] = $lang->{$lang_var};
        }

        $income_amount = $lang->sprintf(
            $lang->newpoints_income_amount,
            get_setting('main_curname')
        );

        if (!empty($lang->{$lang_var_desc})) {
            $setting['description'] = $lang->{$lang_var_desc};
        }

        if ($setting['name'] == 'newpoints_income_minchar') {
            $value = $setting['value'] . ' ' . $lang->newpoints_chars;
        } else {
            $value = points_format((float)$setting['value']);
        }

        $income_settings .= eval(templates_get('home_income_row'));
    }

    $latest_transactions = [];

    $income_setting_params = [
        'newpoints_allowance' => [
            'points' => (float)$mybb->usergroup['newpoints_allowance'],
            //'rate' => (float)$mybb->usergroup['newpoints_allowance'],
            'time' => (int)$mybb->usergroup['newpoints_allowance_period']
        ],
    ];

    run_hooks('home_end');

    foreach ($income_setting_params as $income_key => $income_setting) {
        $setting['title'] = $lang->{"setting_{$income_key}"};

        $setting['description'] = $lang->sprintf(
            $lang->{"setting_{$income_key}_desc"},
            !empty($income_setting['time']) ? my_number_format($income_setting['time'] / 60) : 0
        );

        $value = points_format($income_setting['points']);

        $income_settings .= eval(templates_get('home_income_row'));
    }

    var_dump($income_setting_params, );

    $latest_transactions = implode(' ', $latest_transactions);

    $income_settings = eval(templates_get('home_income_table'));

    $newpoints_home_desc = $lang->newpoints_home_desc;

    $page = eval(templates_get('home'));

    output_page($page);
}

if ($mybb->get_input('action') == 'stats') {
    add_breadcrumb($lang->newpoints_statistics, url_handler_build(['action' => 'stats']));

    if (empty($mybb->usergroup['newpoints_can_see_stats'])) {
        error($lang->newpoints_stats_disabled);
    }

    $richest_users = '';
    $bgcolor = alt_trow();

    $fields = ['uid', 'username', 'newpoints', 'usergroup', 'displaygroup'];

    $statistics_items = [];

    run_hooks('stats_start');

    // get richest users
    $query = $db->simple_select(
        'users',
        implode(',', $fields),
        '',
        [
            'order_by' => 'newpoints',
            'order_dir' => 'DESC',
            'limit' => (int)get_setting('main_stats_richestusers')
        ]
    );
    while ($user = $db->fetch_array($query)) {
        $bgcolor = alt_trow();

        $user['username'] = build_profile_link(
            format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']),
            intval($user['uid'])
        );
        $user['newpoints'] = points_format((float)$user['newpoints']);

        run_hooks('stats_richest_users');

        $richest_users .= eval(templates_get('statistics_richest_user'));
    }

    if ($richest_users == '') {
        $colspan = 2;
        $no_results = $lang->newpoints_noresults;
        $richest_users = eval(templates_get('no_results'));
    }

    run_hooks('stats_middle');

    $last_donations = '';
    $bgcolor = alt_trow();

    // get latest donations
    $query = $db->query(
        "
        SELECT l.*, u.usergroup, u.displaygroup
		FROM {$db->table_prefix}newpoints_log l
		LEFT JOIN {$db->table_prefix}users u ON (u.uid=l.uid)
		WHERE l.action='donation'
		ORDER BY l.date DESC
		LIMIT " . (int)get_setting('donations_stats_latest')
    );

    while ($donation = $db->fetch_array($query)) {
        $bgcolor = alt_trow();

        $data = explode('-', $donation['data']);

        $donation['to'] = build_profile_link(htmlspecialchars_uni($data[0]), intval($data[1]));
        $donation['from'] = build_profile_link(
            format_name(htmlspecialchars_uni($donation['username']), $donation['usergroup'], $donation['displaygroup']),
            intval($donation['uid'])
        );

        $donation['amount'] = points_format((float)$data[2]);
        $donation['date'] = my_date(
                $mybb->settings['dateformat'],
                intval($donation['date']),
                '',
                false
            ) . ', ' . my_date($mybb->settings['timeformat'], intval($donation['date']));

        run_hooks('stats_last_donations');

        $last_donations .= eval(templates_get('statistics_donation'));
    }

    if ($last_donations == '') {
        $colspan = 4;
        $no_results = $lang->newpoints_noresults;
        $last_donations = eval(templates_get('no_results'));
    }

    $statistics_items = implode(' ', $statistics_items);

    $page = eval(templates_get('statistics'));

    run_hooks('stats_end');

    output_page($page);
} elseif ($mybb->get_input('action') == 'donate') {
    if (empty($mybb->usergroup['newpoints_can_donate'])) {
        error($lang->newpoints_donations_disabled);
    }

    run_hooks('donate_start');

    // make sure wen're trying to send a donation to ourselves
    $uid = $mybb->get_input('uid', MyBB::INPUT_INT);
    $user = get_user($uid);
    if (!empty($user['username'])) {
        $user['username'] = htmlspecialchars_uni($user['username']);
    } else {
        $user['username'] = '';
    }

    if ($uid == $mybb->user['uid'] || $user['username'] == $mybb->user['username']) {
        error($lang->newpoints_cant_donate_self);
    }

    $pid = $mybb->get_input('pid', 1);

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
} elseif ($mybb->get_input('action') == 'do_donate') {
    if (empty($mybb->usergroup['newpoints_can_donate'])) {
        error($lang->newpoints_donations_disabled);
    }

    verify_post_check($mybb->get_input('postcode'));

    run_hooks('do_donate_start');

    if ($mybb->user['usergroup'] != 4) {
        $q = $db->simple_select(
            'newpoints_log',
            'COUNT(*) as donations',
            'action=\'donation\' AND date>' . (constant(
                    'TIME_NOW'
                ) - (int)get_setting('donations_flood_minutes') * 60 * 60) . ' AND uid=' . (int)$mybb->user['uid']
        );
        $totaldonations = (int)$db->fetch_field($q, 'donations');
        if ($totaldonations >= (int)get_setting('donations_flood_limit')) {
            error($lang->sprintf($lang->newpoints_max_donations_control, $totaldonations));
        }
    }

    $amount = round($mybb->get_input('amount', MyBB::INPUT_FLOAT), (int)get_setting('main_decimal'));

    // do we have enough points?
    if ($amount <= 0 || $amount > $mybb->user['newpoints']) {
        error($lang->newpoints_invalid_amount);
    }

    // make sure we're sending points to a valid user
    $touser = users_get_by_username($mybb->get_input('username'), 'uid,username');

    if (!$touser) {
        error($lang->newpoints_invalid_user);
    }

    // make sure we're not trying to send a donation to ourselves
    if ($mybb->user['uid'] == $touser['uid']) {
        error($lang->newpoints_cant_donate_self);
    }

    // remove points from us
    points_add_simple($mybb->user['uid'], -$amount);

    // give points to user
    points_add_simple($touser['uid'], $amount);

    // send pm to the user if the "Send PM on donate" setting is set to Yes
    if (get_setting('donations_send_private_message')) {
        if ($mybb->get_input('reason') != '') {
            private_message_send(
                [
                    'subject' => $lang->newpoints_donate_subject,
                    'message' => $lang->sprintf(
                        $lang->newpoints_donate_message_reason,
                        points_format($amount),
                        htmlspecialchars_uni($mybb->get_input('reason'))
                    ),
                    'receivepms' => 1,
                    'touid' => $touser['uid']
                ]
            );
        } else {
            private_message_send(
                [
                    'subject' => $lang->newpoints_donate_subject,
                    'message' => $lang->sprintf(
                        $lang->newpoints_donate_message,
                        points_format($amount)
                    ),
                    'receivepms' => 1,
                    'touid' => $touser['uid']
                ]
            );
        }
    }

    // log donation
    log_add(
        'donation',
        $lang->sprintf($lang->newpoints_donate_log, $touser['username'], $touser['uid'], $amount)
    );

    run_hooks('do_donate_end');

    $link = $mybb->settings['bburl'] . '/newpoints.php';

    if ($post = get_post($mybb->get_input('pid', 1))) {
        $link = get_post_link($post['pid'], $post['tid']) . '#pid' . $post['pid'];
    }

    redirect($link, $lang->sprintf($lang->newpoints_donated, points_format($amount)));
}

run_hooks('terminate');

exit;