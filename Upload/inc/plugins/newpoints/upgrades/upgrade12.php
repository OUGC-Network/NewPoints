<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/upgrades/upgrade12.php)
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

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

if (!defined('IN_ADMINCP')) {
    die('This file must be accessed from the Administrator Panel.');
}

function upgrade12_info()
{
    return [
        'new_version' => '1.2',
        'name' => 'Upgrade to 1.2',
        'description' => 'Upgrade NewPoints 1.1 to NewPoints 1.2.<br />Note that all forums and group rules will be deleted.'
    ];
}

// upgrade function
function upgrade12_run()
{
    global $db;

    if ($db->table_exists('newpoints_forumrules')) {
        $db->drop_table('newpoints_forumrules');
    }

    if ($db->table_exists('newpoints_grouprules')) {
        $db->drop_table('newpoints_grouprules');
    }

    $db->write_query(
        'CREATE TABLE `' . $db->table_prefix . "newpoints_forumrules` (
	  `rid` bigint(30) UNSIGNED NOT NULL auto_increment,
	  `fid` int(10) UNSIGNED NOT NULL default '0',
	  `name` varchar(100) NOT NULL default '',
	  `description` text NOT NULL,
	  `rate` float NOT NULL default '1',
	  PRIMARY KEY  (`rid`)
		) ENGINE=MyISAM"
    );

    $db->write_query(
        'CREATE TABLE `' . $db->table_prefix . "newpoints_grouprules` (
	  `rid` bigint(30) UNSIGNED NOT NULL auto_increment,
	  `gid` int(10) UNSIGNED NOT NULL default '0',
	  `name` varchar(100) NOT NULL default '',
	  `description` text NOT NULL,
	  `rate` float NOT NULL default '1',
	  `lastpay` bigint(30) UNSIGNED NOT NULL default '0',
	  PRIMARY KEY  (`rid`)
		) ENGINE=MyISAM"
    );
}