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
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\user_can_get_points;
use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\Core\FIELDS_DATA;
use const Newpoints\Core\FORM_TYPE_CHECK_BOX;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\POST_VISIBLE_STATUS_VISIBLE;
use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;

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

    if (!($income_setting_new_post = get_income_value(INCOME_TYPE_POST))) {
        return $data_handler;
    }

    $bonus_income = 0;

    $user_group_permissions = users_get_group_permissions($post_user_id);

    if (($character_count = count_characters(
            $post['message']
        )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
        $bonus_income = $character_count * get_income_value(INCOME_TYPE_POST_CHARACTER);
    }

    $forum_id = (int)$post['fid'];

    if (user_can_get_points($post_user_id, $forum_id)) {
        points_add_simple(
            $post_user_id,
            $income_setting_new_post + $bonus_income,
            $forum_id
        );
    }

    $thread = get_thread($post['tid']);

    $thread_user_id = (int)$thread['uid'];

    if (!user_can_get_points($thread_user_id, $forum_id)) {
        return $data_handler;
    }

    // we are the thread author so no points for new reply
    if ((int)$thread['uid'] === $post_user_id || !get_income_value(INCOME_TYPE_THREAD_REPLY)) {
        return $data_handler;
    }

    points_add_simple(
        $thread_user_id,
        get_income_value(INCOME_TYPE_THREAD_REPLY),
        $forum_id
    );

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

    if (!get_income_value(INCOME_TYPE_POST_CHARACTER)) {
        return $data_handler;
    }

    $old_character_count = count_characters(get_post($post['pid'])['message']);

    $new_character_count = count_characters($post['message']);

    if ($old_character_count === $new_character_count) {
        return $data_handler;
    }

    $bonus_income = ($new_character_count - $old_character_count) * get_income_value(INCOME_TYPE_POST_CHARACTER);

    $post_user_id = (int)$post['uid'];

    $forum_id = (int)$post['fid'];

    if (!user_can_get_points($post_user_id, $forum_id)) {
        return $data_handler;
    }

    if (!empty($bonus_income)) {
        points_add_simple(
            $post_user_id,
            $bonus_income,
            $forum_id
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

    if (!get_income_value(INCOME_TYPE_THREAD)) {
        return $data_handler;
    }

    $bonus_income = 0;

    $user_group_permissions = users_get_group_permissions($thread_user_id);

    if (($character_count = count_characters(
            $thread['message']
        )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
        $bonus_income = $character_count * get_income_value(INCOME_TYPE_POST_CHARACTER);
    }

    $forum_id = (int)$thread['fid'];

    if (!user_can_get_points($thread_user_id, $forum_id)) {
        return $data_handler;
    }

    // give points to the author of the new thread
    points_add_simple(
        $thread_user_id,
        get_income_value(INCOME_TYPE_THREAD) + $bonus_income,
        $forum_id
    );

    return $data_handler;
}

function datahandler_pm_insert_end(PMDataHandler $data_handler): PMDataHandler
{
    if (
        !empty($data_handler->pm_insert_data['fromid']) &&
        get_income_value(INCOME_TYPE_PRIVATE_MESSAGE) &&
        !in_array($data_handler->pm_insert_data['fromid'], array_column($data_handler->data['recipients'], 'uid'))
    ) {
        $user_id = (int)$data_handler->pm_insert_data['fromid'];

        if (!user_can_get_points($user_id)) {
            return $data_handler;
        }

        points_add_simple($user_id, get_income_value(INCOME_TYPE_PRIVATE_MESSAGE));
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