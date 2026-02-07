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

namespace NewPoints\Hooks\Forum;

use MyBB;
use MybbStuff_MyAlerts_AlertFormatterManager;
use Exception;
use NewPoints\Core\Permissions;
use NewPoints\System\Url;

use function NewPoints\Core\forum_rule_post_lock;
use function NewPoints\Core\forum_rule_view_lock;
use function NewPoints\Core\build_income_table;
use function NewPoints\Core\cache_get_instances;
use function NewPoints\Core\instance_get;
use function NewPoints\Core\instance_object;
use function NewPoints\Core\language_load;
use function NewPoints\Core\load_set_guest_data;
use function NewPoints\Core\log_error;
use function NewPoints\Core\main_file_name;
use function NewPoints\Core\my_alerts_initiate;
use function NewPoints\Core\templates_get;
use function NewPoints\Core\run_hooks;

function global_start09(): void
{
    load_set_guest_data();

    my_alerts_initiate();
}

// Loads plugins from global_start and runs a new hook called 'newpoints_global_start' that can be used by NewPoints plugins (instead of global_start)
// global_start can't be used by NP plugins
// todo, fix plugins not being able to use global_start by loading plugins before
function global_start(): void
{
    global $templatelist;

    if (isset($templatelist)) {
        $templatelist .= ',';
    } else {
        $templatelist = '';
    }

    $template_list = [
        'global' => [
            'newpoints_points_format', // for some reason this template does not want to be cached xd
            'newpoints_header_menu',
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
            'newpoints_postbit_donate',
        ],
        'member.php' => [
            'newpoints_profile',
            'newpoints_profile_donate',
        ],
        'forumdisplay.php' => [
            'forum_income',
            'forum_income_row',
            'forum_income_table',
        ],
    ];

    if (defined('THIS_SCRIPT')) {
        $this_script = THIS_SCRIPT;
    } else {
        $this_script = 'global';
    }

    $template_list = run_hooks('global_start', $template_list);

    foreach ($template_list as $script_name => $templates) {
        if (!is_array($templates)) {
            $templates = [$templates];
        }

        if ($script_name === 'global' || $script_name === $this_script) {
            $templatelist .= ',' . implode(',', $templates);
        }
    }
    //users_update();

}

function global_intermediate(): void
{
    global $mybb;
    global $newpoints_header_menu;
    global $newpoints_globals;
    global $newpoints_user_balance_formatted, $mypoints;

    isset($newpoints_header_menu) || $newpoints_header_menu = '';

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $newpoints_globals[$instance->users_column_get() . '_user_balance_formatted'] =
        $newpoints_user_balance_formatted = $mypoints =
            $instance->points_format($instance->get_user_column_value());

        $newpoints_file = main_file_name();

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        $newpoints_header_menu .= eval(templates_get('header_menu'));
    }
}

/**
 * @throws Exception
 */
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

                $query = $db->simple_select('announcements', 'fid', "aid='{$announcement_id}'");

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
                if ($mybb->get_input('action') === 'editdraft' ||
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
                if ($mybb->get_input('action') === 'newpoll') {
                    $thread_id = $mybb->get_input('tid', MyBB::INPUT_INT);

                    $thread_data = get_thread($thread_id);

                    $forum_id = (int)$thread_data['fid'];
                }

                if ($mybb->get_input('action') === 'editpoll' || $mybb->get_input('action') === 'showresults') {
                    $poll_id = $mybb->get_input('pid', MyBB::INPUT_INT);

                    $query = $db->simple_select('polls', 'tid', "pid='{$poll_id}'");

                    $thread_id = (int)$db->fetch_field($query, 'tid');

                    $thread_data = get_thread($thread_id);

                    $forum_id = (int)$thread_data['fid'];
                }
                break;
        }
    }

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $instance->income_page_view()
                ->income_visit();
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $post_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }

    return $page_contents;
}

function misc_rules_end(): void
{
    global $mybb;
    global $newpoints_forum_id;

    $newpoints_forum_id = $mybb->get_input('fid', MyBB::INPUT_INT);
}

function error(string &$error_message): string
{
    global $newpoints_is_error_page;

    $newpoints_is_error_page = true;

    return $error_message;
}

function xmlhttp09(): void
{
    load_set_guest_data();

    global $newpoints_globals;
    global $newpoints_user_balance_formatted, $mypoints;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $newpoints_globals[$instance->users_column_get() . '_user_balance_formatted'] =
            $newpoints_user_balance_formatted = $mypoints =
                $instance->points_format($instance->get_user_column_value());
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

    my_alerts_initiate();
}

