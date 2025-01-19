<?php

/***************************************************************************
 *
 *    NewPoints plugin (/admin/modules/newpoints/upgrades.php)
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

run_hooks('admin_upgrades_begin');

if (!$mybb->get_input('action')) // view upgrades
{
    $page->add_breadcrumb_item($lang->newpoints_upgrades, 'index.php?module=newpoints-upgrades');

    $page->output_header($lang->newpoints_upgrades);

    $sub_tabs['newpoints_upgrades'] = [
        'title' => $lang->newpoints_upgrades,
        'link' => 'index.php?module=newpoints-upgrades',
        'description' => $lang->newpoints_upgrades_description
    ];

    $page->output_nav_tabs($sub_tabs, 'newpoints_upgrades');

    echo "<p class=\"notice\">{$lang->newpoints_upgrades_notice}</p>";

    // table
    $table = new Table();
    $table->construct_header($lang->newpoints_upgrades_name, ['width' => '70%']);
    $table->construct_header($lang->options, ['width' => '30%', 'class' => 'align_center']);

    $table->construct_cell($lang->newpoints_no_upgrades, ['colspan' => 2]);
    $table->construct_row();

    $table->output($lang->newpoints_upgrades);
}

run_hooks('admin_upgrades_terminate');

$page->output_footer();