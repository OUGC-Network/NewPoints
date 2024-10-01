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
use postDatahandler;

use function Newpoints\Core\count_characters;
use function Newpoints\Core\language_load;
use function Newpoints\Core\load_set_guest_data;
use function Newpoints\Core\points_add;
use function Newpoints\Core\points_format;
use function Newpoints\Core\rules_forum_get_rate;
use function Newpoints\Core\rules_forum_get;
use function Newpoints\Core\rules_get_all;
use function Newpoints\Core\rules_get_group_rate;
use function Newpoints\Core\rules_group_get;
use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\templates_get;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\users_update;

// Loads plugins from global_start and runs a new hook called 'newpoints_global_start' that can be used by NewPoints plugins (instead of global_start)
// global_start can't be used by NP plugins
// todo, fix plugins not being able to use global_start by loading plugins before
function global_start(): bool
{
    if (THIS_SCRIPT == 'showthread.php') {
        global $templatelist;
        if (isset($templatelist)) {
            $templatelist .= ',';
        }
        $templatelist .= 'newpoints_postbit,newpoints_donate_inline';
    } elseif (THIS_SCRIPT == 'member.php') {
        global $templatelist;
        if (isset($templatelist)) {
            $templatelist .= ',';
        }
        $templatelist .= 'newpoints_profile,newpoints_donate_inline';
    }

    load_set_guest_data();

    // as plugins can't hook to global_start, we must allow them to hook to global_start
    run_hooks('global_start');

    //users_update();
    return true;
}

function global_end(): bool
{
    global $db, $mybb, $cache, $groupscache, $userupdates;

    if (!$mybb->user['uid']) {
        return false;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return false;
    }

    $user_id = (int)$mybb->user['uid'];

    if ($mybb->settings['newpoints_income_pageview'] != 0) {
        points_add(
            $user_id,
            (float)$mybb->settings['newpoints_income_pageview'],
            1,
            $group_rate
        );
    }

    if ($mybb->settings['newpoints_income_visit'] != 0) {
        if ((TIME_NOW - $mybb->user['lastactive']) > 900) {
            points_add(
                $user_id,
                (float)$mybb->settings['newpoints_income_visit'],
                1,
                $group_rate
            );
        }
    }

    return true;
}

function global_intermediate(): bool
{
    load_set_guest_data();

    return true;
}

// Loads plugins from xmlhttp and runs a new hook called 'newpoints_xmlhttp' that can be used by NewPoints plugins (instead of xmlhttp)
// xmlhttp can't be used by NP plugins
// todo, fix plugins not being able to use xmlhttp by loading plugins before
function xmlhttp(): bool
{
    load_set_guest_data();

    run_hooks('xmlhttp');

    return true;
}

// Loads plugins when in archive and runs a new hook called 'newpoints_archive_start' that can be used by NewPoints plugins (instead of archive_start)
// todo, fix plugins not being able to use archive_start by loading plugins before
function archive_start()
{
    load_set_guest_data();

    run_hooks('archive_start');
}

function postbit(array &$post): array
{
    global $mybb, $db, $currency, $points, $templates, $donate, $lang, $uid;

    $post['newpoints_postbit'] = $points = $post['newpoints_balance_formatted'] = '';

    if (empty($post['uid'])) {
        return $post;
    }

    language_load();

    $currency = $mybb->settings['newpoints_main_curname'];

    $points = $post['newpoints_balance_formatted'] = points_format((float)$post['newpoints']);

    $uid = intval($post['uid']);

    if (!empty($mybb->usergroup['newpoints_can_donate']) && $post['uid'] != $mybb->user['uid'] && $mybb->user['uid'] > 0) {
        $donate = eval(templates_get('donate_inline'));
    } else {
        $donate = '';
    }

    $post['newpoints_postbit'] = eval(templates_get('postbit'));

    return $post;
}

function postbit_prev(array &$post): array
{
    return postbit($post);
}

function postbit_pm(array &$post): array
{
    return postbit($post);
}

function postbit_announcement(array &$post): array
{
    return postbit($post);
}

