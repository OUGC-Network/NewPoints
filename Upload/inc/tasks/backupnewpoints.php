<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/tasks/backupnewpoints.php)
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

use function Newpoints\Core\cache_get_instances;
use function NewPoints\Core\get_setting;
use function NewPoints\Core\language_load;
use function NewPoints\Core\run_hooks;

use const NewPoints\Core\FIELDS_DATA;
use const NewPoints\Core\TABLES_DATA;

function task_backupnewpoints(array &$task): array
{
    global $lang;

    language_load();

    backupnewpoints_backupdb();

    add_task_log($task, $lang->newpoints_task_ran);

    return $task;
}

// a modified copy of task_backupdb() from backupdb.php
function backupnewpoints_backupdb(): void
{
    if (get_setting('disable_backups')) {
        return;
    }

    global $mybb, $db, $config;

    set_time_limit(0);

    $admin_directory = defined('MYBB_ADMIN_DIR') ? MYBB_ADMIN_DIR : MYBB_ROOT . ($config['admin_dir'] ?? 'admin') . '/';

    // Check if folder is writable, before allowing submission
    if (!is_writable($admin_directory . '/backups/backupnewpoints')) {
        return;
    }

    $file = $admin_directory . '/backups/backupnewpoints/backup_' . substr(
            md5(($mybb->user['uid'] ?? 0) . TIME_NOW),
            0,
            10
        ) . random_str(54);

    if (function_exists('gzopen')) {
        $fp = gzopen($file . '.sql.gz', 'w9');
    } else {
        $fp = fopen($file . '.sql', 'w');
    }

    if (!is_resource($fp)) {
        return;
    }

    $tables_data = array_merge_recursive(TABLES_DATA, FIELDS_DATA);

    $hook_arguments = [
        'tables_data' => &$tables_data,
    ];

    $hook_arguments = run_hooks('backup_start', $hook_arguments);

    foreach (cache_get_instances() as $instance_id => $instance_data) {
        $tables_data['users'][$instance_data['users_column_name']] = [];
    }

    $time = date('dS F Y \a\t H:i', TIME_NOW);

    $header = "-- MyBB Database Backup\n-- Generated: {$time}\n-- -------------------------------------\n\n";

    $contents = $header;

    foreach ($tables_data as $table_name => $fields_data) {
        if ($table_name === 'users' || $table_name === 'threads') {
            \NewPoints\Hooks\Forum\backupnewpoints_clear_overflow($fp, $contents);

            $field_list = array_keys($fields_data);

            if ($table_name === 'users') {
                $field_list[] = 'uid';
            } else {
                $field_list[] = 'tid';
            }

            $query = $db->simple_select($table_name, implode(',', $field_list));

            while ($row_data = $db->fetch_array($query)) {
                $update = '';

                foreach ($field_list as $field_name) {
                    if ($table_name === 'users') {
                        $update .= 'UPDATE `' . $db->table_prefix . "users` SET `{$field_name}`='{$row_data[$field_name]}' WHERE `uid`='{$row_data['uid']}';\n";
                    } else {
                        $update .= 'UPDATE `' . $db->table_prefix . "threads` SET `{$field_name}`='{$row_data[$field_name]}' WHERE `tid`='{$row_data['tid']}';\n";
                    }
                }

                $contents .= $update;

                backupnewpoints_clear_overflow($fp, $contents);
            }
        } else {
            $field_list = [];

            $fields_array = $db->show_fields_from($table_name);

            foreach ($fields_array as $field) {
                if (isset($fields_data[$field['Field']]) && empty($fields_data[$field['Field']]['skip_backup'])) {
                    $field_list[] = $field['Field'];
                }
            }

            /*$structure=$db->show_create_table($table_name).";\n";
            $contents .= $structure;*/
            backupnewpoints_clear_overflow($fp, $contents);

            $query = $db->simple_select($table_name, implode(',', $field_list));

            while ($row_data = $db->fetch_array($query)) {
                foreach ($fields_data as $fields_name => $field_data) {
                    if (!empty($field_data['primary_key'])) {
                    }
                }

                $fields_list_string = implode(',', $field_list);

                $values = '';

                $comma = '';

                foreach ($field_list as $field_name) {
                    if (!isset($row_data[$field_name]) || trim($row_data[$field_name]) === '') {
                        $values .= $comma . "''";
                    } else {
                        $values .= $comma . "'" . $db->escape_string($row_data[$field_name]) . "'";
                    }

                    $comma = ',';
                }

                $contents .= "REPLACE INTO {$db->table_prefix}{$table_name} ({$fields_list_string}) VALUES ({$values});\n";

                backupnewpoints_clear_overflow($fp, $contents);
            }
        }
    }

    if (function_exists('gzopen')) {
        gzwrite($fp, $contents);

        gzclose($fp);
    } else {
        fwrite($fp, $contents);

        fclose($fp);
    }
}

// Allows us to refresh cache to prevent over flowing
function backupnewpoints_clear_overflow($fp, string &$contents): void
{
    if (!is_resource($fp)) {
        return;
    }

    if (function_exists('gzopen')) {
        gzwrite($fp, $contents);
    } else {
        fwrite($fp, $contents);
    }

    $contents = '';
}