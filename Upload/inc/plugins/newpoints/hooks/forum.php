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

namespace Newpoints\Hooks\Forum;

use InvalidArgumentException;
use MyBB;
use MybbStuff_MyAlerts_AlertFormatterManager;
use Exception;

use Newpoints\Core\Permissions;

use function Newpoints\Core\build_income_table;
use function Newpoints\Core\cache_get_instances;
use function Newpoints\Core\instance_get;
use function Newpoints\Core\instance_object;
use function Newpoints\Core\language_load;
use function Newpoints\Core\load_set_guest_data;
use function Newpoints\Core\log_error;
use function Newpoints\Core\my_alerts_initiate;
use function Newpoints\Core\templates_get;
use function Newpoints\Core\run_hooks;

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

    return true;
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
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $newpoints_globals[$instance->users_column_get() . '_user_balance_formatted'] =
        $newpoints_user_balance_formatted = $mypoints =
            $instance->points_format($instance->get_user_column_value());

        $newpoints_file = $instance->get_script_name();

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

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id)
                ->income_page_view()
                ->income_visit();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $forum_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
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

function xmlhttp09(): void
{
    load_set_guest_data();

    global $newpoints_globals;
    global $newpoints_user_balance_formatted, $mypoints;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

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

    global $newpoints_globals;
    global $newpoints_user_balance_formatted, $mypoints;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            $newpoints_globals[$instance->users_column_get() . '_user_balance_formatted'] =
            $newpoints_user_balance_formatted = $mypoints =
                $instance->points_format($instance->get_user_column_value());
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

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

    $replacements = [
        '<!--NEWPOINTS_POST_USER_POINTS-->' => &$post['newpoints_balance_formatted'],
    ];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $newpoints_amount = $post[$instance->users_column_get() . '_user_balance_formatted'] =
        $post['newpoints_balance_formatted'] = $points =
            $instance->points_format((float)$post[$instance->users_column_get()]);

        $replacements["<!--NewPoints_{$instance->users_column_get()}-->"] = $newpoints_amount;

        $newpoints_file = $instance->get_script_name();

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        $user_id = $uid = (int)$post['uid'];

        $post_id = (int)$post['pid'];

        $donate = '';

        if ($instance->user_permissions[Permissions::CanDonate] &&
            $user_id !== $instance->get_user_id()
        ) {
            $donate_url = $instance->url->build(
                ['action' => 'donate', 'uid' => $user_id, 'pid' => $post_id, 'modal' => 1]
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

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $newpoints_amount = $memprofile[$instance->users_column_get() . '_user_balance_formatted'] =
        $newpoints_profile_user_balance_formatted = $points =
            $instance->points_format((float)$memprofile[$instance->users_column_get()]);

        $newpoints_file = $instance->get_script_name();

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        $user_id = $uid = (int)$memprofile['uid'];

        $donate = '';

        if ($instance->user_permissions[Permissions::CanDonate] &&
            $user_id !== $instance->get_user_id()) {
            $donate_url = $instance->url->build(['action' => 'donate', 'uid' => $user_id, 'modal' => 1]);

            $donate = eval(templates_get('profile_donate'));
        }

        $newpoints_profile .= eval(templates_get('profile'));
    }
}

// todo, I'm unsure how this is necessary if we already hook at the data handler
// removed in 3.1.5 because the data handler should take care of this already
function xmlhttp_edit_post_end(): bool
{
    return false;
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
            instance_object($instance_id, $post_user_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id)
                ->charge_post()
                ->charge_post_characters($post_data['message']);
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $post_user_id,
                post_id: $forum_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }

        if ($thread_user_id !== $post_user_id) {
            try {
                instance_object($instance_id, $thread_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->charge_thread_reply();
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $thread_user_id,
                    post_id: $forum_id,
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
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->charge_post()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id)
                        ->charge_thread_reply();
                } catch (Exception $e) {
                    \Newpoints\Core\log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $forum_id,
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
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->income_post()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id)
                        ->income_thread_reply();
                } catch (Exception $e) {
                    \Newpoints\Core\log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $forum_id,
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
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->income_thread()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
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
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->income_post()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id)
                        ->income_thread_reply();
                } catch (Exception $e) {
                    \Newpoints\Core\log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $forum_id,
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
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->charge_thread()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
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
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->charge_post()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            if ($thread_user_id !== $post_user_id) {
                try {
                    instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id)
                        ->charge_thread_reply();
                } catch (Exception $e) {
                    \Newpoints\Core\log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $forum_id,
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
            instance_object($instance_id, $post_user_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id)
                ->charge_thread()
                ->charge_post_characters($post_data['message'])
                ->charge_thread_reply($thread_data['replies'])
                ->charge_poll();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $post_user_id,
                post_id: $forum_id,
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
                instance_object($instance_id, $thread_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->charge_thread_reply();
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $thread_user_id,
                    post_id: $forum_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }

            try {
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->charge_thread()
                    ->charge_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
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
                    instance_object($instance_id, $thread_user_id)
                        ->set_forum($forum_id)
                        ->set_thread($thread_id)
                        ->set_post($post_id)
                        ->income_thread_reply();
                } catch (Exception $e) {
                    \Newpoints\Core\log_error(
                        $instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        post_id: $forum_id,
                        thread_id: $thread_id,
                        forum_id: $forum_id,
                    );
                }
            }

            try {
                instance_object($instance_id, $post_user_id)
                    ->set_forum($forum_id)
                    ->set_thread($thread_id)
                    ->set_post($post_id)
                    ->income_thread()
                    ->income_post_characters($post_data['message']);
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $instance_id,
                    $e->getMessage(),
                    user_id: $post_user_id,
                    post_id: $forum_id,
                    thread_id: $thread_id,
                    forum_id: $forum_id,
                );
            }
        }
    }

    return $thread_ids;
}