function member_profile_end(): bool
{
    global $mybb, $db, $currency, $points, $templates, $memprofile, $newpoints_profile, $lang, $uid;

    $newpoints_profile = '';

    global $newpoints_profile_user_balance_formatted;

    language_load();

    $currency = $mybb->settings['newpoints_main_curname'];

    $points = $newpoints_profile_user_balance_formatted = points_format((float)$memprofile['newpoints']);

    $uid = intval($memprofile['uid']);

    if (!empty($mybb->usergroup['newpoints_can_donate']) && $memprofile['uid'] != $mybb->user['uid'] && $mybb->user['uid'] > 0) {
        $donate = eval(templates_get('donate_inline'));
    } else {
        $donate = '';
    }

    $newpoints_profile = eval(templates_get('profile'));

    return true;
}

function datahandler_post_insert_post(postDatahandler &$data): postDatahandler
{
    global $db, $mybb, $post, $thread;

    if ($mybb->get_input('action') != 'do_newreply' || $post['savedraft']) {
        return $data;
    }

    if ($data->post_insert_data['visible'] != 1) {
        // If it's not visible, then we may have moderation (drafts are already considered above so it doesn't matter here)

        return $data;
    }

    if (!$mybb->user['uid']) {
        return $data;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $data;
    }

    $forum_id = (int)$data->post_insert_data['fid'];

    $forum_rate = rules_forum_get_rate($forum_id);

    if (!$forum_rate) {
        return $data;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return $data;
    }

    // calculate points per character bonus
    // let's see if the number of characters in the post is greater than the minimum characters
    if (($charcount = count_characters(
            $post['message']
        )) >= $mybb->settings['newpoints_income_minchar']) {
        $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
    } else {
        $bonus = 0;
    }

    $user_id = (int)$mybb->user['uid'];

    // give points to the poster
    points_add(
        $user_id,
        (float)$mybb->settings['newpoints_income_newpost'] + (float)$bonus,
        $forum_rate,
        $group_rate
    );

    if ($thread['uid'] != $mybb->user['uid']) {
        // we are not the thread started so give points to him/her
        if ($mybb->settings['newpoints_income_perreply'] != 0) {
            $thread_user_id = (int)$thread['uid'];

            points_add(
                $thread_user_id,
                (float)$mybb->settings['newpoints_income_perreply'],
                $forum_rate,
                $group_rate
            );
        }
    }

    return $data;
}

function datahandler_post_update(postDatahandler &$newpost): postDatahandler
{
    global $db, $mybb, $thread;

    if (!$mybb->user['uid']) {
        return $newpost;
    }

    if ($mybb->settings['newpoints_income_perchar'] == 0) {
        return $newpost;
    }

    if ($mybb->get_input('action') != 'do_editpost' || $mybb->get_input('editdraft')) {
        return $newpost;
    }

    $fid = (int)$newpost->data['fid'];

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return $newpost;
    }

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return $newpost;
    }

    // check group rules - primary group check

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return $newpost;
    }

    // get old message
    $post = get_post(intval($newpost->data['pid']));
    $oldcharcount = count_characters($post['message']);
    $newcharcount = count_characters($newpost->data['message']);

    // calculate points per character bonus
    // let's see if the number of characters in the post is greater than the minimum characters
    if ($newcharcount >= $mybb->settings['newpoints_income_minchar']) {
        // if we have more characters now
        if ($newcharcount > $oldcharcount) {
            // calculate bonus based on difference of characters
            // bonus will be positive as the new message is longer than the old one
            $bonus = ($newcharcount - $oldcharcount) * $mybb->settings['newpoints_income_perchar'];
        } // otherwise if the message is shorter
        elseif ($newcharcount < $oldcharcount) {
            // calculate bonus based on difference of characters
            // bonus will be negative as the new message is shorter than the old one
            $bonus = ($newcharcount - $oldcharcount) * $mybb->settings['newpoints_income_perchar'];
        } // else if the length is the same, the bonus is 0
        elseif ($newcharcount == $oldcharcount) {
            $bonus = 0;
        }
    } elseif ($newcharcount >= $mybb->settings['newpoints_income_minchar'] && $oldcharcount >= $mybb->settings['newpoints_income_minchar']) {
        // calculate bonus based on difference of characters
        // bonus will be negative as the new message is shorter than the minimum chars
        $bonus = ($newcharcount - $oldcharcount) * $mybb->settings['newpoints_income_perchar'];
    }

    if (isset($bonus)) // give points to the poster
    {
        $user_id = (int)$mybb->user['uid'];

        points_add(
            $user_id,
            (float)$bonus,
            $forum_rate,
            $group_rate,
            false,
            true
        );
    }

    return $newpost;
}

