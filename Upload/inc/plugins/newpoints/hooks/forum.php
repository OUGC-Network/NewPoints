<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/hooks/forum.php)
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

namespace Newpoints\Hooks\Forum;

use MyBB;

use MybbStuff_MyAlerts_AlertFormatterManager;

use function Newpoints\Core\count_characters;
use function Newpoints\Core\get_income_value;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\Core\load_set_guest_data;
use function Newpoints\Core\log_add;
use function Newpoints\Core\main_file_name;
use function Newpoints\Core\my_alerts_initiate;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_format;
use function Newpoints\Core\points_subtract;
use function Newpoints\Core\templates_get;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\url_handler_build;
use function Newpoints\Core\user_can_get_points;
use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\Core\INCOME_TYPE_PAGE_VIEW;
use const Newpoints\Core\INCOME_TYPE_POLL;
use const Newpoints\Core\INCOME_TYPE_POLL_VOTE;
use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_THREAD_RATE;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\INCOME_TYPE_VISIT;
use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;

function global_start09(): bool
{
    load_set_guest_data();

    my_alerts_initiate();

    return true;
}

// Loads plugins from global_start and runs a new hook called 'newpoints_global_start' that can be used by NewPoints plugins (instead of global_start)
// global_start can't be used by NP plugins
// todo, fix plugins not being able to use global_start by loading plugins before
function global_start(): bool
{
    global $templatelist;

    if (isset($templatelist)) {
        $templatelist .= ',';
    }

    $template_list = [
        'global' => [
            'newpoints_header_menu',
            'newpoints_points_format',
            'multipage_page_current',
            'multipage_page',
            'multipage_nextpage',
            'multipage',
        ],
        'newpoints.php' => [
            'newpoints_menu_category',
            'newpoints_page_pagination',
        ],
        'showthread.php' => [
            'newpoints_postbit',
            'newpoints_donate_inline'
        ],
        'member.php' => [
            'newpoints_profile',
            'newpoints_donate_inline'
        ]
    ];

    $template_list = run_hooks('global_start', $template_list);

    foreach ($template_list as $script_name => $templates) {
        if (!is_array($templates)) {
            $templatelist .= ',' . $templates;

            continue;
        }

        if ($script_name === 'global' || $script_name === THIS_SCRIPT) {
            $templatelist .= ',' . implode(',', $templates);
        }
    }

    //users_update();

    return true;
}

function global_intermediate(): bool
{
    global $mybb;
    global $newpoints_header_menu;

    global $lang;

    $newpoints_file = main_file_name();

    language_load();

    $newpoints_header_menu = eval(templates_get('header_menu'));

    return true;
}

