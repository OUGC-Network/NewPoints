<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/stats.php)
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

run_hooks('admin_stats_begin');

$page->add_breadcrumb_item($lang->newpoints_stats, 'index.php?module=newpoints-stats');

$page->output_header($lang->newpoints_stats);

$sub_tabs['newpoints_stats'] = [
    'title' => $lang->newpoints_stats,
    'link' => 'index.php?module=newpoints-stats',
    'description' => $lang->newpoints_stats_description
];

$page->output_nav_tabs($sub_tabs, 'newpoints_stats');
if (!$mybb->get_input('action')) // view stats
{
    $fields = ['uid', 'username', 'newpoints'];

    run_hooks('admin_stats_noaction_start');

    // table
    $table = new Table();
    $table->construct_header($lang->newpoints_stats_user, ['width' => '50%']);
    $table->construct_header($lang->newpoints_stats_points, ['width' => '50%', 'class' => 'align_center']);

    $table->construct_cell($lang->newpoints_error_gathering, ['colspan' => 4]);
    $table->construct_row();

    $table->output($lang->newpoints_stats_richest_users);

    echo '<br />';

    // table
    $table = new Table();
    $table->construct_header($lang->newpoints_stats_to, ['width' => '30%']);
    $table->construct_header($lang->newpoints_stats_from, ['width' => '30%']);
    $table->construct_header($lang->newpoints_stats_amount, ['width' => '20%', 'class' => 'align_center']);
    $table->construct_header($lang->newpoints_stats_date, ['width' => '20%', 'class' => 'align_center']);

    $table->construct_cell($lang->newpoints_error_gathering, ['colspan' => 4]);
    $table->construct_row();

    $table->output($lang->newpoints_stats_lastdonations);

    run_hooks('admin_stats_noaction_end');
}

run_hooks('admin_stats_terminate');

$page->output_footer();