// edit post - counts less chars on edit because of \n\r being deleted
function xmlhttp10(): bool
{
    global $db, $mybb, $thread, $lang, $charset;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_income_perchar'] == 0) {
        return false;
    }

    if ($mybb->get_input('action') != 'edit_post') {
        return false;
    } elseif ($mybb->get_input('action') == 'edit_post' && $mybb->get_input('do') != 'update_post') {
        return false;
    }

    if ($mybb->get_input('editdraft')) {
        return false;
    }

    // Verify POST request
    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        xmlhttp_error($lang->invalid_post_code);
    }

    $post = get_post($mybb->get_input('pid', MyBB::INPUT_INT));

    $fid = (int)$post['fid'];

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return false;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return false;
    }

    // get old message
    $oldcharcount = count_characters($post['message']);

    $message = strval($_POST['value']);
    if (my_strtolower($charset) != 'utf-8') {
        if (function_exists('iconv')) {
            $message = iconv($charset, 'UTF-8//IGNORE', $message);
        } elseif (function_exists('mb_convert_encoding')) {
            $message = @mb_convert_encoding($message, $charset, 'UTF-8');
        } elseif (my_strtolower($charset) == 'iso-8859-1') {
            $message = utf8_decode($message);
        }
    }

    $newcharcount = count_characters($message);

    // calculate points per character bonus
    // let's see if the number of characters in the post is greater than the minimum characters
    if ($newcharcount >= $mybb->settings['newpoints_income_minchar']) {
        // if we have more characters now
        if ($newcharcount > $oldcharcount) {
            // calculate bonus based on difference of characters
            // bonus will be positive as the new message is longer than the old one
            $bonus = ($newcharcount - $oldcharcount) * $mybb->settings['newpoints_income_perchar'];
        } // otherwise if the message is shorter
        elseif ($newcharcount < $oldcharcount) {
            // calculate bonus based on difference of characters
            // bonus will be positive as the new message is longer than the old one
            $bonus = ($newcharcount - $oldcharcount) * $mybb->settings['newpoints_income_perchar'];
        } // else if the length is the same, the bonus is 0
        elseif ($newcharcount == $oldcharcount) {
            $bonus = 0;
        }
    } else {
        // calculate bonus based on difference of characters
        // bonus will be negative as the new message is shorter than the minimum chars
        $bonus = ($newcharcount - $oldcharcount) * $mybb->settings['newpoints_income_perchar'];
    }

    if (isset($bonus)) // give points to the poster
    {
        $user_id = (int)$mybb->user['uid'];

        points_add($user_id, (float)$bonus, $forum_rate, $group_rate, false, true);
    }

    return true;
}

function class_moderation_delete_post_start(int $pid): int
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $pid;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pid;
    }

    $post = get_post((int)$pid);
    // It's currently soft deleted, so we do nothing as we already subtracted points when doing that
    // If it's not visible (unapproved) we also don't take out any money
    if ($post['visible'] == -1 || $post['visible'] == 0) {
        return $pid;
    }

    $fid = (int)$fid;

    $thread = get_thread($post['tid']);

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return $pid;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return $pid;
    }

    // calculate points per character bonus
    // let's see if the number of characters in the post is greater than the minimum characters
    if (($charcount = count_characters(
            $post['message']
        )) >= $mybb->settings['newpoints_income_minchar']) {
        $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
    } else {
        $bonus = 0;
    }

    if ($thread['uid'] != $post['uid']) {
        // we are not the thread started so remove points from him/her
        if ($mybb->settings['newpoints_income_perreply'] != 0) {
            $thread_user_id = (int)$thread['uid'];

            points_add(
                $thread_user_id,
                -(float)$mybb->settings['newpoints_income_perreply'],
                $forum_rate,
                $group_rate
            );
        }
    }

    $post_user_id = (int)$post['uid'];

    // remove points from the poster
    points_add(
        $post_user_id,
        -(float)$mybb->settings['newpoints_income_newpost'] - (float)$bonus,
        $forum_rate,
        $group_rate
    );

    return $pid;
}