function pre_parse_page(string &$page_contents): string
{
    global $mybb;
    global $newpoints_is_error_page;

    if (empty($mybb->user['uid']) || !empty($newpoints_is_error_page)) {
        return $page_contents;
    }

    global $db;

    $forum_id = $thread_id = $post_id = 0;

    if (defined('THIS_SCRIPT')) {
        switch (THIS_SCRIPT) {
            case 'announcements.php':
                $announcement_id = $mybb->get_input('aid', MyBB::INPUT_INT);

                $query = $db->simple_select("announcements", "fid", "aid='{$announcement_id}'");

                $announcement_data = $db->fetch_array($query);

                $forum_id = (int)$announcement_data['fid'];
                break;
            case 'editpost.php':
                $post_id = $mybb->get_input('pid', MyBB::INPUT_INT);

                $post_data = get_post($post_id);

                $forum_id = (int)$post_data['fid'];

                $thread_id = (int)$post_data['tid'];
                break;
            case 'forumdisplay.php':
                $forum_id = $mybb->get_input('fid', MyBB::INPUT_INT);

                break;
            case 'misc.php':
                global $newpoints_forum_id;

                if (!empty($newpoints_forum_id)) {
                    $forum_id = $newpoints_forum_id;
                }
                break;
            case 'newreply.php':
            case 'printthread.php':
            case 'sendthread.php':
            case 'showthread.php':
                $thread_id = $mybb->get_input('tid', MyBB::INPUT_INT);

                $thread_data = get_thread($thread_id);

                $forum_id = (int)$thread_data['fid'];
                break;
            case 'newthread.php':
                if ($mybb->get_input('action') == 'editdraft' ||
                    ($mybb->get_input('savedraft') && $mybb->get_input('tid', MyBB::INPUT_INT)) ||
                    ($mybb->get_input('tid', MyBB::INPUT_INT) && $mybb->get_input('pid', MyBB::INPUT_INT))
                ) {
                    $thread_data = get_thread($mybb->get_input('tid', MyBB::INPUT_INT));

                    $thread_id = (int)$thread_data['tid'];

                    $query = $db->simple_select(
                        'posts',
                        'pid',
                        "tid='" . $mybb->get_input('tid', MyBB::INPUT_INT) . "' AND visible='-2'",
                        ['order_by' => 'dateline, pid', 'limit' => 1]
                    );

                    $post_data = $db->fetch_array($query);

                    $post_id = (int)$post_data['pid'];
                } else {
                    $forum_id = $mybb->get_input('fid', MyBB::INPUT_INT);
                }

                break;
            case 'polls.php':
                if ($mybb->get_input('action') == 'newpoll') {
                    $thread_id = $mybb->get_input('tid', MyBB::INPUT_INT);

                    $thread_data = get_thread($thread_id);

                    $forum_id = (int)$thread_data['fid'];
                }

                if ($mybb->get_input('action') == 'editpoll' || $mybb->get_input('action') == 'showresults') {
                    $poll_id = $mybb->get_input('pid', MyBB::INPUT_INT);

                    $query = $db->simple_select('polls', 'tid', "pid='{$poll_id}'");

                    $thread_id = (int)$db->fetch_field($query, 'tid');

                    $thread_data = get_thread($thread_id);

                    $forum_id = (int)$thread_data['fid'];
                }
                break;
        }
    }

    $current_user_id = (int)$mybb->user['uid'];

    if (user_can_get_points($current_user_id, $forum_id)) {
        $income_value = get_income_value(INCOME_TYPE_PAGE_VIEW, $current_user_id);

        $income_value *= $mybb->usergroup['newpoints_rate_addition'];

        if ($income_value) {
            points_add_simple(
                $current_user_id,
                $income_value
            );

            log_add(
                'income_' . INCOME_TYPE_PAGE_VIEW,
                '',
                get_user($current_user_id)['username'] ?? '',
                $current_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }
    }

    if (user_can_get_points($current_user_id, $forum_id)) {
        $income_value = get_income_value(INCOME_TYPE_VISIT, $current_user_id);

        $income_value *= $mybb->usergroup['newpoints_rate_addition'];

        if ($income_value) {
            if ((TIME_NOW - $mybb->user['lastactive']) > $mybb->usergroup['newpoints_income_visit_minutes'] * 60) {
                points_add_simple(
                    $current_user_id,
                    $income_value
                );

                log_add(
                    'income_' . INCOME_TYPE_VISIT,
                    '',
                    get_user($current_user_id)['username'] ?? '',
                    $current_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                    LOGGING_TYPE_INCOME
                );
            }
        }
    }

    return $page_contents;
}

function misc_rules_end(): bool
{
    global $mybb;
    global $newpoints_forum_id;

    $newpoints_forum_id = $mybb->get_input('fid', MyBB::INPUT_INT);

    return true;
}

function error(string &$error_message): string
{
    global $newpoints_is_error_page;

    $newpoints_is_error_page = true;

    return $error_message;
}

function xmlhttp09(): bool
{
    load_set_guest_data();

    my_alerts_initiate();

    return true;
}

// Loads plugins from xmlhttp and runs a new hook called 'newpoints_xmlhttp' that can be used by NewPoints plugins (instead of xmlhttp)
// xmlhttp can't be used by NP plugins
// todo, fix plugins not being able to use xmlhttp by loading plugins before
function xmlhttp(): bool
{
    run_hooks('xmlhttp');

    return true;
}

// Loads plugins when in archive and runs a new hook called 'newpoints_archive_start' that can be used by NewPoints plugins (instead of archive_start)
// todo, fix plugins not being able to use archive_start by loading plugins before
function archive_start(): bool
{
    load_set_guest_data();

    run_hooks('archive_start');

    return true;
}

function postbit(array &$post): array
{
    global $mybb, $currency, $points, $lang;

    $post['newpoints_postbit'] = $points = $post['newpoints_balance_formatted'] = '';

    if (empty($post['uid'])) {
        return $post;
    }

    language_load();

    $newpoints_file = main_file_name();

    $currency = get_setting('main_curname');

    $points = $post['newpoints_balance_formatted'] = points_format((float)$post['newpoints']);

    $post_user_id = (int)$post['uid'];

    $current_user_id = (int)$mybb->user['uid'];

    if (!empty($mybb->usergroup['newpoints_can_donate']) && $current_user_id && $post_user_id !== $current_user_id) {
        $donate = eval(templates_get('donate_inline'));
    } else {
        $donate = '';
    }

    $post['newpoints_postbit'] = eval(templates_get('postbit'));

    $post['user_details'] = str_replace(
        ['<!--NEWPOINTS_POST_USER_DETAILS-->', '<!--NEWPOINTS_POST_USER_POINTS-->'],
        [$post['newpoints_postbit'], $post['newpoints_balance_formatted']],
        $post['user_details']
    );

    return $post;
}

function postbit_prev(array &$post_data): array
{
    return postbit($post_data);
}

function postbit_pm(array &$post_data): array
{
    return postbit($post_data);
}

function postbit_announcement(array &$post_data): array
{
    return postbit($post_data);
}

function member_profile_end(): bool
{
    global $mybb, $currency, $points, $memprofile, $newpoints_profile, $lang, $uid;

    $newpoints_profile = '';

    global $newpoints_profile_user_balance_formatted;

    language_load();

    $newpoints_file = main_file_name();

    $currency = get_setting('main_curname');

    $points = $newpoints_profile_user_balance_formatted = points_format((float)$memprofile['newpoints']);

    $uid = (int)$memprofile['uid'];

    if (!empty($mybb->usergroup['newpoints_can_donate']) && !empty($mybb->user['uid']) && $uid !== $mybb->user['uid']) {
        $donate = eval(templates_get('donate_inline'));
    } else {
        $donate = '';
    }

    $newpoints_profile = eval(templates_get('profile'));

    return true;
}

// todo, I'm unsure how this is necessary if we already hook at the data handler
// removed in 3.1.5 because the data handler should take care of this already
function xmlhttp_edit_post_end(): bool
{
    return false;
}

function class_moderation_delete_post_start(&$post_id): int
{
    $post_id = (int)$post_id;

    $post_data = get_post($post_id);

    // It's currently soft deleted, so we do nothing as we already subtracted points when doing that
    // If it's not visible (unapproved) we also don't take out any money
    if ((int)$post_data['visible'] === -1 || (int)$post_data['visible'] === 0) {
        return $post_id;
    }

    $post_user_id = (int)$post_data['uid'];

    $forum_id = (int)$post_data['fid'];

    $thread_data = get_thread($post_data['tid']);

    $thread_user_id = (int)$thread_data['uid'];

    $thread_id = (int)$thread_data['tid'];

    if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
        // we are not the thread started so remove points from him/her
        $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

        $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id) *
            ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_value) {
            points_subtract(
                $thread_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_THREAD_REPLY,
                '',
                get_user($thread_user_id)['username'] ?? '',
                $thread_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }
    }

    if (!user_can_get_points($post_user_id, $forum_id)) {
        return $post_id;
    }

    // calculate points per character bonus
    // let's see if the number of characters in the post is greater than the minimum characters
    $characters_count = count_characters($post_data['message']);

    $income_bonus = 0;

    $post_user_group_permissions = users_get_group_permissions($post_user_id);

    if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
        $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
    }

    $income_bonus *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

    if ($income_bonus) {
        points_subtract(
            $post_user_id,
            $income_bonus,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_POST_CHARACTER,
            '',
            get_user($post_user_id)['username'] ?? '',
            $post_user_id,
            $income_bonus,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_CHARGE
        );
    }

    $income_value = get_income_value(INCOME_TYPE_POST, $post_user_id);

    $income_value *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

    if ($income_value) {
        points_subtract(
            $post_user_id,
            $income_value,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_POST,
            '',
            get_user($post_user_id)['username'] ?? '',
            $post_user_id,
            $income_value,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_CHARGE
        );
    }

    return $post_id;
}

