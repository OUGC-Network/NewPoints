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

namespace Newpoints\Hooks\Shared;

use MyBB;
use PMDataHandler;
use postDatahandler;
use userDataHandler;
use Exception;

use function Newpoints\Core\count_characters;
use function Newpoints\Core\get_income_value;
use function Newpoints\Core\instance_object;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_subtract;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\user_can_get_points;
use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\Core\FIELDS_DATA;
use const Newpoints\Core\FORM_TYPE_CHECK_BOX;
use const Newpoints\Core\FORM_TYPE_CHECK_BOX_LEGACY;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD_LEGACY;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;
use const Newpoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const Newpoints\Core\INCOME_TYPE_USER_REFERRAL;
use const Newpoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const Newpoints\Core\INSTANCE_DEFAULT_ID;
use const Newpoints\Core\POST_VISIBLE_STATUS_VISIBLE;

function datahandler_post_insert_post_end(postDatahandler &$data_handler): postDatahandler
{
    $post_data = &$data_handler->data;

    if (!empty($post_data['savedraft']) || empty($post_data['message']) || empty($post_data['uid'])) {
        return $data_handler;
    }

    if ((int)$data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $post_user_id = (int)$post_data['uid'];

    $forum_id = (int)$post_data['fid'];

    $income_bonus = 0;

    $user_group_permissions = users_get_group_permissions($post_user_id);

    $characters_count = count_characters($post_data['message']);

    if ($characters_count >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
        $income_bonus = $characters_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id, $forum_id);
    }

    $thread_id = (int)$post_data['tid'];

    $post_id = (int)$data_handler->pid;

    $post_user_group_permissions = users_get_group_permissions($post_user_id);

    if (user_can_get_points($post_user_id, $forum_id)) {
        $income_bonus *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_bonus) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_POST_CHARACTER,
                    $post_user_id,
                    $income_bonus,
                    $post_id,
                    $thread_id,
                    $forum_id
                );

                points_add_simple(
                    $post_user_id,
                    $income_bonus
                );
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    if (user_can_get_points($post_user_id, $forum_id)) {
        $income_value = get_income_value(INCOME_TYPE_POST, $post_user_id, $forum_id);

        $income_value *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_POST,
                    $post_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                );

                points_add_simple(
                    $post_user_id,
                    $income_value
                );
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    $thread_data = get_thread($thread_id);

    $thread_user_id = (int)$thread_data['uid'];

    if ($thread_user_id !== $post_user_id && user_can_get_points($thread_user_id, $forum_id)) {
        $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id, $forum_id);

        $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

        $income_value *= $thread_user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_THREAD_REPLY,
                    $thread_user_id,
                    $income_value,
                    $post_id,
                    $thread_id,
                    $forum_id,
                );

                points_add_simple(
                    $thread_user_id,
                    $income_value
                );
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    return $data_handler;
}

function datahandler_post_update_end(postDatahandler &$data_handler): postDatahandler
{
    $post_data = &$data_handler->data;

    if (!isset($post_data['message']) || empty($post_data['uid'])) {
        return $data_handler;
    }

    if ((int)$data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $post_user_id = (int)$post_data['uid'];

    $post_id = (int)$post_data['pid'];

    $thread_id = (int)$post_data['tid'];

    $forum_id = (int)$post_data['fid'];

    $income_bonus = get_income_value(INCOME_TYPE_POST_CHARACTER, $post_user_id, $forum_id);

    $old_character_count = count_characters(get_post($post_data['pid'])['message']);

    $new_character_count = count_characters($post_data['message']);

    if ($old_character_count === $new_character_count) {
        return $data_handler;
    }

    $income_bonus = ($new_character_count - $old_character_count) * $income_bonus;

    $post_user_group_permissions = users_get_group_permissions($post_user_id);

    if ($income_bonus > 0 && user_can_get_points($post_user_id, $forum_id)) {
        $income_bonus *= $post_user_group_permissions['newpoints_rate_addition'];

        if ($income_bonus) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_POST_CHARACTER,
                    $post_user_id,
                    $income_bonus,
                    $post_id,
                    $thread_id,
                    $forum_id,
                );

                points_add_simple(
                    $post_user_id,
                    $income_bonus
                );
            } catch (Exception $e) {
                // Handle exception
            }
        }
    } elseif ($income_bonus < 0 && user_can_get_points($post_user_id, $forum_id)) {
        $income_bonus *= ($post_user_group_permissions['newpoints_rate_subtraction'] / 100);

        if ($income_bonus) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_charge(
                    'income_' . INCOME_TYPE_POST_CHARACTER,
                    $post_user_id,
                    $income_bonus,
                    $thread_id,
                    $post_id,
                    $forum_id,
                );

                points_subtract(
                    $post_user_id,
                    $income_bonus
                );
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    return $data_handler;
}

