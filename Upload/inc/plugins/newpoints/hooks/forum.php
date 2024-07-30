<?php

/***************************************************************************
 *
 *   Newpoints plugin (/inc/plugins/newpoints/hooks/forum.php)
 *   Author: Pirata Nervo
 *   Copyright: © 2009 Pirata Nervo
 *   Copyright: © 2024 Omar Gonzalez
 *
 *   Website: https://ougc.network
 *
 *   NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
 *
 ***************************************************************************/

/****************************************************************************
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

use postDatahandler;

use function Newpoints\Core\count_characters;
use function Newpoints\Core\plugins_load;
use function Newpoints\Core\points_add;
use function Newpoints\Core\points_format;
use function Newpoints\Core\rules_get;
use function Newpoints\Core\rules_get_all;
use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\templates_get;
use function Newpoints\Core\run_hooks;

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

    global $plugins, $mybb, $mypoints;

    plugins_load();

    //newpoints_load_settings();

    if ($mybb->user['uid'] > 0) {
        $mypoints = points_format((float)$mybb->user['newpoints']);
    } else {
        $mypoints = 0;
    }

    // as plugins can't hook to global_start, we must allow them to hook to global_start
    run_hooks('global_start');

    return true;
}

function global_end(): bool
{
    global $db, $mybb, $cache, $groupscache, $userupdates;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    // check group rules - primary group check
    $grouprules = rules_get_all('group');
    if (empty($grouprules)) {
        return false;
    }

    if ($mybb->settings['newpoints_income_pageview'] != 0) {
        points_add(
            $mybb->user['uid'],
            $mybb->settings['newpoints_income_pageview'],
            1,
            $grouprules[$mybb->user['usergroup']]['rate']
        );
    }

    if ($mybb->settings['newpoints_income_visit'] != 0) {
        if ((constant('TIME_NOW') - $mybb->user['lastactive']) > 900) {
            points_add(
                $mybb->user['uid'],
                $mybb->settings['newpoints_income_visit'],
                1,
                $grouprules[$mybb->user['usergroup']]['rate']
            );
        }
    }

    foreach ($grouprules as $gid => $rule) {
        if ($rule['pointsearn'] == 0 || $rule['period'] == 0 || $rule['lastpay'] > (constant(
                    'TIME_NOW'
                ) - $rule['period'])) {
            continue;
        }

        $amount = floatval($rule['pointsearn']);

        $userupdates[$gid] = $amount;
        // update rule with last payment
        $db->update_query(
            'newpoints_grouprules',
            ['lastpay' => (int)constant('TIME_NOW')],
            'rid=\'' . (int)$rule['rid'] . '\''
        );

        // Re-cache rules (lastpay must be updated)
        rules_rebuild_cache();

        if ($mybb->user['usergroup'] == $gid) {
            $mybb->user['newpoints'] += $amount;
        }

        if (!empty($userupdates)) {
            // run updates to users on shut down
            add_shutdown('newpoints_update_users');
        }
    }

    return true;
}

function global_intermediate(): bool
{
    global $mybb;
    global $newpoints_user_balance_formatted;

    if (!isset($mybb->user['newpoints'])) {
        $mybb->user['newpoints'] = 0;
    } else {
        $mybb->user['newpoints'] = (float)$mybb->user['newpoints'];
    }

    $newpoints_user_balance_formatted = points_format($mybb->user['newpoints']);

    return true;
}

// Loads plugins from xmlhttp and runs a new hook called 'newpoints_xmlhttp' that can be used by NewPoints plugins (instead of xmlhttp)
// xmlhttp can't be used by NP plugins
// todo, fix plugins not being able to use xmlhttp by loading plugins before
function xmlhttp(): bool
{
    global $plugins;

    global_intermediate();

    plugins_load();
    //newpoints_load_settings();

    // as plugins can't hook to xmlhttp, we must allow them to hook to newpoints_xmlhttp
    run_hooks('xmlhttp');

    return true;
}

// Loads plugins when in archive and runs a new hook called 'newpoints_archive_start' that can be used by NewPoints plugins (instead of archive_start)
// todo, fix plugins not being able to use archive_start by loading plugins before
function archive_start()
{
    global $plugins;

    plugins_load();
    //newpoints_load_settings();

    // as plugins can't hook to archive_start, we must allow them to hook to newpoints_archive_start
    run_hooks('archive_start');
}

function postbit(array &$post): array
{
    global $mybb, $db, $currency, $points, $templates, $donate, $lang, $uid;

    if ($post['uid'] == 0) {
        $post['newpoints_postbit'] = '';

        return $post;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        $post['newpoints_postbit'] = '';

        return $post;
    }

    $lang->load('newpoints');

    $currency = $mybb->settings['newpoints_main_curname'];

    $points = $post['newpointsPostUserBalanceFormatted'] = points_format($post['newpoints']);

    $uid = intval($post['uid']);

    if ($mybb->settings['newpoints_main_donationsenabled'] && $post['uid'] != $mybb->user['uid'] && $mybb->user['uid'] > 0) {
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        $newpoints_profile = '';

        return false;
    }

    global $newpoints_profile_user_balance_formatted;

    $lang->load('newpoints');

    $currency = $mybb->settings['newpoints_main_curname'];

    $points = $newpoints_profile_user_balance_formatted = points_format($memprofile['newpoints']);

    $uid = intval($memprofile['uid']);

    if ($mybb->settings['newpoints_main_donationsenabled'] && $memprofile['uid'] != $mybb->user['uid'] && $mybb->user['uid'] > 0) {
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

    if ($mybb->input['action'] != 'do_newreply' || $post['savedraft']) {
        return $data;
    }

    if ($data->post_insert_data['visible'] != 1) {
        // If it's not visible, then we may have moderation (drafts are already considered above so it doesn't matter here)

        return $data;
    }

    if (!$mybb->user['uid']) {
        return $data;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $data;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $data;
    }

    // check forum rules
    $forumrules = rules_get('forum', $data->post_insert_data['fid']);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return $data;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
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

    // give points to the poster
    points_add(
        $mybb->user['uid'],
        $mybb->settings['newpoints_income_newpost'] + $bonus,
        $forumrules['rate'],
        $grouprules['rate']
    );

    if ($thread['uid'] != $mybb->user['uid']) {
        // we are not the thread started so give points to him/her
        if ($mybb->settings['newpoints_income_perreply'] != 0) {
            points_add(
                $thread['uid'],
                $mybb->settings['newpoints_income_perreply'],
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $newpost;
    }

    if ($mybb->settings['newpoints_income_perchar'] == 0) {
        return $newpost;
    }

    if ($mybb->input['action'] != 'do_editpost' || $mybb->input['editdraft']) {
        return $newpost;
    }

    $fid = intval($newpost->data['fid']);

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return $newpost;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
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

    // give points to the poster
    points_add($mybb->user['uid'], $bonus, $forumrules['rate'], $grouprules['rate'], false, true);

    return $newpost;
}

// edit post - counts less chars on edit because of \n\r being deleted
function xmlhttp10(): bool
{
    global $db, $mybb, $thread, $lang, $charset;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    if ($mybb->settings['newpoints_income_perchar'] == 0) {
        return false;
    }

    if ($mybb->input['action'] != 'edit_post') {
        return false;
    } elseif ($mybb->input['action'] == 'edit_post' && $mybb->input['do'] != 'update_post') {
        return false;
    }

    if ($mybb->input['editdraft']) {
        return false;
    }

    // Verify POST request
    if (!verify_post_check($mybb->input['my_post_key'], true)) {
        xmlhttp_error($lang->invalid_post_code);
    }

    $post = get_post($mybb->input['pid']);

    $fid = intval($post['fid']);

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return false;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
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

    // give points to the poster
    points_add($mybb->user['uid'], $bonus, $forumrules['rate'], $grouprules['rate'], false, true);

    return true;
}

function class_moderation_delete_post_start(int $pid): int
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $pid;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
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

    $thread = get_thread($post['tid']);

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be removed so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return $pid;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be removed so let's just leave the function
    if ($grouprules['rate'] == 0) {
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
            points_add(
                $thread['uid'],
                -$mybb->settings['newpoints_income_perreply'],
                $forumrules['rate'],
                $grouprules['rate']
            );
        }
    }

    // remove points from the poster
    points_add(
        $post['uid'],
        -$mybb->settings['newpoints_income_newpost'] - $bonus,
        $forumrules['rate'],
        $grouprules['rate']
    );

    return $pid;
}

function class_moderation_soft_delete_posts(array $pids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $pids;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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
                    points_add(
                        $thread['uid'],
                        -$mybb->settings['newpoints_income_perreply'],
                        $forumrules['rate'],
                        $grouprules['rate']
                    );
                }
            }

            // remove points from the poster
            points_add(
                $post['uid'],
                -$mybb->settings['newpoints_income_newpost'] - $bonus,
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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
                    points_add(
                        $thread['uid'],
                        $mybb->settings['newpoints_income_perreply'],
                        $forumrules['rate'],
                        $grouprules['rate']
                    );
                }
            }

            // give points to the author of the post
            points_add(
                $post['uid'],
                $mybb->settings['newpoints_income_newpost'] + $bonus,
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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

            // add points to the poster
            points_add(
                $post['uid'],
                $mybb->settings['newpoints_income_newthread'] + $bonus,
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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
                    points_add(
                        $thread['uid'],
                        $mybb->settings['newpoints_income_perreply'],
                        $forumrules['rate'],
                        $grouprules['rate']
                    );
                }
            }

            // give points to the author of the post
            points_add(
                $post['uid'],
                $mybb->settings['newpoints_income_newpost'] + $bonus,
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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

            // add points to the poster
            points_add(
                $post['uid'],
                -$mybb->settings['newpoints_income_newthread'] - $bonus,
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $pids;
    }

    if ($mybb->settings['newpoints_income_newpost'] == 0) {
        return $pids;
    }

    if (!empty($pids)) {
        foreach ($pids as $pid) {
            $post = get_post((int)$pid);
            $thread = get_thread($post['tid']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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
                    points_add(
                        $thread['uid'],
                        -$mybb->settings['newpoints_income_perreply'],
                        $forumrules['rate'],
                        $grouprules['rate']
                    );
                }
            }

            // give points to the author of the post
            points_add(
                $post['uid'],
                -$mybb->settings['newpoints_income_newpost'] - $bonus,
                $forumrules['rate'],
                $grouprules['rate']
            );
        }
    }

    return $pids;
}

function datahandler_post_insert_thread(postDatahandler &$that): postDatahandler
{
    global $db, $mybb, $fid, $thread;

    if ($mybb->input['action'] != 'do_newthread' || $mybb->input['savedraft']) {
        return $that;
    }

    if ($that->thread_insert_data['visible'] != 1) {
        // If it's not visible, then we may have moderation (drafts are already considered above so it doesn't matter here)
        return $that;
    }

    if (!$mybb->user['uid']) {
        return $that;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $that;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $that;
    }

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return $that;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
        return $that;
    }

    // calculate points per character bonus
    // let's see if the number of characters in the thread is greater than the minimum characters
    if (($charcount = count_characters(
            $mybb->input['message']
        )) >= $mybb->settings['newpoints_income_minchar']) {
        $bonus = $charcount * $mybb->settings['newpoints_income_perchar'];
    } else {
        $bonus = 0;
    }

    // give points to the author of the new thread
    points_add(
        $mybb->user['uid'],
        $mybb->settings['newpoints_income_newthread'] + $bonus,
        $forumrules['rate'],
        $grouprules['rate']
    );

    return $that;
}

function class_moderation_delete_thread(int $tid): int
{
    global $db, $mybb;

    if (!$mybb->user['uid']) {
        return $tid;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $tid;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tid;
    }

    // even though the thread was deleted it was previously cached so we can use get_thread
    $thread = get_thread((int)$tid);
    $fid = $thread['fid'];

    // It's currently soft deleted, so we do nothing as we already subtracted points when doing that
    // If it's not visible (unapproved) we also don't take out any money
    if ($thread['visible'] == -1 || $thread['visible'] == 0) {
        return $tid;
    }

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be removed so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return $tid;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be removed so let's just leave the function
    if ($grouprules['rate'] == 0) {
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

    if ($thread['poll'] != 0) {
        // if this thread has a poll, remove points from the author of the thread
        points_add(
            $thread['uid'],
            -$mybb->settings['newpoints_income_newpoll'],
            $forumrules['rate'],
            $grouprules['rate']
        );
    }

    $q = $db->simple_select(
        'posts',
        'COUNT(*) as total_replies',
        'uid!=' . (int)$thread['uid'] . ' AND tid=' . (int)$thread['tid']
    );
    $thread['replies'] = (int)$db->fetch_field($q, 'total_replies');
    points_add(
        $thread['uid'],
        -($thread['replies'] * $mybb->settings['newpoints_income_perreply']),
        $forumrules['rate'],
        $grouprules['rate']
    );

    // take out points from the author of the thread
    points_add(
        $thread['uid'],
        -$mybb->settings['newpoints_income_newthread'] - $bonus,
        $forumrules['rate'],
        $grouprules['rate']
    );

    return $tid;
}

function class_moderation_soft_delete_threads(array $tids): array
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return $tids;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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
                    points_add(
                        $thread['uid'],
                        -$mybb->settings['newpoints_income_perreply'],
                        $forumrules['rate'],
                        $grouprules['rate']
                    );
                }
            }

            // remove points from the poster
            points_add(
                $post['uid'],
                -$mybb->settings['newpoints_income_newthread'] - $bonus,
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $tids;
    }

    if ($mybb->settings['newpoints_income_newthread'] == 0) {
        return $tids;
    }

    if (!empty($tids)) {
        foreach ($tids as $tid) {
            $thread = get_thread($tid);
            $post = get_post((int)$thread['firstpost']);

            // check forum rules
            $forumrules = rules_get('forum', $fid);
            if (!$forumrules) {
                $forumrules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the forum rate is 0, nothing is going to be removed so let's just leave the function
            if ($forumrules['rate'] == 0) {
                continue;
            }

            // check group rules - primary group check
            $grouprules = rules_get('group', $mybb->user['usergroup']);
            if (!$grouprules) {
                $grouprules['rate'] = 1;
            } // no rule set so default income rate is 1

            // if the group rate is 0, nothing is going to be removed so let's just leave the function
            if ($grouprules['rate'] == 0) {
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
                    points_add(
                        $thread['uid'],
                        $mybb->settings['newpoints_income_perreply'],
                        $forumrules['rate'],
                        $grouprules['rate']
                    );
                }
            }

            // give points to the author of the post
            points_add(
                $post['uid'],
                $mybb->settings['newpoints_income_newthread'] + $bonus,
                $forumrules['rate'],
                $grouprules['rate']
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

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    if ($mybb->settings['newpoints_income_newpoll'] == 0) {
        return false;
    }

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return false;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
        return false;
    }

    // give points to the author of the new poll
    points_add(
        $mybb->user['uid'],
        $mybb->settings['newpoints_income_newpoll'],
        $forumrules['rate'],
        $grouprules['rate']
    );

    return true;
}

function class_moderation_delete_poll(int $pid): int
{
    global $db, $mybb;

    if (!$mybb->user['uid']) {
        return $pid;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return $pid;
    }

    if ($mybb->settings['newpoints_income_newpoll'] == 0) {
        return $pid;
    }

    $query = $db->simple_select('polls', '*', "pid = '{$pid}'");
    $poll = $db->fetch_array($query);

    $fid = $poll['fid'];

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return $pid;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
        return $pid;
    }

    // remove points from the author by deleting the poll
    points_add(
        $poll['uid'],
        -$mybb->settings['newpoints_income_newpoll'],
        $forumrules['rate'],
        $grouprules['rate']
    );

    return $pid;
}

function member_do_register_end(): bool
{
    global $db, $mybb, $user_info;

    // give points to our new user
    if ($mybb->settings['newpoints_income_newreg'] != 0) {
        points_add(
            trim($mybb->input['username']),
            $mybb->settings['newpoints_income_newreg'],
            1,
            1,
            true
        );
    }

    if ($mybb->settings['newpoints_income_referral'] != 0) {
        // Grab the referred user's points
        $query = $db->simple_select(
            'users',
            'uid,newpoints',
            'username=\'' . my_strtolower($db->escape_string(trim($mybb->input['referrername']))) . '\''
        );
        $user = $db->fetch_array($query);
        if (empty($user)) {
            return false;
        }

        // check group rules - primary group check
        $grouprules = rules_get('group', $mybb->user['usergroup']);
        if (!$grouprules) {
            $grouprules['rate'] = 1;
        } // no rule set so default income rate is 1

        // if the group rate is 0, nothing is going to be added so let's just leave the function
        if ($grouprules['rate'] == 0) {
            return false;
        }

        points_add($user['uid'], $mybb->settings['newpoints_income_referral'], 1, $grouprules['rate']);
    }

    return true;
}

function polls_vote_process(): bool
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    if ($mybb->settings['newpoints_income_pervote'] == 0) {
        return false;
    }

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return false;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
        return false;
    }

    // give points to us as we're voting in a poll
    points_add(
        $mybb->user['uid'],
        $mybb->settings['newpoints_income_pervote'],
        $forumrules['rate'],
        $grouprules['rate']
    );

    return true;
}

function private_do_send_end(): bool
{
    global $pmhandler, $pminfo, $db, $mybb;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
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

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
        return false;
    }

    // give points to the author of the PM
    points_add($mybb->user['uid'], $mybb->settings['newpoints_income_pmsent'], 1, $grouprules['rate']);

    return true;
}

function ratethread_process(): bool
{
    global $db, $mybb, $fid;

    if (!$mybb->user['uid']) {
        return false;
    }

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    if ($mybb->settings['newpoints_income_perrate'] == 0) {
        return false;
    }

    // check forum rules
    $forumrules = rules_get('forum', $fid);
    if (!$forumrules) {
        $forumrules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the forum rate is 0, nothing is going to be added so let's just leave the function
    if ($forumrules['rate'] == 0) {
        return false;
    }

    // check group rules - primary group check
    $grouprules = rules_get('group', $mybb->user['usergroup']);
    if (!$grouprules) {
        $grouprules['rate'] = 1;
    } // no rule set so default income rate is 1

    // if the group rate is 0, nothing is going to be added so let's just leave the function
    if ($grouprules['rate'] == 0) {
        return false;
    }

    // give points us, as we're rating a thread
    points_add(
        $mybb->user['uid'],
        $mybb->settings['newpoints_income_perrate'],
        $forumrules['rate'],
        $grouprules['rate']
    );

    return true;
}

function forumdisplay_end(): bool
{
    global $mybb, $lang, $fid;

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    if (THIS_SCRIPT == 'forumdisplay.php') {
        $fid = intval($mybb->input['fid']);
    }

    $forumrules = rules_get('forum', $fid);
    if ($forumrules['pointsview'] > $mybb->user['newpoints']) {
        $lang->load('newpoints');
        error(
            $lang->sprintf($lang->newpoints_not_enough_points, points_format($forumrules['pointsview']))
        );
    }

    return true;
}

function showthread_start(): bool
{
    return forumdisplay_end();
}

function editpost_start(): bool
{
    global $mybb, $lang;

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    $pid = intval($mybb->input['pid']);
    $post = get_post($pid);
    if (!$post) {
        return false;
    }

    $fid = $post['fid'];

    $forumrules = rules_get('forum', $fid);
    if ($forumrules['pointsview'] > $mybb->user['newpoints']) {
        $lang->load('newpoints');
        error(
            $lang->sprintf($lang->newpoints_not_enough_points, points_format($forumrules['pointsview']))
        );
    }

    return true;
}

function sendthread_do_sendtofriend_start(): bool
{
    global $mybb, $lang, $fid;

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    $forumrules = rules_get('forum', $fid);
    if ($forumrules['pointsview'] > $mybb->user['newpoints']) {
        $lang->load('newpoints');
        error(
            $lang->sprintf($lang->newpoints_not_enough_points, points_format($forumrules['pointsview']))
        );
    }

    return true;
}

function sendthread_start(): bool
{
    return sendthread_do_sendtofriend_start();
}

function archive_forum_start(): bool
{
    global $mybb, $lang, $forum;

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    $fid = intval($forum['fid']);

    $forumrules = rules_get('forum', $fid);
    if ($forumrules['pointsview'] > $mybb->user['newpoints']) {
        $lang->load('newpoints');
        error(
            $lang->sprintf($lang->newpoints_not_enough_points, points_format($forumrules['pointsview']))
        );
    }

    return true;
}

function archive_thread_start(): bool
{
    return archive_forum_start();
}

function printthread_end(): bool
{
    global $mybb, $lang, $fid;

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    $forumrules = rules_get('forum', $fid);
    if ($forumrules['pointsview'] > $mybb->user['newpoints']) {
        $lang->load('newpoints');
        error(
            $lang->sprintf($lang->newpoints_not_enough_points, points_format($forumrules['pointsview']))
        );
    }

    return true;
}

function newreply_start(): bool
{
    global $mybb, $lang, $fid;

    if ($mybb->settings['newpoints_main_enabled'] != 1) {
        return false;
    }

    $forumrules = rules_get('forum', $fid);
    if ($forumrules['pointspost'] > $mybb->user['newpoints']) {
        $lang->load('newpoints');
        error(
            $lang->sprintf($lang->newpoints_not_enough_points, points_format($forumrules['pointspost']))
        );
    }

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