function class_moderation_soft_delete_posts(array &$post_ids): array
{
    foreach ($post_ids as $post_id) {
        $post_id = (int)$post_id;

        $post_data = get_post($post_id);

        $thread_data = get_thread($post_data['tid']);

        $thread_id = (int)$thread_data['tid'];

        $forum_id = (int)$thread_data['fid'];

        $post_user_id = (int)$post_data['uid'];

        $thread_user_id = (int)$thread_data['uid'];

        if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
            $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

            $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

            $income_value *= ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

            // we are not the thread started so remove points from him/her
            if ($income_value) {
                points_subtract(
                    $thread_user_id,
                    $income_value,
                    $forum_id
                );

                log_add(
                    'income_' . INCOME_TYPE_THREAD_REPLY,
                    '',
                    get_user($thread_user_id)['username'] ?? '',
                    $thread_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                    LOGGING_TYPE_CHARGE
                );
            }
        }

        if (!user_can_get_points($post_user_id, $forum_id)) {
            continue;
        }

        $post_user_group_permissions = users_get_group_permissions($post_user_id);

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $income_bonus = 0;

        $characters_count = count_characters($post_data['message']);

        if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
        }

        $income_bonus *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_bonus) {
            points_subtract(
                $post_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }

        $income_value = get_income_value(INCOME_TYPE_POST, $post_user_id);

        $income_value *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_value) {
            points_subtract(
                $post_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }
    }

    return $post_ids;
}