function class_moderation_soft_delete_posts(array $pids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so remove points from him/her
                if ($mybb->settings['newpoints_income_perreply'] != 0) {
                    $thread_user_id = (int)$thread['uid'];

                    points_add(
                        $thread_user_id,
                        -(float)$mybb->settings['newpoints_income_perreply'],
                        $forum_rate,
                        $group_rate
                    );
                }
            }

            $post_user_id = (int)$post['uid'];

            // remove points from the poster
            points_add(
                $post_user_id,
                -(float)$mybb->settings['newpoints_income_newpost'] - (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $pids;
}

function class_moderation_restore_posts($pids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so give points to them
                if ($mybb->settings['newpoints_income_perreply'] != 0) {
                    $thread_user_id = (int)$thread['uid'];

                    points_add(
                        $thread_user_id,
                        (float)$mybb->settings['newpoints_income_perreply'],
                        $forum_rate,
                        $group_rate
                    );
                }
            }

            $post_user_id = (int)$post['uid'];

            // give points to the author of the post
            points_add(
                $post_user_id,
                (float)$mybb->settings['newpoints_income_newpost'] + (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $pids;
}

function class_moderation_approve_threads(array $tids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            $post_user_id = (int)$post['uid'];

            // add points to the poster
            points_add(
                $post_user_id,
                (float)$mybb->settings['newpoints_income_newthread'] + (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $tids;
}

function class_moderation_approve_posts(array $pids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so give points to them
                if ($mybb->settings['newpoints_income_perreply'] != 0) {
                    $thread_user_id = (int)$thread['uid'];

                    points_add(
                        $thread_user_id,
                        (float)$mybb->settings['newpoints_income_perreply'],
                        $forum_rate,
                        $group_rate
                    );
                }
            }

            $post_user_id = (int)$post['uid'];

            // give points to the author of the post
            points_add(
                $post_user_id,
                (float)$mybb->settings['newpoints_income_newpost'] + (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $pids;
}

function class_moderation_unapprove_threads(array $tids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            $post_user_id = (int)$post['uid'];

            // add points to the poster
            points_add(
                $post_user_id,
                -(float)$mybb->settings['newpoints_income_newthread'] - (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $tids;
}

function class_moderation_unapprove_posts(array $pids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    $fid = (int)$fid;

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so remove points from them
                if ($mybb->settings['newpoints_income_perreply'] != 0) {
                    $thread_user_id = (int)$thread['uid'];

                    points_add(
                        $thread_user_id,
                        -(float)$mybb->settings['newpoints_income_perreply'],
                        $forum_rate,
                        $group_rate
                    );
                }
            }

            $post_user_id = (int)$post['uid'];

            // give points to the author of the post
            points_add(
                $post_user_id,
                -(float)$mybb->settings['newpoints_income_newpost'] - (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $pids;
}

function datahandler_post_insert_thread(postDatahandler &$that): postDatahandler
{
    global $db, $mybb, $fid, $thread;

    if ($mybb->get_input('action') != 'do_newthread' || $mybb->get_input('savedraft')) {
        return $that;
    }

    if ($that->thread_insert_data['visible'] != 1) {
        // If it's not visible, then we may have moderation(drafts are already considered above so it doesn't matter here)
        return $that;
    }

    if (!$mybb->user['uid']) {
        return $that;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $that;
    }

    $fid = (int)$fid;

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return $that;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return $that;
    }

    // calculate points per character bonus
    // let's see if the number of characters in the thread is greater than the minimum characters
    if (($charcount = count_characters(
            $mybb->get_input('message')
        )) >= $mybb->settings['newpoints_income_minchar']) {
        $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
    } else {
        $bonus = 0;
    }

    $user_id = (int)$mybb->user['uid'];

    // give points to the author of the new thread
    points_add(
        $user_id,
        (float)$mybb->settings['newpoints_income_newthread'] + (float)$bonus,
        $forum_rate,
        $group_rate
    );

    return $that;
}

function class_moderation_delete_thread(int $tid): int
{
    global $db, $mybb;

    if (!$mybb->user['uid']) {
        return $tid;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tid;
    }

    // even though the thread was deleted it was previously cached so we can use get_thread
    $thread = get_thread($tid);
    $fid = (int)$thread['fid'];

    // It's currently soft deleted, so we do nothing as we already subtracted points when doing that
    // If it's not visible (unapproved) we also don't take out any money
    if ($thread['visible'] == -1 || $thread['visible'] == 0) {
        return $tid;
    }

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return $tid;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return $tid;
    }

    // get post of the thread
    $post = get_post($thread['firstpost']);

    // calculate points per character bonus
    // let's see if the number of characters in the thread is greater than the minimum characters
    if (($charcount = count_characters(
            $post['message']
        )) >= $mybb->settings['newpoints_income_minchar']) {
        $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
    } else {
        $bonus = 0;
    }

    $thread_user_id = (int)$thread['uid'];

    if ($thread['poll'] != 0) {
        // if this thread has a poll, remove points from the author of the thread

        points_add(
            $thread_user_id,
            -(float)$mybb->settings['newpoints_income_newpoll'],
            $forum_rate,
            $group_rate
        );
    }

    $q = $db->simple_select(
        'posts',
        'COUNT(*) as total_replies',
        'uid!=' . (int)$thread['uid'] . ' AND tid=' . (int)$thread['tid']
    );
    $thread['replies'] = (int)$db->fetch_field($q, 'total_replies');

    points_add(
        $thread_user_id,
        -(float)($thread['replies'] * $mybb->settings['newpoints_income_perreply']),
        $forum_rate,
        $group_rate
    );

    // take out points from the author of the thread
    points_add(
        $thread_user_id,
        -(float)$mybb->settings['newpoints_income_newthread'] - (float)$bonus,
        $forum_rate,
        $group_rate
    );

    return $tid;
}

function class_moderation_soft_delete_threads(array $tids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so remove points from him/her
                if ($mybb->settings['newpoints_income_perreply'] != 0) {
                    $thread_user_id = (int)$thread['uid'];

                    points_add(
                        $thread_user_id,
                        -(float)$mybb->settings['newpoints_income_perreply'],
                        $forum_rate,
                        $group_rate
                    );
                }
            }

            $post_user_id = (int)$post['uid'];

            // remove points from the poster
            points_add(
                $post_user_id,
                -(float)$mybb->settings['newpoints_income_newthread'] - (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $tids;
}

function class_moderation_restore_threads(array $tids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    $fid = (int)$fid;

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            $forum_rate = rules_forum_get_rate($fid);

            if (!$forum_rate) {
                continue;
            }

            $group_rate = rules_get_group_rate();

            if (!$group_rate) {
                continue;
            }

            // calculate points per character bonus
            // let's see if the number of characters in the post is greater than the minimum characters
            if (($charcount = count_characters(
                    $post['message']
                )) >= $mybb->settings['newpoints_income_minchar']) {
                $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
            } else {
                $bonus = 0;
            }

            // the post author != thread author?
            if ($thread['uid'] != $post['uid']) {
                // we are not the thread started so give points to them
                if ($mybb->settings['newpoints_income_perreply'] != 0) {
                    $thread_user_id = (int)$thread['uid'];

                    points_add(
                        $thread_user_id,
                        (float)$mybb->settings['newpoints_income_perreply'],
                        $forum_rate,
                        $group_rate
                    );
                }
            }

            $post_user_id = (int)$post['uid'];

            // give points to the author of the post
            points_add(
                $post_user_id,
                (float)$mybb->settings['newpoints_income_newthread'] + (float)$bonus,
                $forum_rate,
                $group_rate
            );
        }
    }

    return $tids;
}

function polls_do_newpoll_process(): bool
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_income_newpoll'] == 0) {
        return false;
    }

    $fid = (int)$fid;

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return false;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return false;
    }

    $user_id = (int)$mybb->user['uid'];

    // give points to the author of the new polls
    points_add(
        $user_id,
        (float)$mybb->settings['newpoints_income_newpoll'],
        $forum_rate,
        $group_rate
    );

    return true;
}

function class_moderation_delete_poll(int $pid): int
{
    global $db, $mybb;

    if (!$mybb->user['uid']) {
        return $pid;
    }

    if ($mybb->settings['newpoints_income_newpoll'] == 0) {
        return $pid;
    }

    $query = $db->simple_select('polls', '*', "pid='{$pid}'");
    $poll = $db->fetch_array($query);

    $fid = (int)$poll['fid'];

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return $pid;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return $pid;
    }

    $poll_user_id = (int)$poll['uid'];

    // remove points from the author by deleting the poll
    points_add(
        $poll_user_id,
        -(float)$mybb->settings['newpoints_income_newpoll'],
        $forum_rate,
        $group_rate
    );

    return $pid;
}

function member_do_register_end(): bool
{
    global $db, $mybb, $user_info;

    // give points to our new user
    if ($mybb->settings['newpoints_income_newreg'] != 0) {
        $user_id = (int)$user_info['uid'];

        points_add(
            $user_id,
            (float)$mybb->settings['newpoints_income_newreg']
        );
    }

    if ($mybb->settings['newpoints_income_referral'] != 0) {
        // Grab the referred user's points
        $query = $db->simple_select(
            'users',
            'uid,newpoints',
            'username=\'' . my_strtolower($db->escape_string(trim($mybb->get_input('referrername')))) . '\''
        );
        $user = $db->fetch_array($query);
        if (empty($user)) {
            return false;
        }

        $group_rate = rules_get_group_rate();

        if (!$group_rate) {
            return false;
        }

        $user_id = (int)$user['uid'];

        points_add($user_id, (float)$mybb->settings['newpoints_income_referral'], 1, $group_rate);
    }

    return true;
}

function polls_vote_process(): bool
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_income_pervote'] == 0) {
        return false;
    }

    $fid = (int)$fid;

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return false;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return false;
    }

    $user_id = (int)$mybb->user['uid'];

    // give points to us as we're voting in a poll
    points_add(
        $user_id,
        (float)$mybb->settings['newpoints_income_pervote'],
        $forum_rate,
        $group_rate
    );

    return true;
}

