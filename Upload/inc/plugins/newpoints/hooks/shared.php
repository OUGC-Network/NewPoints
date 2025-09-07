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

use Exception;
use InvalidArgumentException;
use MyBB;
use PMDataHandler;
use postDatahandler;
use userDataHandler;

use function Newpoints\Core\cache_get_instances;
use function Newpoints\Core\count_characters;
use function Newpoints\Core\instance_get;
use function Newpoints\Core\instance_object;
use function Newpoints\Core\run_hooks;

use const Newpoints\Core\FIELDS_DATA;
use const Newpoints\Core\FORM_TYPE_CHECK_BOX;
use const Newpoints\Core\FORM_TYPE_CHECK_BOX_LEGACY;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD;
use const Newpoints\Core\FORM_TYPE_NUMERIC_FIELD_LEGACY;
use const Newpoints\Core\POST_VISIBLE_STATUS_VISIBLE;

function datahandler_post_insert_post_end(postDatahandler &$data_handler): postDatahandler
{
    $post_data = &$data_handler->data;

    if (!empty($post_data['savedraft']) || empty($post_data['uid'])) {
        return $data_handler;
    }

    if ((int)$data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $post_user_id = (int)$post_data['uid'];

    $forum_id = (int)$post_data['fid'];

    $thread_id = (int)$post_data['tid'];

    $post_id = (int)$data_handler->pid;

    $thread_data = get_thread($thread_id);

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

    $old_character_count = count_characters(get_post($post_data['pid'])['message']);

    $new_character_count = count_characters($post_data['message']);

    if ($old_character_count === $new_character_count) {
        return $data_handler;
    }

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id, $post_user_id)
                ->set_forum($forum_id)
                ->set_thread($thread_id)
                ->set_post($post_id);

            if ($old_character_count - $new_character_count < 0) {
                $instance->income_post_characters(characters_count: $new_character_count - $old_character_count);
            } elseif ($old_character_count - $new_character_count > 0) {
                $instance->charge_post_characters(characters_count: $new_character_count - $old_character_count);
            }
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

    return $data_handler;
}

function datahandler_post_insert_thread_end(postDatahandler &$data_handler): postDatahandler
{
    $post_data = &$data_handler->data;

    $post_user_id = (int)$post_data['uid'];

    if (!empty($post_data['savedraft']) || !$post_user_id) {
        return $data_handler;
    }

    if ($data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    $forum_id = (int)$post_data['fid'];

    $thread_id = (int)$data_handler->tid;

    $post_id = (int)$data_handler->pid;

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id, $post_user_id)
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

    return $data_handler;
}

function datahandler_pm_insert_end(PMDataHandler &$data_handler): PMDataHandler
{
    $user_id = (int)$data_handler->pm_insert_data['fromid'];

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            instance_object($instance_id, $user_id)
                ->set_primary_id((int)($data_handler->pmid[0] ?? 0))
                ->set_secondary_id((int)($data_handler->pmid[1] ?? 0))
                ->set_tertiary_id((int)($data_handler->pmid[2] ?? 0))
                ->income_private_message();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $user_id,
                primary_id: (int)($data_handler->pmid[0] ?? 0),
                secondary_id: (int)($data_handler->pmid[1] ?? 0),
                tertiary_id: (int)($data_handler->pmid[2] ?? 0),
            );
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
        $data_field_data['form_type'] = $data_field_data['form_type'] ?? ($data_field_data['form_type'] ?? null);

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
        $data_field_data['form_type'] = $data_field_data['form_type'] ?? ($data_field_data['form_type'] ?? null);

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

    $referrer_user_id = (int)($data_handler->user_insert_data['referrer'] ?? 0);

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        try {
            instance_object($instance_id, $user_id)
                ->income_registration();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $user_id,
            );
        }

        try {
            instance_object($instance_id, $referrer_user_id)
                ->set_primary_id($user_id)
                ->income_referral();
        } catch (Exception $e) {
            \Newpoints\Core\log_error(
                $instance_id,
                $e->getMessage(),
                user_id: $referrer_user_id,
                primary_id: $user_id,
            );
        }
    }

    return $data_handler;
}