function class_moderation_restore_posts(array &$post_ids): array
{
    foreach ($post_ids as $post_id) {
        $post_id = (int)$post_id;

        $post_data = get_post($post_id);

        $thread_id = (int)$post_data['tid'];

        $thread_data = get_thread($post_data['tid']);

        $post_user_id = (int)$post_data['uid'];

        $forum_id = (int)$post_data['fid'];

        $thread_user_id = (int)$thread_data['uid'];

        if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
            // we are not the thread started so give points to them

            $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

            $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

            $income_value *= $thread_user_group_permissions['newpoints_rate_addition'];

            if ($income_value) {
                points_add_simple(
                    $thread_user_id,
                    $income_value,
                    $forum_id
                );

                log_add(
                    'income_' . INCOME_TYPE_THREAD_REPLY,
                    '',
                    get_user($thread_user_id)['username'] ?? '',
                    $thread_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                    LOGGING_TYPE_INCOME
                );
            }
        }

        if (!user_can_get_points($post_user_id, $forum_id)) {
            return $post_ids;
        }

        $post_user_group_permissions = users_get_group_permissions($post_user_id);

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $characters_count = count_characters($post_data['message']);

        $income_bonus = 0;

        if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
        }

        $income_bonus *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_bonus) {
            // give points to the author of the post
            points_add_simple(
                $post_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }

        $income_value = get_income_value(INCOME_TYPE_POST, $post_user_id);

        $income_value *= $post_user_group_permissions['newpoints_rate_addition'];

        // give points to the author of the post
        if ($income_value) {
            points_add_simple(
                $post_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }
    }

    return $post_ids;
}

function class_moderation_approve_threads(array &$thread_ids): array
{
    foreach ($thread_ids as $thread_id) {
        $thread_id = (int)$thread_id;

        $thread_data = get_thread($thread_id);

        $post_data = get_post((int)$thread_data['firstpost']);

        $post_id = (int)$post_data['pid'];

        $post_user_id = (int)$post_data['uid'];

        $forum_id = (int)$post_data['fid'];

        if (!user_can_get_points($post_user_id, $forum_id)) {
            continue;
        }

        $post_user_group_permissions = users_get_group_permissions($post_user_id);

        $income_value = get_income_value(INCOME_TYPE_THREAD, $post_user_id);

        $income_value *= $post_user_group_permissions['newpoints_rate_addition'];

        // add points to the poster
        if ($income_value) {
            points_add_simple(
                $post_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_THREAD,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $income_bonus = 0;

        $characters_count = count_characters($post_data['message']);

        if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
        }

        $income_bonus *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_bonus) {
            points_add_simple(
                $post_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }
    }

    return $thread_ids;
}

function class_moderation_approve_posts(array &$post_ids): array
{
    foreach ($post_ids as $post_id) {
        $post_id = (int)$post_id;

        $post_data = get_post($post_id);

        $post_id = (int)$post_data['pid'];

        $thread_data = get_thread($post_data['tid']);

        $forum_id = (int)$thread_data['fid'];

        $thread_id = (int)$thread_data['tid'];

        $post_user_id = (int)$post_data['uid'];

        $thread_user_id = (int)$thread_data['uid'];

        if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
            $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

            $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

            $income_value *= $thread_user_group_permissions['newpoints_rate_addition'];

            if ($income_value) {
                points_add_simple(
                    $thread_user_id,
                    $income_value,
                    $forum_id
                );

                log_add(
                    'income_' . INCOME_TYPE_THREAD_REPLY,
                    '',
                    get_user($thread_user_id)['username'] ?? '',
                    $thread_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                    LOGGING_TYPE_INCOME
                );
            }
        }

        if (!user_can_get_points($post_user_id, $forum_id)) {
            continue;
        }

        $post_user_group_permissions = users_get_group_permissions($post_user_id);

        $income_value = get_income_value(INCOME_TYPE_POST, $post_user_id);

        $income_value *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            // give points to the author of the post
            points_add_simple(
                $post_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $income_bonus = 0;

        $characters_count = count_characters($post_data['message']);

        if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
        }

        $income_bonus *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_bonus) {
            points_add_simple(
                $post_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }
    }

    return $post_ids;
}