function polls_do_newpoll_process(): bool
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $thread_id = (int)$thread['tid'];

    $post_id = (int)$thread['firstpost'];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id)
                ->income_poll();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $forum_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }

    return true;
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

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            instance_object($instance_id, $post_user_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id)
                ->charge_poll();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $post_user_id,
                post_id: $forum_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }

    return $post_id;
}

function polls_vote_process(): bool
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $thread_id = (int)$thread['tid'];

    $post_id = (int)$thread['firstpost'];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id)
                ->charge_poll_vote();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $forum_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }

    return true;
}

function ratethread_process(): void
{
    global $mybb, $fid, $thread;

    $forum_id = (int)$fid;

    $thread_id = (int)$thread['tid'];

    $post_id = (int)$thread['firstpost'];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            instance_object($instance_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id)
                ->income_thread_rating();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                post_id: $forum_id,
                thread_id: $thread_id,
                forum_id: $forum_id,
            );
        }
    }
}

function forumdisplay_start(): void
{
    global $mybb;

    _helper_evaluate_forum_view_lock($mybb->get_input('fid', MyBB::INPUT_INT));
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
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                post_id: (int)$fid,
            );

            continue;
        }

        language_load();

        $instance_name_upper = $instance->get_display_name_upper();

        $instance_name_lower = $instance->get_display_name_lower();

        $user_group_rate_addition = $instance->get_user_permissions_rate_addition();

        $user_group_rate_subtraction = $instance->get_user_permissions_rate_substraction();

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

function _helper_evaluate_forum_view_lock(int $forum_id): void
{
    $forum_data = get_forum($forum_id);

    $minimum_points = (float)$forum_data['newpoints_view_lock_points'];

    if (!($minimum_points > 0)) {
        return;
    }

    global $lang;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if ($minimum_points > $instance->get_user_column_value()) {
                language_load();

                \error(
                    $lang->sprintf(
                        $lang->newpoints_not_enough_points,
                        $instance->points_format($minimum_points)
                    )
                );
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }
}

function _helper_evaluate_forum_post_lock(int $forum_id): void
{
    $forum_data = get_forum($forum_id);

    $minimum_points = (float)$forum_data['newpoints_post_lock_points'];

    if (!($minimum_points > 0)) {
        return;
    }

    global $lang;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            if ($minimum_points > $instance->get_user_column_value()) {
                language_load();

                \error(
                    $lang->sprintf(
                        $lang->newpoints_not_enough_points,
                        $instance->points_format($minimum_points)
                    )
                );
            }
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }
}

function fetch_wol_activity_end(array &$user_activity): array
{
    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        if (my_strpos($user_activity['location'], $instance->get_script_name()) === false) {
            continue;
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
            'instance_object' => &$instance,
        ];

        $hook_arguments = run_hooks('wol_fetch', $hook_arguments);

        break;
    }

    return $user_activity;
}

function build_friendly_wol_location_end(array &$hook_arguments): array
{
    global $mybb, $lang;

    language_load();

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());

            continue;
        }

        $hook_arguments['instance_object'] = &$instance;

        if (my_strpos($hook_arguments['user_activity']['location'], $instance->get_script_name()) === false) {
            continue;
        }

        switch ($hook_arguments['user_activity']['activity']) {
            case 'newpoints_home':
                $hook_arguments['location_name'] = $lang->sprintf(
                    $lang->newpoints_wol_location_home,
                    $mybb->settings['bburl'],
                    $instance->get_script_name()
                );
                break;
            case 'newpoints_stats':
                $hook_arguments['location_name'] = $lang->sprintf(
                    $lang->newpoints_wol_location_stats,
                    $mybb->settings['bburl'],
                    $instance->url->build(['action' => 'stats'])
                );
                break;
            case 'newpoints_donation':
                $hook_arguments['location_name'] = $lang->sprintf(
                    $lang->newpoints_wol_location_donation,
                    $mybb->settings['bburl'],
                    $instance->url->build(['action' => 'donate'])
                );
                break;
            case 'newpoints_logs':
                $hook_arguments['location_name'] = $lang->sprintf(
                    $lang->newpoints_wol_location_logs,
                    $mybb->settings['bburl'],
                    $instance->url->build(['action' => 'logs'])
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

            if ($mybb->get_input('sort') === $instance->users_column_get()) {
                $newpoints_member_list_sort = $instance->users_column_get();

                break;
            }
        } catch (Exception $e) {
            \Newpoints\Core\log_error($instance_id, $e->getMessage());
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

function myalerts_register_client_alert_formatters(): bool
{
    if (!class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter') ||
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
    $hook_arguments = [];

    $hook_arguments = run_hooks('my_alerts_language_load', $hook_arguments);

    return '';
}