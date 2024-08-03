<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/forumrules.php)
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
use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\run_hooks;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $plugins, $page, $db, $mybb;

language_load();

run_hooks('admin_forumrules_begin');

$page->add_breadcrumb_item($lang->newpoints_forumrules, 'index.php?module=newpoints-forumrules');

$page->output_header($lang->newpoints_forumrules);

$sub_tabs['newpoints_forumrules'] = [
    'title' => $lang->newpoints_forumrules,
    'link' => 'index.php?module=newpoints-forumrules',
    'description' => $lang->newpoints_forumrules_description
];

$sub_tabs['newpoints_forumrules_add'] = [
    'title' => $lang->newpoints_forumrules_add,
    'link' => 'index.php?module=newpoints-forumrules&amp;action=add',
    'description' => $lang->newpoints_forumrules_add_description
];

$sub_tabs['newpoints_forumrules_edit'] = [
    'title' => $lang->newpoints_forumrules_edit,
    'link' => 'index.php?module=newpoints-forumrules&amp;action=edit',
    'description' => $lang->newpoints_forumrules_edit_description
];

if (!$mybb->get_input('action')) // view forumrules
{
    $page->output_nav_tabs($sub_tabs, 'newpoints_forumrules');

    run_hooks('admin_forumrules_noaction_start');

    echo "<p class=\"notice\">{$lang->newpoints_forumrules_notice}</p>";

    // table
    $table = new Table();
    $table->construct_header($lang->newpoints_forumrules_name, ['width' => '50%']);
    $table->construct_header($lang->newpoints_forumrules_title, ['width' => '30%']);
    $table->construct_header($lang->newpoints_forumrules_options, ['width' => '20%', 'class' => 'align_center']);

    $query = $db->simple_select('newpoints_forumrules', '*', '', ['order_by' => 'rid', 'order_dir' => 'ASC']);
    while ($rule = $db->fetch_array($query)) {
        $table->construct_cell(
            htmlspecialchars_uni($rule['name']) . '<br /><small>' . htmlspecialchars_uni(
                $rule['description']
            ) . '</small>'
        );

        $forum = get_forum($rule['fid']);

        $link = "<a href=\"" . $mybb->settings['bburl'] . '/' . get_forum_link(
                $rule['fid']
            ) . "\" target=\"_blank\">" . $forum['name'] . '</a>';
        $table->construct_cell($link);
        $table->construct_cell(
            "<a href=\"index.php?module=newpoints-forumrules&amp;action=delete_rule&amp;rid={$rule['rid']}\" target=\"_self\">{$lang->newpoints_delete}</a> - <a href=\"index.php?module=newpoints-forumrules&amp;action=edit&amp;rid={$rule['rid']}\" target=\"_self\">{$lang->newpoints_edit}</a>",
            ['class' => 'align_center']
        ); // delete button

        $table->construct_row();
    }

    if ($table->num_rows() == 0) {
        $table->construct_cell($lang->newpoints_forumrules_none, ['colspan' => 3]);
        $table->construct_row();
    }

    $table->output($lang->newpoints_forumrules_rules);

    run_hooks('admin_forumrules_noaction_end');
} elseif ($mybb->get_input('action') == 'add') {
    run_hooks('admin_forumrules_add_start');

    $page->output_nav_tabs($sub_tabs, 'newpoints_forumrules_add');

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input('my_post_key')) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-forumrules');
        }

        if (!$mybb->get_input('name') || !$mybb->get_input('forum', MyBB::INPUT_INT)) {
            flash_message($lang->newpoints_missing_fields, 'error');
            admin_redirect('index.php?module=newpoints-forumrules');
        }

        $insert_query = [
            'name' => $db->escape_string($mybb->get_input('name')),
            'description' => $db->escape_string($mybb->get_input('description')),
            'rate' => $mybb->get_input('rate', MyBB::INPUT_FLOAT),
            'pointsview' => $mybb->get_input('minview', MyBB::INPUT_INT),
            'pointspost' => $mybb->get_input('minpost', MyBB::INPUT_INT),
            'fid' => $mybb->get_input('forum', MyBB::INPUT_INT)
        ];

        $insert_query = run_hooks('admin_forumrules_add_insert', $insert_query);

        $db->insert_query('newpoints_forumrules', $insert_query);

        // Rebuild rules cache
        $array = [];
        rules_rebuild_cache($array);

        flash_message($lang->newpoints_forumrules_added, 'success');
        admin_redirect('index.php?module=newpoints-forumrules');
    }

    $form = new Form('index.php?module=newpoints-forumrules&amp;action=add', 'post', 'newpoints');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $form_container = new FormContainer($lang->newpoints_forumrules_addrule);
    $form_container->output_row(
        $lang->newpoints_forumrules_name . '<em>*</em>',
        $lang->newpoints_forumrules_name_desc,
        $form->generate_text_box('name', '', ['id' => 'name']),
        'name'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_desc,
        $lang->newpoints_forumrules_desc_desc,
        $form->generate_text_box('description', '', ['id' => 'description']),
        'description'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_rate . '<em>*</em>',
        $lang->newpoints_forumrules_rate_desc,
        $form->generate_text_box('rate', '1', ['id' => 'rate']),
        'rate'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_minview,
        $lang->newpoints_forumrules_minview_desc,
        $form->generate_text_box('minview', '0', ['id' => 'minview']),
        'minview'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_minpost,
        $lang->newpoints_forumrules_minpost_desc,
        $form->generate_text_box('minpost', '0', ['id' => 'minpost']),
        'minpost'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_forum . '<em>*</em>',
        $lang->newpoints_forumrules_forum_desc,
        $form->generate_forum_select('forum', 0, ['id' => 'forum', 'main_option' => $lang->newpoints_select_forum]
        ),
        'forum'
    );

    run_hooks('admin_forumrules_add');

    $form_container->end();

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
    $form->output_submit_wrapper($buttons);
    $form->end();
} elseif ($mybb->get_input('action') == 'edit') {
    run_hooks('admin_forumrules_edit_start');

    $page->output_nav_tabs($sub_tabs, 'newpoints_forumrules_edit');

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input('my_post_key')) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-forumrules');
        }

        if (!$mybb->get_input('name') || !$mybb->get_input('forum', MyBB::INPUT_INT)) {
            flash_message($lang->newpoints_missing_fields, 'error');
            admin_redirect('index.php?module=newpoints-forumrules');
        }

        $update_query = [
            'name' => $db->escape_string($mybb->get_input('name')),
            'description' => $db->escape_string($mybb->get_input('description')),
            'rate' => $mybb->get_input('rate', MyBB::INPUT_FLOAT),
            'pointsview' => $mybb->get_input('minview', MyBB::INPUT_INT),
            'pointspost' => $mybb->get_input('minpost', MyBB::INPUT_INT),
            'fid' => $mybb->get_input('forum', MyBB::INPUT_INT)
        ];

        $update_query = run_hooks('admin_forumrules_edit_update', $update_query);

        $db->update_query('newpoints_forumrules', $update_query, "rid='{$mybb->get_input('rid', MyBB::INPUT_INT)}'");

        // Rebuild rules cache
        $array = [];
        rules_rebuild_cache();

        flash_message($lang->newpoints_forumrules_edited, 'success');
        admin_redirect('index.php?module=newpoints-forumrules');
    }

    $query = $db->simple_select(
        'newpoints_forumrules',
        '*',
        "rid='{$mybb->get_input('rid', MyBB::INPUT_INT)}'"
    );
    $rule = $db->fetch_array($query);
    if (!$rule) {
        flash_message($lang->newpoints_forumrules_invalid, 'error');
        admin_redirect('index.php?module=newpoints-forumrules');
    }

    $form = new Form('index.php?module=newpoints-forumrules&amp;action=edit', 'post', 'newpoints');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);
    echo $form->generate_hidden_field('rid', $rule['rid']);

    $form_container = new FormContainer($lang->newpoints_forumrules_editrule);
    $form_container->output_row(
        $lang->newpoints_forumrules_name . '<em>*</em>',
        $lang->newpoints_forumrules_name_desc,
        $form->generate_text_box('name', htmlspecialchars_uni($rule['name']), ['id' => 'name']),
        'name'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_desc,
        $lang->newpoints_forumrules_desc_desc,
        $form->generate_text_box('description', htmlspecialchars_uni($rule['description']), ['id' => 'description']
        ),
        'description'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_rate . '<em>*</em>',
        $lang->newpoints_forumrules_rate_desc,
        $form->generate_text_box('rate', floatval($rule['rate']), ['id' => 'rate']),
        'rate'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_minview,
        $lang->newpoints_forumrules_minview_desc,
        $form->generate_text_box('minview', intval($rule['pointsview']), ['id' => 'minview']),
        'minview'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_minpost,
        $lang->newpoints_forumrules_minpost_desc,
        $form->generate_text_box('minpost', intval($rule['pointspost']), ['id' => 'minpost']),
        'minpost'
    );
    $form_container->output_row(
        $lang->newpoints_forumrules_forum . '<em>*</em>',
        $lang->newpoints_forumrules_forum_desc,
        $form->generate_forum_select(
            'forum',
            intval($rule['fid']),
            ['id' => 'forum', 'main_option' => $lang->newpoints_select_forum]
        ),
        'forum'
    );

    run_hooks('admin_forumrules_edit');

    $form_container->end();

    $buttons = [];
    $buttons[] = $form->generate_submit_button($lang->newpoints_submit_button);
    $buttons[] = $form->generate_reset_button($lang->newpoints_reset_button);
    $form->output_submit_wrapper($buttons);
    $form->end();
} elseif ($mybb->get_input('action') == 'delete_rule') {
    if ($mybb->get_input('no')) // user clicked no
    {
        admin_redirect('index.php?module=newpoints-forumrules');
    }

    if ($mybb->request_method == 'post') {
        if (!$mybb->get_input('my_post_key') || $mybb->post_code != $mybb->get_input('my_post_key')) {
            $mybb->request_method = 'get';
            flash_message($lang->newpoints_error, 'error');
            admin_redirect('index.php?module=newpoints-forumrules');
        }

        if (!$db->fetch_field(
            $db->simple_select(
                'newpoints_forumrules',
                'name',
                "rid='{$mybb->get_input('rid', MyBB::INPUT_INT)}'",
                ['limit' => 1]
            ),
            'name'
        )) {
            flash_message($lang->newpoints_forumrules_invalid, 'error');
            admin_redirect('index.php?module=newpoints-forumrules');
        }

        $db->delete_query('newpoints_forumrules', "rid='{$mybb->get_input('rid', MyBB::INPUT_INT)}'");

        // Rebuild rules cache
        $array = [];
        rules_rebuild_cache();

        flash_message($lang->newpoints_forumrules_deleted, 'success');
        admin_redirect('index.php?module=newpoints-forumrules');
    }

    $form = new Form(
        "index.php?module=newpoints-forumrules&amp;action=delete_rule&amp;rid={$mybb->get_input('rid', MyBB::INPUT_INT)}&amp;my_post_key={$mybb->post_code}",
        'post'
    );
    echo "<div class=\"confirm_action\">\n";
    echo "<p>{$lang->newpoints_forumrules_deleteconfirm}</p>\n";
    echo "<br />\n";
    echo "<p class=\"buttons\">\n";
    echo $form->generate_submit_button($lang->yes, ['class' => 'button_yes']);
    echo $form->generate_submit_button($lang->no, ['name' => 'no', 'class' => 'button_no']);
    echo "</p>\n";
    echo "</div>\n";
    $form->end();
}

run_hooks('admin_forumrules_terminate');

$page->output_footer();