function class_moderation_unapprove_threads(array &$thread_ids): array
{
    foreach ($thread_ids as $thread_id) {
        $thread_id = (int)$thread_id;

        $thread_data = get_thread($thread_id);

        $thread_user_id = (int)$thread_data['uid'];

        $post_data = get_post((int)$thread_data['firstpost']);

        $post_id = (int)$post_data['pid'];

        $forum_id = (int)$post_data['fid'];

        if (!user_can_get_points($thread_user_id, $forum_id)) {
            continue;
        }

        $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $characters_count = count_characters($post_data['message']);

        $income_bonus = 0;

        if ($characters_count >= $thread_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $thread_user_id);
        }

        $income_bonus *= ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_bonus) {
            points_subtract(
                $thread_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($thread_user_id)['username'] ?? '',
                $thread_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }

        $income_value = get_income_value(INCOME_TYPE_THREAD, $thread_user_id);

        $income_value *= ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_value) {
            points_subtract(
                $thread_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_THREAD,
                '',
                get_user($thread_user_id)['username'] ?? '',
                $thread_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }
    }

    return $thread_ids;
}

function class_moderation_unapprove_posts(array &$post_ids): array
{
    foreach ($post_ids as $post_id) {
        $post_id = (int)$post_id;

        $post_data = get_post($post_id);

        $thread_data = get_thread($post_data['tid']);

        $thread_id = (int)$thread_data['tid'];

        $post_user_id = (int)$post_data['uid'];

        $forum_id = (int)$post_data['fid'];

        $post_user_group_permissions = users_get_group_permissions($post_user_id);

        $thread_user_id = (int)$thread_data['uid'];

        if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
            $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

            $income_value *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

            // we are not the thread started so remove points from them
            if ($income_value) {
                points_subtract(
                    $thread_user_id,
                    $income_value,
                    $forum_id
                );

                log_add(
                    'income_' . INCOME_TYPE_THREAD_REPLY,
                    '',
                    get_user($thread_user_id)['username'] ?? '',
                    $thread_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                    LOGGING_TYPE_CHARGE
                );
            }
        }

        if (!user_can_get_points($post_user_id, $forum_id)) {
            continue;
        }

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $income_bonus = 0;

        $characters_count = count_characters($post_data['message']);

        if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
        }

        $income_bonus *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_bonus) {
            points_subtract(
                $post_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }

        $income_value = get_income_value(INCOME_TYPE_POST, $post_user_id);

        $income_value *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_value) {
            points_subtract(
                $post_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }
    }

    return $post_ids;
}

function class_moderation_delete_thread(int &$thread_id): int
{
    global $db, $mybb;

    // even though the thread was deleted it was previously cached so we can use get_thread
    $thread_data = get_thread($thread_id);

    $forum_id = (int)$thread_data['fid'];

    // It's currently soft deleted, so we do nothing as we already subtracted points when doing that
    // If it's not visible (unapproved) we also don't take out any money
    if ((int)$thread_data['visible'] === -1 || (int)$thread_data['visible'] === 0) {
        return $thread_id;
    }

    // get post of the thread
    $post_data = get_post($thread_data['firstpost']);

    $post_id = (int)$post_data['pid'];

    $thread_user_id = (int)$thread_data['uid'];

    $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

    if (!user_can_get_points($thread_user_id, $forum_id)) {
        return $thread_id;
    }

    if (!empty($thread_data['poll'])) {
        // if this thread has a poll, remove points from the author of the thread

        $income_value = get_income_value(INCOME_TYPE_POLL, $thread_user_id);

        $income_value *= ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_value) {
            points_subtract(
                $thread_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POLL,
                '',
                get_user($thread_user_id)['username'] ?? '',
                $thread_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }
    }

    $thread_user_id = (int)$thread_data['uid'];

    $post_user_id = (int)$thread_data['tid'];

    $q = $db->simple_select(
        'posts',
        'COUNT(pid) as total_replies',
        "uid!='{$thread_user_id}' AND tid='{$post_user_id}'"
    );

    $thread_data['replies'] = (int)$db->fetch_field($q, 'total_replies');

    $income_value = $thread_data['replies'] * get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

    $income_value *= ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

    if ($income_value) {
        points_subtract(
            $thread_user_id,
            $income_value,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_THREAD_REPLY,
            '',
            get_user($thread_user_id)['username'] ?? '',
            $thread_user_id,
            $income_value,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_CHARGE
        );
    }

    $income_value = get_income_value(INCOME_TYPE_THREAD, $thread_user_id);

    $income_value *= ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

    if ($income_value) {
        points_subtract(
            $thread_user_id,
            $income_value,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_THREAD,
            '',
            get_user($thread_user_id)['username'] ?? '',
            $thread_user_id,
            $income_value,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_CHARGE
        );
    }

    // calculate points per character bonus
    // let's see if the number of characters in the thread is greater than the minimum characters
    $income_bonus = 0;

    $characters_count = count_characters($post_data['message']);

    if ($characters_count >= $thread_user_group_permissions['newpoints_income_post_minimum_characters']) {
        $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $thread_user_id);
    }

    $income_bonus *= ($thread_user_group_permissions['newpoints_rate_subtraction'] / 100);

    if ($income_bonus) {
        points_subtract(
            $thread_user_id,
            $income_bonus,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_POST_CHARACTER,
            '',
            get_user($thread_user_id)['username'] ?? '',
            $thread_user_id,
            $income_bonus,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_CHARGE
        );
    }

    return $thread_id;
}