// Loads plugins from xmlhttp and runs a new hook called 'newpoints_xmlhttp' that can be used by NewPoints plugins (instead of xmlhttp)
// xmlhttp can't be used by NP plugins
// todo, fix plugins not being able to use xmlhttp by loading plugins before
function xmlhttp(): void
{
    run_hooks('xmlhttp');
}

// Loads plugins when in archive and runs a new hook called 'newpoints_archive_start' that can be used by NewPoints plugins (instead of archive_start)
// todo, fix plugins not being able to use archive_start by loading plugins before
function archive_start(): void
{
    load_set_guest_data();

    global $newpoints_globals;
    global $newpoints_user_balance_formatted, $mypoints;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $newpoints_globals[$instance->users_column_get() . '_user_balance_formatted'] =
            $newpoints_user_balance_formatted = $mypoints =
                $instance->points_format($instance->get_user_column_value());
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

    run_hooks('archive_start');
}

function postbit(array &$post): array
{
    global $mybb, $currency, $points, $lang;

    $post['newpoints_postbit'] = $points = $post['newpoints_balance_formatted'] = '';

    if (empty($post['uid'])) {
        return $post;
    }

    language_load();

    $replacements = [
        '<!--NEWPOINTS_POST_USER_POINTS-->' => &$post['newpoints_balance_formatted'],
    ];

    $url = new Url();

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $newpoints_amount = $post[$instance->users_column_get() . '_user_balance_formatted'] =
        $post['newpoints_balance_formatted'] = $points =
            $instance->points_format((float)$post[$instance->users_column_get()]);

        $replacements["<!--NewPoints_{$instance->users_column_get()}-->"] = $newpoints_amount;

        $newpoints_file = main_file_name();

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        $user_id = $uid = (int)$post['uid'];

        $post_id = (int)$post['pid'];

        $donate = '';

        if (
            $instance->user_permissions[Permissions::CanDonate] &&
            $user_id !== $instance->get_user_id()
        ) {
            $donate_url = $url->build(
                [
                    'action' => 'donate',
                    'uid' => $user_id,
                    'pid' => $post_id,
                    'modal' => 1,
                    'instance_id' => $instance_id
                ]
            );

            $donate = eval(templates_get('postbit_donate'));
        }

        $post['newpoints_postbit'] .= eval(templates_get('postbit'));
    }

    $post['user_details'] = str_replace(
        array_keys($replacements),
        array_values($replacements),
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

function member_profile_end(): void
{
    global $mybb, $currency, $points, $memprofile, $newpoints_profile, $lang, $uid;
    global $newpoints_profile_user_balance_formatted;

    $newpoints_profile = '';

    language_load();

    $url = new Url();

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $newpoints_amount = $memprofile[$instance->users_column_get() . '_user_balance_formatted'] =
        $newpoints_profile_user_balance_formatted = $points =
            $instance->points_format((float)$memprofile[$instance->users_column_get()]);

        $newpoints_file = main_file_name();

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        $user_id = $uid = (int)$memprofile['uid'];

        $donate = '';

        if ($instance->user_permissions[Permissions::CanDonate] &&
            $user_id !== $instance->get_user_id()) {
            $donate_url = $url->build(
                ['action' => 'donate', 'uid' => $user_id, 'modal' => 1, 'instance_id' => $instance_id]
            );

            $donate = eval(templates_get('profile_donate'));
        }

        $newpoints_profile .= eval(templates_get('profile'));
    }
}

// todo, I'm unsure how this is necessary if we already hook at the data handler
// removed in 3.1.5 because the data handler should take care of this already
function xmlhttp_edit_post_end(): void
{
}

/**
 * @throws Exception
 */
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

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id, $post_user_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $instance->charge_post()
                ->charge_post_characters($post_data['message']);
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $post_user_id,
                post_id: $post_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }

        if ($thread_user_id !== $post_user_id) {
            try {
                $instance = instance_object($instance_id, $thread_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->charge_thread_reply();
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $thread_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }
        }
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

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->charge_post()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    $instance = instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id);

                    if (!$instance->is_enabled()) {
                        continue;
                    }

                    $instance->charge_thread_reply();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $post_id,
                        thread_id: $thread_id,
                        forum_id: $forum_id,
                    );
                }
            }
        }
    }

    return $post_ids;
}

