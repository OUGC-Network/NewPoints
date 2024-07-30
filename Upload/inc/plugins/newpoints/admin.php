<?php

/***************************************************************************
 *
 *   Newpoints plugin (/inc/plugins/newpoints/admin.php)
 *   Author: Pirata Nervo
 *   Copyright: © 2009 Pirata Nervo
 *   Copyright: © 2024 Omar Gonzalez
 *
 *   Website: https://ougc.network
 *
 *   NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
 *
 ***************************************************************************/

/****************************************************************************
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

namespace Newpoints\Admin;

use PhpParser\Node\Expr\Cast\Bool_;

use PluginLibrary;

use stdClass;

use function Newpoints\Core\language_load;

use function Newpoints\Core\rules_rebuild_cache;

use function Newpoints\Core\settings_add;
use function Newpoints\Core\settings_rebuild_cache;
use function Newpoints\Core\templates_rebuild;

use const Newpoints\Core\FIELDS_DATA;
use const Newpoints\Core\TABLES_DATA;

const PERMISSION_ENABLE = 1;

const PERMISSION_DISABLE = 0;

const PERMISSION_REMOVE = -1;

const TASK_ENABLE = 1;

const TASK_DEACTIVATE = 0;

const TASK_DELETE = -1;

function plugin_information(): array
{
    global $lang;

    language_load();

    return [
        'name' => 'NewPoints',
        'description' => $lang->newpoints_description,
        'website' => 'https://ougc.network',
        'author' => 'Diogo Parrinha & Omar G',
        'authorsite' => 'https://ougc.network',
        'version' => NEWPOINTS_VERSION,
        'versioncode' => NEWPOINTS_VERSION_CODE,
        'compatibility' => '18*',
        'codename' => 'ougc_newpoints',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ]
    ];
}

function plugin_activation(): bool
{
    global $PL, $cache, $lang;

    language_load();

    $plugin_information = plugin_information();

    plugin_library_load();

    \Newpoints\Core\settings_rebuild();

    templates_rebuild();

    // Insert/update version into cache
    $plugins_list = $cache->read('ougc_plugins');

    if (!$plugins_list) {
        $plugins_list = [];
    }

    if (!isset($plugins_list['newpoints'])) {
        $plugins_list['newpoints'] = $plugin_information['versioncode'];
    }

    db_verify_tables();

    db_verify_columns();

    task_enable();

    permissions_update();

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    $plugins_list['newpoints'] = $plugin_information['versioncode'];

    $cache->update('ougc_plugins', $plugins_list);

    return true;
}

function pluginDeactivation(): bool
{
    task_disable();

    permissions_update(PERMISSION_DISABLE);

    return true;
}

function pluginInstallation(): bool
{
    global $cache;

    db_verify_tables();

    db_verify_columns();

    task_enable();

    rules_rebuild_cache();

    return true;
}

function pluginIsInstalled(): bool
{
    static $isInstalled = null;

    if ($isInstalled === null) {
        global $db;

        $isInstalledEach = true;

        foreach (db_tables() as $tableName => $tableData) {
            $isInstalledEach = $db->table_exists($tableName) && $isInstalledEach;

            break;
        }

        $isInstalled = $isInstalledEach;
    }

    return $isInstalled;
}

function pluginUninstallation(): bool
{
    global $db, $PL, $cache;

    plugin_library_load();

    // uninstall plugins
    $plugins_cache = $cache->read('newpoints_plugins');
    $active_plugins = $plugins_cache['active'];

    if (!empty($active_plugins)) {
        foreach ($active_plugins as $plugin) {
            // Ignore missing plugins
            if (!file_exists(constant('MYBB_ROOT') . 'inc/plugins/newpoints/' . $plugin . '.php')) {
                continue;
            }

            require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/' . $plugin . '.php';

            if (function_exists("{$plugin}_deactivate")) {
                call_user_func("{$plugin}_deactivate");
            }

            if (function_exists("{$plugin}_uninstall")) {
                call_user_func("{$plugin}_uninstall");
            }
        }
    }

    // delete plugins cache
    $cache->delete('newpoints_rules');
    $cache->delete('newpoints_settings');
    $cache->delete('newpoints_plugins');

    if ($db->field_exists('newpoints', 'users')) {
        $db->write_query('ALTER TABLE `' . $db->table_prefix . 'users` DROP `newpoints`;');
    }

    // Delete all templates
    $query = $db->simple_select('templategroups', 'prefix', "prefix='newpoints'");

    $twhere = [];

    while ($row = $db->fetch_array($query)) {
        $tprefix = $db->escape_string($row['prefix']);
        $twhere[] = "title='{$tprefix}' OR title LIKE '{$tprefix}=_%' ESCAPE '='";
    }

    if ($twhere) {
        $db->delete_query('templategroups', "prefix='newpoints'");

        $db->delete_query('templates', implode(' OR ', $twhere));
    }

    //rebuild_settings();

    $db->delete_query('tasks', 'file=\'backupnewpoints\'');


    foreach (TABLES_DATA as $table_name => $table_data) {
        if ($db->table_exists($table_name)) {
            $db->drop_table($table_name);
        }
    }

    foreach (FIELDS_DATA as $table_name => $table_columns) {
        if ($db->table_exists($table_name)) {
            foreach ($table_columns as $field_name => $field_data) {
                if ($db->field_exists($field_name, $table_name)) {
                    $db->drop_column($table_name, $field_name);
                }
            }
        }
    }

    $PL->settings_delete('newpoints');

    $PL->templates_delete('newpoints');

    task_delete();

    permissions_update(PERMISSION_REMOVE);

    // Delete version from cache
    $plugins_list = (array)$cache->read('ougc_plugins');

    if (isset($plugins_list['newpoints'])) {
        unset($plugins_list['newpoints']);
    }

    if (!empty($plugins_list)) {
        $cache->update('ougc_plugins', $plugins_list);
    } else {
        $cache->delete('ougc_plugins');
    }

    return true;
}

function permissions_update(int $action = PERMISSION_ENABLE): bool
{
    change_admin_permission('newpoints', false, $action);
    change_admin_permission('newpoints', 'plugins', $action);
    change_admin_permission('newpoints', 'settings', $action);
    change_admin_permission('newpoints', 'log', $action);
    change_admin_permission('newpoints', 'maintenance', $action);
    change_admin_permission('newpoints', 'forumrules', $action);
    change_admin_permission('newpoints', 'grouprules', $action);
    change_admin_permission('newpoints', 'stats', $action);
    change_admin_permission('newpoints', 'upgrades', $action);

    return true;
}

function task_enable(int $action = TASK_ENABLE): bool
{
    global $db, $lang;

    language_load();

    if ($action === TASK_DELETE) {
        $db->delete_query('tasks', "file='backupnewpoints'");

        return true;
    }

    $db_query = $db->simple_select('tasks', '*', "file='backupnewpoints'", ['limit' => 1]);

    if ($db->num_rows($db_query)) {
        $db->update_query('tasks', ['enabled' => $action], "file='backupnewpoints'");
    } else {
        include_once constant('MYBB_ROOT') . 'inc/functions_task.php';

        $new_task_data = [
            'title' => $db->escape_string('Backup NewPoints'),
            'description' => $db->escape_string("Creates a backup of NewPoints default tables and users\'s points."),
            'file' => $db->escape_string('backupnewpoints'),
            'minute' => $db->escape_string('0'),
            'hour' => $db->escape_string('0'),
            'day' => $db->escape_string('*'),
            'weekday' => $db->escape_string('0'),
            'month' => $db->escape_string('*'),
            'enabled' => 0,
            'logging' => 1
        ];

        $new_task_data['nextrun'] = fetch_next_run($new_task_data);

        $db->insert_query('tasks', $new_task_data);
    }

    return true;
}

function task_disable(): bool
{
    task_enable(TASK_DEACTIVATE);

    return true;
}

function task_delete(): bool
{
    task_enable(TASK_DELETE);

    return true;
}

function db_tables(): array
{
    $tables_data = [];

    foreach (TABLES_DATA as $table_name => $table_columns) {
        foreach ($table_columns as $field_name => $field_data) {
            if (!isset($field_data['type'])) {
                continue;
            }

            $tables_data[$table_name][$field_name] = db_build_field_definition($field_data);
        }

        foreach ($table_columns as $field_name => $field_data) {
            if (isset($field_data['primary_key'])) {
                $tables_data[$table_name]['primary_key'] = $field_name;
            }
            if ($field_name === 'unique_key') {
                $tables_data[$table_name]['unique_key'] = $field_data;
            }
        }
    }

    return $tables_data;
}

function db_verify_tables(): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (db_tables() as $table_name => $table_data) {
        if ($db->table_exists($table_name)) {
            foreach ($table_data as $field_name => $field_data) {
                if ($field_name == 'primary_key' || $field_name == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($field_name, $table_name)) {
                    $db->modify_column($table_name, "`{$field_name}`", $field_data);
                } else {
                    $db->add_column($table_name, $field_name, $field_data);
                }
            }
        } else {
            $query_string = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$table_name}` (";

            foreach ($table_data as $field_name => $field_data) {
                if ($field_name == 'primary_key') {
                    $query_string .= "PRIMARY KEY (`{$field_data}`)";
                } elseif ($field_name != 'unique_key') {
                    $query_string .= "`{$field_name}` {$field_data},";
                }
            }

            $query_string .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query_string);
        }
    }

    db_verify_indexes();

    return true;
}

function db_verify_indexes(): bool
{
    global $db;

    foreach (db_tables() as $table_name => $table_data) {
        if (!$db->table_exists($table_name)) {
            continue;
        }

        if (isset($table_data['unique_key'])) {
            foreach ($table_data['unique_key'] as $key_name => $key_value) {
                if ($db->index_exists($table_name, $key_name)) {
                    continue;
                }

                $db->write_query(
                    "ALTER TABLE {$db->table_prefix}{$table_name} ADD UNIQUE KEY {$key_name} ({$key_value})"
                );
            }
        }
    }

    return true;
}

function db_build_field_definition(array $field_data): string
{
    $field_definition = '';

    $field_definition .= $field_data['type'];

    if (isset($field_data['size'])) {
        $field_definition .= "({$field_data['size']})";
    }

    if (isset($field_data['unsigned'])) {
        if ($field_data['unsigned'] === true) {
            $field_definition .= ' UNSIGNED';
        } else {
            $field_definition .= ' SIGNED';
        }
    }

    if (!isset($field_data['null'])) {
        $field_definition .= ' NOT';
    }

    $field_definition .= ' NULL';

    if (isset($field_data['auto_increment'])) {
        $field_definition .= ' AUTO_INCREMENT';
    }

    if (isset($field_data['default'])) {
        $field_definition .= " DEFAULT '{$field_data['default']}'";
    }

    return $field_definition;
}

function db_verify_columns(): bool
{
    global $db;

    foreach (FIELDS_DATA as $table_name => $table_columns) {
        foreach ($table_columns as $field_name => $field_data) {
            if (!isset($field_data['type'])) {
                continue;
            }

            if ($db->field_exists($field_name, $table_name)) {
                $db->modify_column($table_name, "`{$field_name}`", db_build_field_definition($field_data));
            } else {
                $db->add_column($table_name, $field_name, db_build_field_definition($field_data));
            }
        }
    }

    return true;
}

function plugin_library_requirements(): stdClass
{
    return (object)plugin_information()['pl'];
}

function plugin_library_load(): bool
{
    global $PL, $lang;

    language_load();

    $file_exists = file_exists(PLUGINLIBRARY);

    if ($file_exists && !($PL instanceof PluginLibrary)) {
        require_once PLUGINLIBRARY;
    }

    if (!$file_exists || $PL->version < plugin_library_requirements()->version) {
        flash_message(
            $lang->sprintf(
                $lang->newpoints_plugin_library,
                plugin_library_requirements()->url,
                plugin_library_requirements()->version
            ),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}