function class_moderation_soft_delete_threads(array &$thread_ids): array
{
    foreach ($thread_ids as $thread_id) {
        $thread_id = (int)$thread_id;

        $thread_data = get_thread($thread_id);

        $post_data = get_post((int)$thread_data['firstpost']);

        $post_id = (int)$post_data['pid'];

        $post_user_id = (int)$post_data['uid'];

        $forum_id = (int)$post_data['fid'];

        $post_user_group_permissions = users_get_group_permissions($post_user_id);

        $thread_user_id = (int)$thread_data['uid'];

        if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
            $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

            $income_value *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

            // we are not the thread started so remove points from him/her
            if ($income_value) {
                points_subtract(
                    $thread_user_id,
                    $income_value,
                    $forum_id
                );

                log_add(
                    'income_' . INCOME_TYPE_THREAD_REPLY,
                    '',
                    get_user($thread_user_id)['username'] ?? '',
                    $thread_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                    LOGGING_TYPE_CHARGE
                );
            }
        }

        if (!user_can_get_points($post_user_id, $forum_id)) {
            continue;
        }

        $income_value = get_income_value(INCOME_TYPE_THREAD, $post_user_id);

        $income_value *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_value) {
            points_subtract(
                $post_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_THREAD,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $income_bonus = 0;

        $characters_count = count_characters($post_data['message']);

        if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
        }

        $income_bonus *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_bonus) {
            points_subtract(
                $post_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_CHARGE
            );
        }
    }

    return $thread_ids;
}

function class_moderation_restore_threads(array &$thread_ids): array
{
    foreach ($thread_ids as $thread_id) {
        $thread_id = (int)$thread_id;

        $thread_data = get_thread($thread_id);

        $post_data = get_post((int)$thread_data['firstpost']);

        $post_id = (int)$post_data['pid'];

        $post_user_id = (int)$post_data['uid'];

        $forum_id = (int)$post_data['fid'];

        $thread_user_id = (int)$thread_data['uid'];

        $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

        if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
            $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

            $income_value *= $thread_user_group_permissions['newpoints_rate_addition'];

            if ($income_value) {
                points_add_simple(
                    $thread_user_id,
                    $income_value,
                    $forum_id
                );

                log_add(
                    'income_' . INCOME_TYPE_THREAD_REPLY,
                    '',
                    get_user($thread_user_id)['username'] ?? '',
                    $thread_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                    LOGGING_TYPE_INCOME
                );
            }
        }

        if (!user_can_get_points($post_user_id, $forum_id)) {
            continue;
        }

        $post_user_group_permissions = users_get_group_permissions($post_user_id);

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        $income_bonus = 0;

        $characters_count = count_characters($post_data['message']);

        if ($characters_count >= $post_user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);
        }

        $income_bonus *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_bonus) {
            points_add_simple(
                $post_user_id,
                $income_bonus,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_POST_CHARACTER,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_bonus,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }

        $income_value = get_income_value(INCOME_TYPE_THREAD, $post_user_id);

        $income_value *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            points_add_simple(
                $post_user_id,
                $income_value,
                $forum_id
            );

            log_add(
                'income_' . INCOME_TYPE_THREAD,
                '',
                get_user($post_user_id)['username'] ?? '',
                $post_user_id,
                $income_value,
                $post_id,
                $thread_id,
                $forum_id,
                LOGGING_TYPE_INCOME
            );
        }
    }

    return $thread_ids;
}