/**
 * @throws Exception
 */
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

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->income_post()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    $instance = instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id);

                    if (!$instance->is_enabled()) {
                        continue;
                    }

                    $instance->income_thread_reply();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $post_id,
                        thread_id: $thread_id,
                        forum_id: $forum_id,
                    );
                }
            }
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

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->income_thread()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }
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

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->income_post()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    $instance = instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id);

                    if (!$instance->is_enabled()) {
                        continue;
                    }

                    $instance->income_thread_reply();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $post_id,
                        thread_id: $thread_id,
                        forum_id: $forum_id,
                    );
                }
            }
        }
    }

    return $post_ids;
}

function class_moderation_unapprove_threads(array &$thread_ids): array
{
    foreach ($thread_ids as $thread_id) {
        $thread_id = (int)$thread_id;

        $thread_data = get_thread($thread_id);

        $post_data = get_post((int)$thread_data['firstpost']);

        $post_id = (int)$post_data['pid'];

        $forum_id = (int)$post_data['fid'];

        $post_user_id = (int)$post_data['uid'];

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->charge_thread()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }
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

        $thread_user_id = (int)$thread_data['uid'];

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->charge_post()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    $instance = instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id);

                    if (!$instance->is_enabled()) {
                        continue;
                    }

                    $instance->charge_thread_reply();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $post_id,
                        thread_id: $thread_id,
                        forum_id: $forum_id,
                    );
                }
            }
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

    $post_user_id = (int)$thread_data['uid'];

    // todo, this should use the replies field as other areas do
    $q = $db->simple_select(
        'posts',
        'COUNT(pid) as total_replies',
        "uid!='{$post_user_id}' AND tid='{$thread_id}'"
    );

    $thread_data['replies'] = (int)$db->fetch_field($q, 'total_replies');

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id, $post_user_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $instance->charge_thread()
                ->charge_post_characters($post_data['message'])
                ->charge_thread_reply($thread_data['replies'])
                ->charge_poll();
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $post_user_id,
                post_id: $post_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
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

        $thread_user_id = (int)$thread_data['uid'];

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            try {
                $instance = instance_object($instance_id, $thread_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->charge_thread_reply();
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $thread_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->charge_thread()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }
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

        foreach (cache_get_instances() as $instance_id => $instance_data) {
            if ($thread_user_id !== $post_user_id) {
                try {
                    $instance = instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id);

                    if (!$instance->is_enabled()) {
                        continue;
                    }

                    $instance->income_thread_reply();
                } catch (Exception $e) {
                    log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $post_id,
                        thread_id: $thread_id,
                        forum_id: $forum_id,
                    );
                }
            }

            try {
                $instance = instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id);

                if (!$instance->is_enabled()) {
                    continue;
                }

                $instance->income_thread()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $post_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }
        }
    }

    return $thread_ids;
}

function polls_do_newpoll_process(): void
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $thread_id = (int)$thread['tid'];

    $post_id = (int)$thread['firstpost'];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $instance->income_poll();
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $post_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }
}

function class_moderation_delete_poll(int &$post_id): int
{
    global $db;

    $query = $db->simple_select('polls', '*', "pid='{$post_id}'");

    $poll = $db->fetch_array($query);

    $forum_id = (int)$poll['fid'];

    $post_data = get_post($post_id);

    $thread_id = (int)$post_data['tid'];

    $post_user_id = (int)$post_data['uid'];

    if (!$thread_id ||
        !($thread_data = get_thread($thread_id)) ||
        empty($thread_data['poll'])) {
        return $post_id;
    }

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id, $post_user_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $instance->charge_poll();
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $post_user_id,
                post_id: $post_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }

    return $post_id;
}

function polls_vote_process(): void
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $thread_id = (int)$thread['tid'];

    $post_id = (int)$thread['firstpost'];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $instance->charge_poll_vote();
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $post_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }
}

function ratethread_process(): void
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $thread_id = (int)$thread['tid'];

    $post_id = (int)$thread['firstpost'];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $instance->income_thread_rating();
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $post_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }
}

function forumdisplay_start(): void
{
    global $mybb;

    forum_rule_view_lock($mybb->get_input('fid', MyBB::INPUT_INT));
}

