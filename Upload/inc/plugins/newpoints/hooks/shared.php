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

use postDatahandler;

use function Newpoints\Core\count_characters;
use function Newpoints\Core\get_income_value;
use function Newpoints\Core\points_add_simple;

use const Newpoints\Core\INCOME_TYPE_POST_PER_REPLY;
use const Newpoints\Core\INCOME_TYPE_THREAD_NEW;
use const Newpoints\Core\POST_VISIBLE_STATUS_VISIBLE;
use const Newpoints\Core\INCOME_TYPE_POST_NEW;
use const Newpoints\Core\INCOME_TYPE_POST_MINIMUM_CHARACTERS;
use const Newpoints\Core\INCOME_TYPE_POST_PER_CHARACTER;

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

    if (!($income_setting_new_post = get_income_value(INCOME_TYPE_POST_NEW))) {
        return $data_handler;
    }

    $forum_id = (int)$post['fid'];

    $bonus_income = 0;

    if (($character_count = count_characters($post['message'])) >= get_income_value(
            INCOME_TYPE_POST_MINIMUM_CHARACTERS
        )) {
        $bonus_income = $character_count * get_income_value(INCOME_TYPE_POST_PER_CHARACTER);
    }

    points_add_simple(
        $post_user_id,
        $income_setting_new_post + $bonus_income,
        $forum_id
    );

    $thread = get_thread($post['tid']);

    // we are the thread author so no points for new reply
    if ((int)$thread['uid'] === $post_user_id || !get_income_value(INCOME_TYPE_POST_PER_REPLY)) {
        return $data_handler;
    }

    points_add_simple(
        (int)$thread['uid'],
        get_income_value(INCOME_TYPE_POST_PER_REPLY),
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

    if (!get_income_value(INCOME_TYPE_POST_PER_CHARACTER)) {
        return $data_handler;
    }

    $old_character_count = count_characters(get_post($post['pid'])['message']);

    $new_character_count = count_characters($post['message']);

    if ($old_character_count === $new_character_count) {
        return $data_handler;
    }

    $bonus_income = ($new_character_count - $old_character_count) * get_income_value(INCOME_TYPE_POST_PER_CHARACTER);

    if (!empty($bonus_income)) {
        points_add_simple(
            (int)$post['uid'],
            $bonus_income,
            (int)$data_handler->data['fid'],
            true
        );
    }

    return $data_handler;
}

function datahandler_post_insert_thread_end(postDatahandler &$data_handler): postDatahandler
{
    $thread = &$data_handler->data;

    if (!empty($thread['savedraft']) || empty($thread['uid'])) {
        return $data_handler;
    }

    if ($data_handler->return_values['visible'] !== POST_VISIBLE_STATUS_VISIBLE) {
        return $data_handler;
    }

    if (!get_income_value(INCOME_TYPE_THREAD_NEW)) {
        return $data_handler;
    }

    $bonus_income = 0;

    if (($character_count = count_characters($thread['message'])) >= get_income_value(
            INCOME_TYPE_POST_MINIMUM_CHARACTERS
        )) {
        $bonus_income = $character_count * get_income_value(INCOME_TYPE_POST_PER_CHARACTER);
    }

    // give points to the author of the new thread
    points_add_simple(
        (int)$thread['uid'],
        get_income_value(INCOME_TYPE_THREAD_NEW) + $bonus_income,
        (int)$thread['fid']
    );

    return $data_handler;
}