function polls_do_newpoll_process(): bool
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $current_user_id = (int)$mybb->user['uid'];

    $income_value = get_income_value(INCOME_TYPE_POLL, $current_user_id);

    // give points to the author of the new polls
    if ($income_value && user_can_get_points($current_user_id, $forum_id)) {
        $thread_id = (int)$thread['tid'];

        $post_id = (int)$thread['firstpost'];

        points_add_simple(
            $current_user_id,
            $income_value,
            $fid
        );

        log_add(
            'income_' . INCOME_TYPE_POLL,
            '',
            get_user($current_user_id)['username'] ?? '',
            $current_user_id,
            $income_value,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_INCOME
        );
    }

    return true;
}

function class_moderation_delete_poll(int &$post_id): int
{
    global $db, $mybb;

    $query = $db->simple_select('polls', '*', "pid='{$post_id}'");

    $poll = $db->fetch_array($query);

    $forum_id = (int)$poll['fid'];

    $poll_user_id = (int)$poll['uid'];

    $post_data = get_post($post_id);

    $thread_id = (int)$post_data['tid'];

    if (!user_can_get_points($poll_user_id, $forum_id)) {
        return $post_id;
    }

    $poll_user_group_permissions = users_get_group_permissions($poll_user_id);

    $income_value = get_income_value(INCOME_TYPE_POLL, $poll_user_id);

    $income_value *= ($poll_user_group_permissions['newpoints_rate_subtraction'] / 100);

    if ($income_value) {
        points_subtract(
            $poll_user_id,
            $income_value,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_THREAD,
            '',
            get_user($poll_user_id)['username'] ?? '',
            $poll_user_id,
            $income_value,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_CHARGE
        );
    }

    return $post_id;
}

function polls_vote_process(): bool
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $current_user_id = (int)$mybb->user['uid'];

    if (!user_can_get_points($current_user_id, $forum_id)) {
        return false;
    }

    $income_value = get_income_value(INCOME_TYPE_POLL_VOTE, $current_user_id);

    if ($income_value) {
        $thread_id = (int)$thread['tid'];

        $post_id = (int)$thread['firstpost'];

        // give points to us as we're voting in a poll
        points_add_simple(
            $current_user_id,
            $income_value,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_POLL_VOTE,
            '',
            get_user($current_user_id)['username'] ?? '',
            $current_user_id,
            $income_value,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_INCOME
        );
    }

    return true;
}

function ratethread_process(): bool
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $current_user_id = (int)$mybb->user['uid'];

    if (!user_can_get_points($current_user_id, $forum_id)) {
        return false;
    }

    $income_value = get_income_value(INCOME_TYPE_THREAD_RATE, $current_user_id);

    if ($income_value) {
        $thread_id = (int)$thread['tid'];

        $post_id = (int)$thread['firstpost'];

        // give points us, as we're rating a thread
        points_add_simple(
            $current_user_id,
            $income_value,
            $forum_id
        );

        log_add(
            'income_' . INCOME_TYPE_THREAD_RATE,
            '',
            get_user($current_user_id)['username'] ?? '',
            $current_user_id,
            $income_value,
            $post_id,
            $thread_id,
            $forum_id,
            LOGGING_TYPE_INCOME
        );
    }

    return true;
}

function forumdisplay_start(): bool
{
    global $mybb;

    _helper_evaluate_forum_view_lock($mybb->get_input('fid', MyBB::INPUT_INT));

    return true;
}

function showthread_start(): bool
{
    global $forum;

    _helper_evaluate_forum_view_lock((int)$forum['fid']);

    return true;
}

function editpost_start(): bool
{
    global $mybb;

    $post_id = $mybb->get_input('pid', MyBB::INPUT_INT);

    $post_data = get_post($post_id);

    _helper_evaluate_forum_view_lock((int)$post_data['fid']);

    return true;
}

function sendthread_do_sendtofriend_start(): bool
{
    global $thread;

    _helper_evaluate_forum_view_lock((int)$thread['fid']);

    return true;
}

function sendthread_start(): bool
{
    return sendthread_do_sendtofriend_start();
}

function archive_forum_start(): bool
{
    global $forum;

    _helper_evaluate_forum_view_lock((int)$forum['fid']);

    return true;
}

function archive_thread_start(): bool
{
    return archive_forum_start();
}

function printthread_end(): bool
{
    global $thread;

    _helper_evaluate_forum_view_lock((int)$thread['fid']);

    return true;
}

function newreply_start(): bool
{
    global $fid;

    _helper_evaluate_forum_post_lock((int)$fid);

    return true;
}

function newreply_do_newreply_start(): bool
{
    return newreply_start();
}

function newthread_start(): bool
{
    return newreply_start();
}

function newthread_do_newthread_start(): bool
{
    return newreply_start();
}