function forumdisplay_end(): void
{
    global $lang;
    global $theme;
    global $header;
    global $fid;

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id)->set_forum((int)$fid);

            if (!$instance->is_enabled()) {
                continue;
            }
        } catch (Exception $e) {
            log_error(
                $instance_id,
                $e->getMessage(),
                post_id: (int)$fid,
            );

            continue;
        }

        language_load();

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        $user_group_rate_addition = $instance->get_user_permission_rate_addition();

        $user_group_rate_subtraction = $instance->get_user_permission_rate_substraction();

        $user_rate_description = $lang->sprintf(
            $lang->newpoints_home_user_rate_description,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
            $user_group_rate_addition,
            $user_group_rate_subtraction
        );

        $description_header = $lang->sprintf(
            $lang->newpoints_home_description_header,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
        );

        $income_settings = build_income_table($instance, 'forum');

        $header .= eval(templates_get('forum_income'));
    }
}

function showthread_start(): void
{
    global $forum;

    forum_rule_view_lock((int)$forum['fid']);
}

function editpost_start(): void
{
    global $mybb;

    $post_id = $mybb->get_input('pid', MyBB::INPUT_INT);

    $post_data = get_post($post_id);

    forum_rule_view_lock((int)$post_data['fid']);
}

function sendthread_do_sendtofriend_start(): void
{
    global $thread;

    forum_rule_view_lock((int)$thread['fid']);
}

function sendthread_start(): void
{
    sendthread_do_sendtofriend_start();
}

function archive_forum_start(): void
{
    global $forum;

    forum_rule_view_lock((int)$forum['fid']);
}

function archive_thread_start(): void
{
    archive_forum_start();
}

function printthread_end(): void
{
    global $thread;

    forum_rule_view_lock((int)$thread['fid']);
}

function newreply_start(): void
{
    global $fid;

    forum_rule_post_lock((int)$fid);
}

function newreply_do_newreply_start(): void
{
    newreply_start();
}

function newthread_start(): void
{
    newreply_start();
}

function newthread_do_newthread_start(): void
{
    newreply_start();
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

    $hook_arguments = [
        'user_activity' => &$user_activity,
    ];

    $hook_arguments = run_hooks('wol_fetch', $hook_arguments);

    return $user_activity;
}

function build_friendly_wol_location_end(array &$hook_arguments): array
{
    global $mybb, $lang;

    language_load();

    $url = new Url();

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $hook_arguments['instance_object'] = &$instance;

        if (my_strpos($hook_arguments['user_activity']['location'], main_file_name()) === false) {
            continue;
        }

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
                    $url->build(['action' => 'stats'])
                );
                break;
            case 'newpoints_donation':
                $hook_arguments['location_name'] = $lang->sprintf(
                    $lang->newpoints_wol_location_donation,
                    $mybb->settings['bburl'],
                    $url->build(['action' => 'donate'])
                );
                break;
            case 'newpoints_logs':
                $hook_arguments['location_name'] = $lang->sprintf(
                    $lang->newpoints_wol_location_logs,
                    $mybb->settings['bburl'],
                    $url->build(['action' => 'logs'])
                );
                break;
        }

        $hook_arguments = run_hooks('wol_format', $hook_arguments);

        break;
    }

    return $hook_arguments;
}

function memberlist_start(): void
{
    global $mybb;
    global $newpoints_member_list_sort;

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            if ($mybb->get_input('sort') === $instance->users_column_get()) {
                $newpoints_member_list_sort = $instance->users_column_get();

                break;
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }
}

function memberlist_intermediate(): void
{
    global $newpoints_member_list_sort;

    if (empty($newpoints_member_list_sort)) {
        return;
    }

    global $mybb;
    global $sort, $sort_field;

    $sort_field = 'u.' . $newpoints_member_list_sort;

    $sort = $mybb->input['sort'] = $newpoints_member_list_sort;
}

function memberlist_user(array &$user_data): array
{
    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if (!$instance->is_enabled()) {
                continue;
            }

            $user_data[$instance->users_column_get()] =
                (float)($user_data[$instance->users_column_get()] ?? 0);

            $user_data[$instance->users_column_get() . '_user_balance_formatted'] =
                $instance->points_format($user_data[$instance->users_column_get()]);
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

    return $user_data;
}

function myalerts_register_client_alert_formatters(): void
{
    if (!class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') ||
        !class_exists('MybbStuff_MyAlerts_AlertFormatterManager')) {
        return;
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
}

function myalerts_load_lang(): void
{
    $hook_arguments = [];

    language_load();

    $hook_arguments = run_hooks('my_alerts_language_load', $hook_arguments);
}