function private_do_send_end(): bool
{
    global $pmhandler, $pminfo, $db, $mybb;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_income_pmsent'] == 0) {
        return false;
    }

    if (isset($pminfo['draftsaved'])) {
        return false;
    }

    if ($mybb->user['uid'] == $pmhandler->data['toid']) {
        return false;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return false;
    }

    $user_id = (int)$mybb->user['uid'];

    // give points to the author of the PM
    points_add($user_id, (float)$mybb->settings['newpoints_income_pmsent'], 1, $group_rate);

    return true;
}

function ratethread_process(): bool
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_income_perrate'] == 0) {
        return false;
    }

    $fid = (int)$fid;

    $forum_rate = rules_forum_get_rate($fid);

    if (!$forum_rate) {
        return false;
    }

    $group_rate = rules_get_group_rate();

    if (!$group_rate) {
        return false;
    }

    $user_id = (int)$mybb->user['uid'];

    // give points us, as we're rating a thread
    points_add(
        $user_id,
        (float)$mybb->settings['newpoints_income_perrate'],
        $forum_rate,
        $group_rate
    );

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

function _helper_evaluate_forum_view_lock(int $forum_ID): bool
{
    $forum_data = get_forum($forum_ID);

    if (empty($forum_data['newpoints_view_lock_points'])) {
        return false;
    }

    global $mybb, $lang;

    if ($forum_data['newpoints_view_lock_points'] > $mybb->user['newpoints']) {
        language_load();

        error(
            $lang->sprintf(
                $lang->newpoints_not_enough_points,
                points_format((float)$forum_data['newpoints_view_lock_points'])
            )
        );
    }

    return true;
}

function _helper_evaluate_forum_post_lock(int $forum_ID): bool
{
    $forum_data = get_forum($forum_ID);

    if (empty($forum_data['newpoints_post_lock_points'])) {
        return false;
    }

    global $mybb, $lang;

    if ($forum_data['newpoints_post_lock_points'] > $mybb->user['newpoints']) {
        language_load();

        error(
            $lang->sprintf(
                $lang->newpoints_not_enough_points,
                points_format((float)$forum_data['newpoints_post_lock_points'])
            )
        );
    }

    return true;
}