function _helper_evaluate_forum_view_lock(int $forum_id): bool
{
    $forum_data = get_forum($forum_id);

    $minimum_points = (float)$forum_data['newpoints_view_lock_points'];

    if (!($minimum_points > 0)) {
        return false;
    }

    global $mybb, $lang;

    if ($minimum_points > $mybb->user['newpoints']) {
        language_load();

        \error(
            $lang->sprintf(
                $lang->newpoints_not_enough_points,
                points_format($minimum_points)
            )
        );
    }

    return true;
}

function _helper_evaluate_forum_post_lock(int $forum_id): bool
{
    $forum_data = get_forum($forum_id);

    $minimum_points = (float)$forum_data['newpoints_post_lock_points'];

    if (!($minimum_points > 0)) {
        return false;
    }

    global $mybb, $lang;

    if ($minimum_points > $mybb->user['newpoints']) {
        language_load();

        \error(
            $lang->sprintf(
                $lang->newpoints_not_enough_points,
                points_format($minimum_points)
            )
        );
    }

    return true;
}

function fetch_wol_activity_end(array &$user_activity): array
{
    if (my_strpos($user_activity['location'], main_file_name()) === false) {
        return $user_activity;
    }

    $user_activity['activity'] = 'newpoints_home';

    if (my_strpos($user_activity['location'], 'action=stats') !== false) {
        $user_activity['activity'] = 'newpoints_stats';
    }

    if (my_strpos($user_activity['location'], 'action=donate') !== false) {
        $user_activity['activity'] = 'newpoints_donation';
    }

    if (my_strpos($user_activity['location'], 'action=logs') !== false) {
        $user_activity['activity'] = 'newpoints_logs';
    }

    return $user_activity;
}

function build_friendly_wol_location_end(array &$hook_arguments): array
{
    global $mybb, $lang;

    language_load();

    switch ($hook_arguments['user_activity']['activity']) {
        case 'newpoints_home':
            $hook_arguments['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_home,
                $mybb->settings['bburl'],
                main_file_name()
            );
            break;
        case 'newpoints_stats':
            $hook_arguments['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_stats,
                $mybb->settings['bburl'],
                url_handler_build(['action' => 'stats'])
            );
            break;
        case 'newpoints_donation':
            $hook_arguments['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_donation,
                $mybb->settings['bburl'],
                url_handler_build(['action' => 'donate'])
            );
            break;
        case 'newpoints_logs':
            $hook_arguments['location_name'] = $lang->sprintf(
                $lang->newpoints_wol_location_logs,
                $mybb->settings['bburl'],
                url_handler_build(['action' => 'logs'])
            );
            break;
    }

    return $hook_arguments;
}

function memberlist_start(): bool
{
    global $mybb;

    if ($mybb->get_input('sort') === 'newpoints') {
        global $newpointsMemberListSort;

        $newpointsMemberListSort = true;
    }

    return true;
}

function memberlist_intermediate(): bool
{
    global $newpointsMemberListSort;

    if (!empty($newpointsMemberListSort)) {
        global $mybb;
        global $sort, $sort_field;

        $sort_field = 'u.newpoints';

        $sort = $mybb->input['sort'] = 'newpoints';
    }

    return true;
}

function memberlist_user(array &$user_data): array
{
    $user_data['newpoints'] = (float)($user_data['newpoints'] ?? 0);

    $user_data['newpoints_formatted'] = points_format($user_data['newpoints']);

    return $user_data;
}

function myalerts_register_client_alert_formatters(): bool
{
    if (!get_setting('main_my_alerts_enabled') ||
        !class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') ||
        !class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        return false;
    }

    global $newpoints_my_alerts_formatters;

    $hook_arguments = [
        'newpoints_my_alerts_formatters' => &$newpoints_my_alerts_formatters,
    ];

    $hook_arguments = run_hooks('my_alerts_register_client_alert_formatters', $hook_arguments);

    global $mybb, $lang;

    foreach ($newpoints_my_alerts_formatters as $plugin_code => $formatter_data) {
        $formatter_manager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();

        if (empty($formatter_manager)) {
            $formatter_manager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
        }

        if (!empty($formatter_manager)) {
            foreach ($formatter_data['alert_classes'] as $alert_type => $alert_class_name) {
                $formatter_manager->registerFormatter(
                    new $alert_class_name(
                        $mybb,
                        $lang,
                        "newpoints_{$formatter_data['plugin_code']}{$alert_type}"
                    )
                );
            }
        }
    }

    return true;
}

function myalerts_load_lang(): string
{
    if (!get_setting('main_my_alerts_enabled')) {
        return '';
    }

    $hook_arguments = [];

    $hook_arguments = run_hooks('my_alerts_language_load', $hook_arguments);

    return '';
}