function datahandler_post_insert_thread_end(postDatahandler &$data_handler): postDatahandler
{
    $thread_data = &$data_handler->data;

    $thread_user_id = (int)$thread_data['uid'];

    if (!empty($thread_data['savedraft']) || !$thread_user_id) {
        return $data_handler;
    }

    if ($data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $forum_id = (int)$thread_data['fid'];

    $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

    if (user_can_get_points($thread_user_id, $forum_id)) {
        $income_bonus = 0;

        $user_group_permissions = users_get_group_permissions($thread_user_id);

        $characters_count = count_characters($thread_data['message']);

        if ($characters_count >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
            $income_bonus = $characters_count * get_income_value(
                    INCOME_TYPE_POST_CHARACTER,
                    $thread_user_id,
                    $forum_id
                );
        }

        $income_bonus *= $thread_user_group_permissions['newpoints_rate_addition'];

        if ($income_bonus) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_POST_CHARACTER,
                    $thread_user_id,
                    $income_bonus,
                    0,
                    (int)$data_handler->tid,
                    $forum_id,
                );

                points_add_simple(
                    $thread_user_id,
                    $income_bonus
                );
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    if (user_can_get_points($thread_user_id, $forum_id)) {
        $income_value = get_income_value(INCOME_TYPE_THREAD, $thread_user_id, $forum_id);

        $income_value *= $thread_user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_THREAD,
                    $thread_user_id,
                    $income_value,
                    0,
                    (int)$data_handler->tid,
                    $forum_id,
                );

                points_add_simple(
                    $thread_user_id,
                    $income_value
                );
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    return $data_handler;
}

function datahandler_pm_insert_end(PMDataHandler &$data_handler): PMDataHandler
{
    $user_id = (int)$data_handler->pm_insert_data['fromid'];

    if (user_can_get_points($user_id)) {
        $income_value = get_income_value(INCOME_TYPE_PRIVATE_MESSAGE, $user_id);

        $user_group_permissions = users_get_group_permissions($user_id);

        $income_value *= $user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_PRIVATE_MESSAGE,
                    $user_id,
                    $income_value,
                    (int)($data_handler->pmid[0] ?? 0),
                    (int)($data_handler->pmid[1] ?? 0),
                    (int)($data_handler->pmid[2] ?? 0),
                );

                points_add_simple($user_id, $income_value);
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    return $data_handler;
}

// todo, use this for Quick Edit plugin
function datahandler_user_validate(userDataHandler &$data_handler): userDataHandler
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
        $data_field_data['form_type'] = $data_field_data['form_type'] ?? ($data_field_data['formType'] ?? null);

        if (empty($data_field_data['form_type'])) {
            continue;
        }

        switch ($data_field_data['form_type']) {
            case FORM_TYPE_CHECK_BOX:
            case FORM_TYPE_CHECK_BOX_LEGACY:
                $user_data[$data_field_key] = $mybb->get_input($data_field_key, MyBB::INPUT_INT);
                break;
            case FORM_TYPE_NUMERIC_FIELD:
            case FORM_TYPE_NUMERIC_FIELD_LEGACY:
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

function datahandler_user_update(userDataHandler &$data_handler): userDataHandler
{
    $data_fields = FIELDS_DATA['users'];

    $hook_arguments = [
        'data_handler' => &$data_handler,
        'data_fields' => &$data_fields,
    ];

    $hook_arguments = run_hooks('datahandler_user_update', $hook_arguments);

    $user_data = &$data_handler->data;

    global $mybb, $db;

    foreach ($data_fields as $data_field_key => $data_field_data) {
        $data_field_data['form_type'] = $data_field_data['form_type'] ?? ($data_field_data['formType'] ?? null);

        if (empty($data_field_data['form_type']) ||
            (!isset($user_data[$data_field_key]) && !isset($mybb->input[$data_field_key]))) {
            continue;
        }

        if (in_array($data_field_data['type'], ['INT', 'SMALLINT', 'TINYINT'])) {
            $data_handler->user_update_data[$data_field_key] = (int)($user_data[$data_field_key] ?? $mybb->input[$data_field_key]);
        } elseif (in_array($data_field_data['type'], ['FLOAT', 'DECIMAL'])) {
            $data_handler->user_update_data[$data_field_key] = (float)($user_data[$data_field_key] ?? $mybb->input[$data_field_key]);
        } else {
            $data_handler->user_update_data[$data_field_key] = $db->escape_string(
                $user_data[$data_field_key] ?? $mybb->input[$data_field_key]
            );
        }
    }

    return $data_handler;
}

function datahandler_user_insert_end(userDataHandler &$data_handler): userDataHandler
{
    $user_id = (int)$data_handler->uid;

    $user_group_permissions = users_get_group_permissions($user_id);

    if (user_can_get_points($user_id)) {
        $income_value = get_income_value(INCOME_TYPE_USER_REGISTRATION, $user_id);

        $income_value *= $user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_USER_REGISTRATION,
                    $user_id,
                    $income_value
                );

                points_add_simple($user_id, $income_value);
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    $referrer_user_id = (int)($data_handler->user_insert_data['referrer'] ?? 0);

    if (user_can_get_points($referrer_user_id)) {
        $income_value = get_income_value(INCOME_TYPE_USER_REFERRAL, $referrer_user_id);

        $referrer_user_group_permissions = users_get_group_permissions($referrer_user_id);

        $income_value *= $referrer_user_group_permissions['newpoints_rate_addition'];

        if ($income_value) {
            try {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                    'income_' . INCOME_TYPE_USER_REFERRAL,
                    $referrer_user_id,
                    $income_value,
                    $user_id,
                );

                points_add_simple($referrer_user_id, $income_value);
            } catch (Exception $e) {
                // Handle exception
            }
        }
    }

    return $data_handler;
}