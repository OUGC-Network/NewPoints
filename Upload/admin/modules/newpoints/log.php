<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/log.php)
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

use function Newpoints\Core\language_load;
use function Newpoints\Core\run_hooks;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb;

language_load();

run_hooks('admin_log_begin');

if (!$mybb->get_input('action')) // view logs
{
    $page->add_breadcrumb_item($lang->newpoints_log, 'index.php?module=newpoints-log');

    $page->output_header($lang->newpoints_log);

    $sub_tabs['newpoints_log'] = [
        'title' => $lang->newpoints_log,
        'link' => 'index.php?module=newpoints-log',
        'description' => $lang->newpoints_log_description
    ];

    $page->output_nav_tabs($sub_tabs, 'newpoints_log');

    $per_page = 10;
    if ($mybb->get_input('page', MyBB::INPUT_INT) > 1) {
        $start = ($mybb->get_input('page', MyBB::INPUT_INT) * $per_page) - $per_page;
    } else {
        $mybb->input['page'] = 1;
        $start = 0;
    }

    $sql = '';

    $filter_msg = '';

    $url_filters = '';

    // Process "username" search
    if ($mybb->get_input('username') != '') {
        $query = $db->simple_select(
            'users',
            'uid',
            "username='{$db->escape_string(trim($mybb->get_input('username')))}'"
        );
        $uid = $db->fetch_field($query, 'uid');
        if ($uid <= 0) {
            flash_message($lang->newpoints_invalid_username, 'error');
            admin_redirect('index.php?module=newpoints-log');
        }

        $sql .= 'uid=' . (int)$uid;

        $url_filters .= '&amp;username=' . urlencode(htmlspecialchars_uni($mybb->get_input('username')));

        $filter_msg .= $lang->newpoints_username . ': ' . htmlspecialchars_uni($mybb->get_input('username'));
    }

    // Process "fields" search
    $selected = [];

    $fields = $mybb->get_input('fields', MyBB::INPUT_ARRAY);

    if (!empty($fields)) {
        $or = '';
        $close = '';

        if ($sql != '') {
            $sql .= ' AND (';
            $close = ')';
        }

        $url_filters = '';

        foreach ($fields as $field) {
            $field = htmlspecialchars_uni($field);
            $sql .= $or . 'action=\'' . $field . '\'';
            if ($or == '') {
                $or = ' OR ';
            }

            $selected[$field] = $field;

            if (!isset($selected[$field])) {
                $selected[$field] = $field;
            }

            if ($filter_msg != '') {
                $filter_msg .= '<br />' . $field;
            }

            $url_filters .= '&amp;fields[]=' . $field;
        }

        $sql .= $close;
    }

    if ($filter_msg != '') {
        echo "<p class=\"notice\">" . $lang->sprintf($lang->newpoints_filter, $filter_msg) . '</p><br />';
    }

    echo "<p class=\"notice\">{$lang->newpoints_log_notice}</p>";

    $query = $db->simple_select('newpoints_log', 'COUNT(lid) as log_entries', $sql);
    $total_rows = $db->fetch_field($query, 'log_entries');
    if ($total_rows > $per_page) {
        echo '<br />' . draw_admin_pagination(
                $mybb->get_input('page', MyBB::INPUT_INT),
                $per_page,
                $total_rows,
                'index.php?module=newpoints-log&amp;page={page}' . $url_filters
            );
    }

    // table
    $table = new Table();
    $table->construct_header($lang->newpoints_log_action, ['width' => '15%']);
    $table->construct_header($lang->newpoints_log_data, ['width' => '30%']);
    $table->construct_header($lang->newpoints_log_user, ['width' => '20%']);
    $table->construct_header($lang->newpoints_log_date, ['width' => '20%', 'class' => 'align_center']);
    $table->construct_header($lang->newpoints_log_options, ['width' => '15%', 'class' => 'align_center']);

    $fields = [];
    $query = $db->simple_select(
        'newpoints_log',
        '*',
        $sql,
        ['order_by' => 'date', 'order_dir' => 'DESC', 'limit' => "{$start}, {$per_page}"]
    );
    while ($log = $db->fetch_array($query)) {
        $table->construct_cell(htmlspecialchars_uni($log['action']));
        $table->construct_cell(htmlspecialchars_uni($log['data']));
        $link = build_profile_link(htmlspecialchars_uni($log['username']), intval($log['uid']));
        $table->construct_cell($link);
        $table->construct_cell(
            my_date($mybb->settings['dateformat'], intval($log['date']), '', false) . ', ' . my_date(
                $mybb->settings['timeformat'],
                intval($log['date'])
            ),
            ['class' => 'align_center']
        );
        $table->construct_cell(
            "<a href=\"index.php?module=newpoints-log&amp;action=delete_log&amp;lid={$log['lid']}&amp;my_post_key={$mybb->post_code}\" target=\"_self\">{$lang->newpoints_delete}</a>",
            ['class' => 'align_center']
        ); // delete button

        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->newpoints_no_log_entries, ['colspan' => 5]);
        $table->construct_row();
    }

    $table->output($lang->newpoints_log_entries);

    echo '<br />';

    // Get all actions
    $fields = [];
    $q = $db->query('SELECT action FROM `' . $db->table_prefix . 'newpoints_log` GROUP BY action');
    while ($action = $db->fetch_field($q, 'action')) {
        $fields[htmlspecialchars_uni($action)] = htmlspecialchars_uni($action);
    }

    $form = new Form('index.php?module=newpoints-log', 'post', 'newpoints');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $form_container = new FormContainer($lang->newpoints_log_filter);
    $form_container->output_row(
        $lang->newpoints_filter_username,
        $lang->newpoints_filter_username_desc,
        $form->generate_text_box(
            'username',
            htmlspecialchars_uni($mybb->get_input('username')),
            ['id' => 'username']
        ),
        'username'
    );
    $form_container->output_row(
        $lang->newpoints_filter_actions,
        $lang->newpoints_filter_actions_desc,
        $form->generate_select_box('fields[]', $fields, $selected, ['id' => 'fields', 'multiple' => true]),
        'fields'
    );
    $form_container->end();

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $form->output_submit_wrapper($buttons);
    $form->end();

    echo '<br />';

    $form = new Form('index.php?module=newpoints-log&amp;action=prune', 'post', 'newpoints');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $form_container = new FormContainer($lang->newpoints_log_prune);
    $form_container->output_row(
        $lang->newpoints_older_than,
        $lang->newpoints_older_than_desc,
        $form->generate_text_box('days', 30, ['id' => 'days']),
        'days'
    );
    $form_container->end();

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
    $form->output_submit_wrapper($buttons);
    $form->end();
} elseif ($mybb->get_input('action') == 'delete_log') {
    if ($mybb->get_input('no')) // user clicked no
    {
        admin_redirect('index.php?module=newpoints-log');
    }

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input('my_post_key')) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-log');
        }

        if (!$db->fetch_field(
            $db->simple_select(
                'newpoints_log',
                'action',
                "lid='{$mybb->get_input('lid', MyBB::INPUT_INT)}'",
                ['limit' => 1]
            ),
            'action'
        )) {
            flash_message($lang->newpoints_log_invalid, 'error');
            admin_redirect('index.php?module=newpoints-log');
        }

        $db->delete_query('newpoints_log', "lid='{$mybb->get_input('lid', MyBB::INPUT_INT)}'");
        flash_message($lang->newpoints_log_deleted, 'success');
        admin_redirect('index.php?module=newpoints-log');
    }

    $page->add_breadcrumb_item($lang->newpoints_log, 'index.php?module=newpoints-log');

    $page->output_header($lang->newpoints_log);

    $form = new Form(
        "index.php?module=newpoints-log&amp;action=delete_log&amp;lid={$mybb->get_input('lid', MyBB::INPUT_INT)}&amp;my_post_key={$mybb->post_code}",
        'post'
    );
    echo "<div class=\"confirm_action\">\n";
    echo "<p>{$lang->newpoints_log_deleteconfirm}</p>\n";
    echo "<br />\n";
    echo "<p class=\"buttons\">\n";
    echo $form->generate_submit_button($lang->yes, ['class' => 'button_yes']);
    echo $form->generate_submit_button($lang->no, ['name' => 'no', 'class' => 'button_no']);
    echo "</p>\n";
    echo "</div>\n";
    $form->end();
} elseif ($mybb->get_input('action') == 'prune') {
    if ($mybb->get_input('no')) // user clicked no
    {
        admin_redirect('index.php?module=newpoints-log');
    }

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input('my_post_key')) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-log');
        }

        $db->delete_query(
            'newpoints_log',
            'date < ' . (TIME_NOW - $mybb->get_input('days', MyBB::INPUT_INT) * 60 * 60 * 24)
        );
        flash_message($lang->newpoints_log_pruned, 'success');
        admin_redirect('index.php?module=newpoints-log');
    }

    $page->add_breadcrumb_item($lang->newpoints_log, 'index.php?module=newpoints-log');

    $page->output_header($lang->newpoints_log);

    $form = new Form(
        "index.php?module=newpoints-log&amp;action=prune&amp;days={$mybb->get_input('days', MyBB::INPUT_INT)}&amp;my_post_key={$mybb->post_code}",
        'post'
    );
    echo "<div class=\"confirm_action\">\n";
    echo "<p>{$lang->newpoints_log_pruneconfirm}</p>\n";
    echo "<br />\n";
    echo "<p class=\"buttons\">\n";
    echo $form->generate_submit_button($lang->yes, ['class' => 'button_yes']);
    echo $form->generate_submit_button($lang->no, ['name' => 'no', 'class' => 'button_no']);
    echo "</p>\n";
    echo "</div>\n";
    $form->end();
}

run_hooks('admin_log_terminate');

$page->output_footer();