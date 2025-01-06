<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/hooks/shared.php)
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

namespace Newpoints\Hooks\Shared;

use MyBB;
use PMDataHandler;
use postDatahandler;
use userDataHandler;

use function Newpoints\Core\count_characters;
use function Newpoints\Core\get_income_value;
use function Newpoints\Core\log_add;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_subtract;
use function Newpoints\Core\rules_forum_get_rate;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\user_can_get_points;
use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\Core\FIELDS_DATA;
use const Newpoints\Core\FORM_TYPE_CHECK_BOX;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;
use const Newpoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const Newpoints\Core\INCOME_TYPE_USER_REFERRAL;
use const Newpoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;
use const Newpoints\Core\POST_VISIBLE_STATUS_VISIBLE;

function datahandler_post_insert_post_end(postDatahandler &$data_handler): postDatahandler
{
    $post = &$data_handler->data;

    if (!empty($post['savedraft']) || empty($post['message']) || empty($post['uid'])) {
        return $data_handler;
    }

    if ((int)$data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $post_user_id = (int)$post['uid'];

    $income_value = get_income_value(INCOME_TYPE_POST, $post_user_id);

    if (!$income_value) {
        return $data_handler;
    }

    $bonus_income = 0;

    $user_group_permissions = users_get_group_permissions($post_user_id);

    $income_value_bonus = get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);

    $character_count = count_characters($post['message']);

    if ($income_value_bonus && $character_count >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
        $bonus_income = $character_count * $income_value_bonus;
    }

    $forum_id = (int)$post['fid'];

    $thread_id = (int)$post['tid'];

    $post_id = (int)$data_handler->pid;

    $income_value = ($income_value + $bonus_income) * rules_forum_get_rate($forum_id);

    if ($income_value && user_can_get_points($post_user_id, $forum_id)) {
        points_add_simple(
            $post_user_id,
            $income_value
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

    $thread = get_thread($thread_id);

    $thread_user_id = (int)$thread['uid'];

    if (!user_can_get_points($thread_user_id, $forum_id)) {
        return $data_handler;
    }

    $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id);

    // we are the thread author so no points for new reply
    if (!$income_value || (int)$thread['uid'] === $post_user_id) {
        return $data_handler;
    }

    $income_value = $income_value * rules_forum_get_rate($forum_id);

    if ($income_value) {
        points_add_simple(
            $thread_user_id,
            $income_value
        );

        log_add(
            'income_' . INCOME_TYPE_THREAD_REPLY,
            '',
            get_user($thread_user_id)['username'] ?? '',
            $thread_user_id,
            $income_value,
            $thread_id,
            $post_id,
            $forum_id,
            LOGGING_TYPE_INCOME
        );
    }

    return $data_handler;
}

function datahandler_post_update_end(postDatahandler &$data_handler): postDatahandler
{
    $post = &$data_handler->data;

    if (!isset($post['message']) || empty($post['uid'])) {
        return $data_handler;
    }

    if ((int)$data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $post_user_id = (int)$post['uid'];

    $post_id = (int)$post['pid'];

    $thread_id = (int)$post['tid'];

    $income_value = get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id);

    if (!$income_value) {
        return $data_handler;
    }

    $old_character_count = count_characters(get_post($post['pid'])['message']);

    $new_character_count = count_characters($post['message']);

    if ($old_character_count === $new_character_count) {
        return $data_handler;
    }

    $bonus_income = ($new_character_count - $old_character_count) * $income_value;

    $forum_id = (int)$post['fid'];

    if (!user_can_get_points($post_user_id, $forum_id)) {
        return $data_handler;
    }

    $bonus_income = $bonus_income * rules_forum_get_rate($forum_id);

    if (!empty($bonus_income)) {
        if ($bonus_income > 0) {
            points_add_simple(
                $post_user_id,
                $bonus_income
            );
        } else {
            points_subtract(
                $post_user_id,
                $bonus_income
            );
        }

        log_add(
            'income_' . INCOME_TYPE_POST_CHARACTER,
            'income_post_update',
            '',
            get_user($post_user_id)['username'] ?? '',
            $post_user_id,
            $bonus_income,
            $thread_id,
            $post_id,
            $forum_id,
            $bonus_income > 0 ? LOGGING_TYPE_INCOME : LOGGING_TYPE_CHARGE
        );
    }

    return $data_handler;
}

function datahandler_post_insert_thread_end(postDatahandler &$data_handler): postDatahandler
{
    $thread = &$data_handler->data;

    $thread_user_id = (int)$thread['uid'];

    if (!empty($thread['savedraft']) || !$thread_user_id) {
        return $data_handler;
    }

    if ($data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $income_value = get_income_value(INCOME_TYPE_THREAD, $thread_user_id);

    $income_value_bonus = get_income_value(INCOME_TYPE_POST_CHARACTER, $thread_user_id);

    if (!$income_value) {
        return $data_handler;
    }

    $bonus_income = 0;

    $user_group_permissions = users_get_group_permissions($thread_user_id);

    $character_count = count_characters($thread['message']);

    if ($income_value_bonus && $character_count >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
        $bonus_income = $character_count * $income_value_bonus;
    }

    $forum_id = (int)$thread['fid'];

    if (!user_can_get_points($thread_user_id, $forum_id)) {
        return $data_handler;
    }

    $income_value = ($income_value + $bonus_income) * rules_forum_get_rate($forum_id);

    if ($income_value) {
        // give points to the author of the new thread
        points_add_simple(
            $thread_user_id,
            $income_value
        );

        log_add(
            'income_' . INCOME_TYPE_THREAD,
            '',
            get_user($thread_user_id)['username'] ?? '',
            $thread_user_id,
            $income_value,
            (int)$data_handler->tid,
            $forum_id,
            0,
            LOGGING_TYPE_INCOME
        );
    }

    return $data_handler;
}

function datahandler_pm_insert_end(PMDataHandler $data_handler): PMDataHandler
{
    $user_id = (int)$data_handler->pm_insert_data['fromid'];

    $income_value = get_income_value(INCOME_TYPE_PRIVATE_MESSAGE, $user_id);

    if (
        !empty($user_id) &&
        $income_value &&
        //!in_array($user_id, array_column($data_handler->data['recipients'], 'uid')) &&
        user_can_get_points($user_id)
    ) {
        points_add_simple($user_id, $income_value);

        log_add(
            'income_' . INCOME_TYPE_PRIVATE_MESSAGE,
            '',
            get_user($user_id)['username'] ?? '',
            $user_id,
            $income_value,
            (int)($data_handler->pmid[0] ?? 0),
            (int)($data_handler->pmid[1] ?? 0),
            (int)($data_handler->pmid[2] ?? 0),
            LOGGING_TYPE_INCOME
        );
    }

    return $data_handler;
}

function datahandler_user_validate(userDataHandler $data_handler): userDataHandler
{
    global $newpoints_user_update;

    if (empty($newpoints_user_update)) {
        return $data_handler;
    }

    global $mybb;

    $data_fields = FIELDS_DATA['users'];

    $hook_arguments = [
        'data_handler' => &$data_handler,
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('datahandler_user_validate', $hook_arguments);

    $user_data = &$data_handler->data;

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (!isset($data_field_data['formType'])) {
            continue;
        }

        switch ($data_field_data['formType']) {
            case FORM_TYPE_CHECK_BOX:
                $user_data[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_INT);
                break;
            case FORM_TYPE_NUMERIC_FIELD:
                if (!isset($mybb->input[$data_field_key])) {
                    break;
                }

                if (in_array($data_field_data['type'], ['DECIMAL', 'FLOAT'])) {
                    $user_data[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_FLOAT);
                } else {
                    $user_data[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_INT);
                }
        }
    }

    return $data_handler;
}

function datahandler_user_update(userDataHandler $data_handler): userDataHandler
{
    $data_fields = FIELDS_DATA['users'];

    $hook_arguments = [
        'data_handler' => &$data_handler,
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('datahandler_user_update', $hook_arguments);

    $user_data = &$data_handler->data;

    foreach ($data_fields as $data_field_key => $data_field_data) {
        if (!isset($data_field_data['formType']) || !isset($user_data[$data_field_key])) {
            continue;
        }

        $data_handler->user_update_data[$data_field_key] = $user_data[$data_field_key];
    }

    return $data_handler;
}

function datahandler_user_insert_end(userDataHandler $data_handler): userDataHandler
{
    $user_id = (int)$data_handler->uid;

    $income_value = get_income_value(INCOME_TYPE_USER_REGISTRATION, $user_id);

    if ($income_value && user_can_get_points($user_id)) {
        points_add_simple($user_id, $income_value);

        log_add(
            'income_' . INCOME_TYPE_USER_REGISTRATION,
            '',
            get_user($user_id)['username'] ?? '',
            $user_id,
            $income_value,
            0,
            0,
            0,
            LOGGING_TYPE_INCOME
        );
    }

    $referrer_user_id = (int)($data_handler->user_insert_data['referrer'] ?? 0);

    $income_value = get_income_value(INCOME_TYPE_USER_REFERRAL, $referrer_user_id);

    if ($income_value && user_can_get_points($referrer_user_id)) {
        points_add_simple($referrer_user_id, $income_value);

        log_add(
            'income_' . INCOME_TYPE_USER_REFERRAL,
            '',
            get_user($referrer_user_id)['username'] ?? '',
            $referrer_user_id,
            $income_value,
            $user_id,
            0,
            0,
            LOGGING_TYPE_INCOME
        );
    }

    return $data_handler;
}