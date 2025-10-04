<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/admin.php)
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

namespace NewPoints\Admin;

use Form;
use InvalidArgumentException;
use MyBB;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_AlertType;
use NewPoints\Core\Permissions;
use PluginLibrary;
use stdClass;
use Exception;
use NewPoints\Core\IncomePermissions;
use NewPoints\Core\IncomeRates;

use function NewPoints\Core\cache_update_instances;
use function NewPoints\Core\instance_get;
use function NewPoints\Core\instance_insert;
use function NewPoints\Core\instance_object;
use function NewPoints\Core\get_setting;
use function NewPoints\Core\language_load;
use function NewPoints\Core\log_error;
use function NewPoints\Core\run_hooks;
use function NewPoints\Core\settings_rebuild;
use function NewPoints\Core\task_delete;
use function NewPoints\Core\task_disable;
use function NewPoints\Core\task_enable;
use function NewPoints\Core\templates_rebuild;
use function NewPoints\Core\templates_remove;
use function NewPoints\Core\user_update;

use const NewPoints\Core\FIELDS_DATA;
use const NewPoints\Core\FORM_TYPE_CHECK_BOX;
use const NewPoints\Core\FORM_TYPE_NUMERIC_FIELD;
use const NewPoints\Core\FORM_TYPE_SELECT_FIELD;
use const NewPoints\Core\FORM_TYPE_TEXT_FIELD;
use const NewPoints\Core\FORM_TYPE_YES_NO_FIELD;
use const NewPoints\Core\INCOME_TYPE_POLL;
use const NewPoints\Core\INCOME_TYPE_POLL_VOTE;
use const NewPoints\Core\INCOME_TYPE_POST;
use const NewPoints\Core\INCOME_TYPE_POST_CHARACTER;
use const NewPoints\Core\INCOME_TYPE_THREAD_REPLY;
use const NewPoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const NewPoints\Core\INCOME_TYPE_THREAD;
use const NewPoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const NewPoints\Core\INSTANCE_DEFAULT_ID;
use const NewPoints\Core\LOGGING_TYPE_CHARGE;
use const NewPoints\Core\LOGGING_TYPE_INCOME;
use const NewPoints\Core\TABLES_DATA;

const PERMISSION_ENABLE = 1;

const PERMISSION_DISABLE = 0;

const PERMISSION_REMOVE = -1;

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

function plugin_activation(): void
{
    // todo: remove old templates from the global templates set
    global $db, $cache;

    language_load();

    $plugin_information = plugin_information();

    plugin_library_load();

    /*~*~* RUN UPDATES START *~*~*/

    if ($db->field_exists('newpoints_rate', 'forums') &&
        !$db->field_exists(Permissions::Rate, 'forums')) {
        $db->rename_column(
            'forums',
            'newpoints_rate',
            Permissions::Rate,
            db_build_field_definition(FIELDS_DATA['forums'][Permissions::Rate])
        );
    }

    /*~*~* RUN UPDATES END *~*~*/

    db_verify_tables();

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            db_verify_columns(
                [
                    'users' => [
                        instance_object($instance_id)->users_column_get() => FIELDS_DATA['users']['newpoints']
                    ]
                ]
            );
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

    db_verify_columns();

    settings_rebuild();

    templates_rebuild();

    // Insert/update version into cache
    $plugins_list = $cache->read('ougc_plugins');

    if (!$plugins_list) {
        $plugins_list = [];
    }

    if (!isset($plugins_list['newpoints'])) {
        $plugins_list['newpoints'] = $plugin_information['versioncode'];
    }

    foreach (
        [
            'newpoints' => ['title' => 'NewPoints', 'description' => 'Handles NewPoints automatic features.'],
            'backupnewpoints' => [
                'title' => 'Backup NewPoints',
                'description' => "Creates a backup of NewPoints default tables and users's points."
            ]
        ] as $task_name => $task_data
    ) {
        task_enable($task_name, $task_data['title'], $task_data['description']);
    }

    permissions_update();

    //rules_rebuild_cache();

    my_alerts_install();

    /*~*~* RUN UPDATES START *~*~*/

    if (!instance_get(INSTANCE_DEFAULT_ID)) {
        instance_insert([
            'instance_id' => INSTANCE_DEFAULT_ID,
            'currency_name_singular' => 'NewPoint',
            'currency_name_plural' => 'NewPoints',
            'users_column_name' => 'newpoints',
            'is_enabled' => 1,
        ]);
    }

    if ($plugins_list['newpoints'] <= 3104) {
        $action_types = implode("','", [
            'income_' . INCOME_TYPE_THREAD_REPLY,
            'income_' . INCOME_TYPE_POST_CHARACTER,
            'income_' . INCOME_TYPE_THREAD
        ]);

        $db->update_query(
            'newpoints_log',
            [
                'log_primary_id' => 0,
                'log_secondary_id' => 0,
                'log_tertiary_id' => 0,
            ],
            "action IN ('{$action_types}')"
        );
    }

    if ($plugins_list['newpoints'] < 3100) {
        // general settings go to usergroup or forum permissions
        foreach (
            [
                'newthread' => 'thread',
                'perreply' => 'thread_reply',
                'perrate' => 'thread_rate',
                'newpost' => 'post',
                'perchar' => 'post_character',
                'pageview' => 'page_view',
                'visit' => 'visit',
                'newpoll' => 'poll',
                'pervote' => 'poll_vote',
                'newreg' => 'user_registration',
                'referral' => 'user_referral',
                'pmsent' => 'private_message',
            ] as $setting_key => $group_key
        ) {
            if (get_setting("income_{$setting_key}") !== false) {
                $db->update_query(
                    'usergroups',
                    ["newpoints_income_{$group_key}" => (float)get_setting("income_{$setting_key}")]
                );
            }
        }

        foreach (
            [
                'minchar' => 'post_minimum_characters',
                'visit_minutes' => 'visit_minutes',
            ] as $setting_key => $group_key
        ) {
            if (get_setting("income_{$setting_key}") !== false) {
                $db->update_query(
                    'usergroups',
                    ["newpoints_income_{$group_key}" => (int)get_setting("income_{$setting_key}")]
                );
            }
        }
    }

    $db->delete_query('newpoints_settings', "plugin='income'");

    settings_rebuild();

    if ($db->field_exists('newpoints_allowance', 'usergroups')) {
        $query = $db->simple_select('usergroups', 'gid, newpoints_allowance');

        while ($group = $db->fetch_array($query)) {
            $group_id = (int)$group['gid'];

            $db->update_query(
                'usergroups',
                [IncomePermissions::UserIncomeUserAllowance => (float)$group['newpoints_allowance']],
                "gid='{$group_id}'"
            );
        }

        $db->drop_column('usergroups', 'newpoints_allowance');
    }

    if ($db->field_exists('newpoints_allowance_period', 'usergroups')) {
        $query = $db->simple_select('usergroups', 'gid, newpoints_allowance_period');

        while ($group = $db->fetch_array($query)) {
            $group_id = (int)$group['gid'];

            $db->update_query(
                'usergroups',
                [IncomePermissions::UserIncomeUserAllowanceMinutes => (int)($group['newpoints_allowance_period'] / 60)],
                "gid='{$group_id}'"
            );
        }

        $db->drop_column('usergroups', 'newpoints_allowance_period');
    }

    if ($db->field_exists('newpoints_allowance_primary_only', 'usergroups')) {
        $query = $db->simple_select('usergroups', 'gid, newpoints_allowance_primary_only');

        while ($group = $db->fetch_array($query)) {
            $group_id = (int)$group['gid'];

            $db->update_query(
                'usergroups',
                [IncomePermissions::UserIncomeUserAllowancePrimaryOnly => (int)$group['newpoints_allowance_primary_only']],
                "gid='{$group_id}'"
            );
        }

        $db->drop_column('usergroups', 'newpoints_allowance_primary_only');
    }

    if ($db->field_exists('newpoints_allowance_last_stamp', 'usergroups')) {
        $query = $db->simple_select('usergroups', 'gid, newpoints_allowance_last_stamp');

        while ($group = $db->fetch_array($query)) {
            $group_id = (int)$group['gid'];

            $db->update_query(
                'usergroups',
                [IncomePermissions::UserIncomeUserAllowanceLastStamp => (int)$group['newpoints_allowance_last_stamp']],
                "gid='{$group_id}'"
            );
        }

        $db->drop_column('usergroups', 'newpoints_allowance_last_stamp');
    }

    if ($db->field_exists('newpoints_rate', 'usergroups')) {
        $query = $db->simple_select('usergroups', 'gid, newpoints_rate');

        while ($group = $db->fetch_array($query)) {
            $group_id = (int)$group['gid'];

            $db->update_query(
                'usergroups',
                [IncomeRates::RateAddition => (float)$group['newpoints_rate']],
                "gid='{$group_id}'"
            );
        }

        $db->drop_column('usergroups', 'newpoints_rate');
    }

    templates_remove(['donate_inline']);

    change_admin_permission('newpoints', 'log', PERMISSION_REMOVE);

    change_admin_permission('newpoints', 'forumrules', PERMISSION_REMOVE);

    change_admin_permission('newpoints', 'grouprules', PERMISSION_REMOVE);

    change_admin_permission('newpoints', 'stats', PERMISSION_REMOVE);

    change_admin_permission('newpoints', 'upgrades', PERMISSION_REMOVE);

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            $instance = instance_object($instance_id);

            $instance->cache_update_group_permissions();

            $instance->cache_update_forum_permissions();
        } catch (Exception $e) {
        }
    }

    cache_update_instances();

    /*~*~* RUN UPDATES END *~*~*/

    $cache->update_usergroups();

    $cache->update_forums();

    $plugins_list['newpoints'] = $plugin_information['versioncode'];

    $cache->update('ougc_plugins', $plugins_list);
}

function plugin_deactivation(): void
{
    foreach (['newpoints', 'backupnewpoints'] as $task_name) {
        task_disable($task_name);
    }

    permissions_update(PERMISSION_DISABLE);
}

function plugin_installation(): void
{
    global $cache;

    plugin_library_load();

    db_verify_tables();

    foreach (instance_get() as $instance_id => $instance_data) {
        try {
            db_verify_columns(
                [
                    'users' => [
                        instance_object($instance_id)->users_column_get() => FIELDS_DATA['users']['newpoints']
                    ]
                ]
            );
        } catch (Exception $e) {
            log_error($instance_id, $e->getMessage());
        }
    }

    db_verify_columns();

    settings_rebuild();

    templates_rebuild();

    //rules_rebuild_cache();

    $cache->update_usergroups();

    $cache->update_forums();
}

function plugin_is_installed(): bool
{
    return db_verify_tables_exists() && db_verify_columns_exists(TABLES_DATA) && db_verify_columns_exists();
}

function plugin_uninstallation(): void
{
    global $db, $PL, $cache;

    plugin_library_load();

    // uninstall plugins
    $plugins_cache = (array)$cache->read('newpoints_plugins');

    $active_plugins = $plugins_cache['active'] ?? [];

    if (!empty($active_plugins)) {
        foreach ($active_plugins as $plugin) {
            // Ignore missing plugins
            if (!file_exists(MYBB_ROOT . 'inc/plugins/newpoints/plugins/' . $plugin . '.php')) {
                continue;
            }

            $plugin_file_path = MYBB_ROOT . "inc/plugins/newpoints/plugins/{$plugin}.php";

            require_once $plugin_file_path;

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
    $cache->delete('newpoints_instances');
    $cache->delete('newpoints_group_permissions');
    $cache->delete('newpoints_forum_permissions');

    db_drop_tables(TABLES_DATA);

    db_drop_columns(FIELDS_DATA);

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

    $PL->settings_delete('newpoints');

    $PL->templates_delete('newpoints');

    my_alerts_uninstall();

    foreach (['newpoints', 'backupnewpoints'] as $task_name) {
        task_delete($task_name);
    }

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
}

function permissions_update(int $action = PERMISSION_ENABLE): bool
{
    change_admin_permission('newpoints', false, $action);

    change_admin_permission('newpoints', 'plugins', $action);

    change_admin_permission('newpoints', 'settings', $action);

    return true;
}

function db_tables(array $tables_objects = TABLES_DATA): array
{
    $tables_data = [];

    foreach ($tables_objects as $table_name => $table_data) {
        foreach ($table_data as $field_name => $field_definition) {
            if (!isset($field_definition['type'])) {
                continue;
            }

            $tables_data[$table_name][$field_name] = db_build_field_definition($field_definition);
        }

        foreach ($table_data as $field_name => $field_definition) {
            if (isset($field_definition['primary_key'])) {
                $tables_data[$table_name]['primary_key'] = $field_name;
            }
            if ($field_name === 'unique_key') {
                $tables_data[$table_name]['unique_key'] = $field_definition;
            }
        }
    }

    return $tables_data;
}

function db_verify_tables(array $tables_objects = TABLES_DATA): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (db_tables($tables_objects) as $table_name => $table_data) {
        if ($db->table_exists($table_name)) {
            foreach ($table_data as $field_name => $field_definition) {
                if ($field_name == 'primary_key' || $field_name == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($field_name, $table_name)) {
                    $db->modify_column($table_name, "`{$field_name}`", $field_definition);
                } else {
                    $db->add_column($table_name, $field_name, $field_definition);
                }
            }
        } else {
            $query_string = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$table_name}` (";

            foreach ($table_data as $field_name => $field_definition) {
                if ($field_name == 'primary_key') {
                    $query_string .= "PRIMARY KEY (`{$field_definition}`)";
                } elseif ($field_name != 'unique_key') {
                    $query_string .= "`{$field_name}` {$field_definition},";
                }
            }

            $query_string .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query_string);
        }
    }

    db_verify_indexes($tables_objects);

    return true;
}

function db_verify_indexes(array $tables_objects = TABLES_DATA): bool
{
    global $db;

    foreach (db_tables($tables_objects) as $table_name => $table_data) {
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

function db_build_field_definition(array $field_definition): string
{
    $definition_string = '';

    $definition_string .= $field_definition['type'];

    if (isset($field_definition['size'])) {
        $definition_string .= "({$field_definition['size']})";
    }

    if (isset($field_definition['unsigned'])) {
        if ($field_definition['unsigned'] === true) {
            $definition_string .= ' UNSIGNED';
        } else {
            $definition_string .= ' SIGNED';
        }
    }

    if (!isset($field_definition['null'])) {
        $definition_string .= ' NOT';
    }

    $definition_string .= ' NULL';

    if (isset($field_definition['auto_increment'])) {
        $definition_string .= ' AUTO_INCREMENT';
    }

    if (isset($field_definition['default'])) {
        $definition_string .= " DEFAULT '{$field_definition['default']}'";
    }

    return $definition_string;
}

function db_verify_columns(array $fields_objects = FIELDS_DATA): bool
{
    global $db;

    foreach ($fields_objects as $table_name => $table_data) {
        if (!$db->table_exists($table_name)) {
            continue;
        }

        foreach ($table_data as $field_name => $field_definition) {
            if (!isset($field_definition['type'])) {
                continue;
            }

            if ($db->field_exists($field_name, $table_name)) {
                $db->modify_column($table_name, "`{$field_name}`", db_build_field_definition($field_definition));
            } else {
                $db->add_column($table_name, $field_name, db_build_field_definition($field_definition));
            }
        }
    }

    return true;
}

function db_verify_tables_exists(array $tables_objects = TABLES_DATA): bool
{
    global $db;

    $is_installed_each = true;

    foreach (db_tables($tables_objects) as $table_name => $table_data) {
        $is_installed_each = $db->table_exists($table_name) && $is_installed_each;
    }

    return $is_installed_each;
}

function db_verify_columns_exists(array $fields_objects = FIELDS_DATA): bool
{
    global $db;

    $is_installed_each = true;

    foreach ($fields_objects as $table_name => $table_data) {
        if (!$db->table_exists($table_name)) {
            $is_installed_each = false;

            continue;
        }

        foreach ($table_data as $field_name => $field_definition) {
            if (!isset($field_definition['type'])) {
                continue;
            }

            $is_installed_each = $db->field_exists($field_name, $table_name) && $is_installed_each;
        }
    }

    return $is_installed_each;
}

function db_drop_tables(array $tables_objects = TABLES_DATA): bool
{
    global $db;

    foreach ($tables_objects as $table_name => $table_data) {
        $db->drop_table($table_name);
    }

    return true;
}

function db_drop_columns(array $tables_objects = FIELDS_DATA): bool
{
    global $db;

    foreach ($tables_objects as $table_name => $table_data) {
        if ($db->table_exists($table_name)) {
            foreach ($table_data as $field_name => $field_definition) {
                if ($db->field_exists($field_name, $table_name)) {
                    $db->drop_column($table_name, $field_name);
                }
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

function permission_enable(string $plugin_code): bool
{
    change_admin_permission('newpoints', 'newpoints_' . $plugin_code, 1);

    return true;
}

function permission_delete(string $plugin_code): bool
{
    change_admin_permission('newpoints', 'newpoints_' . $plugin_code, -1);

    return true;
}

function recount_rebuild_newpoints_recount_from_logs(): void
{
    global $db, $mybb, $lang;

    try {
        $instance = instance_object(
            $mybb->get_input('newpoints_recount_from_logs_instance_id', MyBB::INPUT_INT)
        );
    } catch (Exception $e) {
        log_error(
            $mybb->get_input('newpoints_recount_from_logs_instance_id', MyBB::INPUT_INT),
            $e->getMessage()
        );

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=tools-recount_rebuild');

        exit;
    }

    $query = $db->simple_select('users', 'COUNT(uid) as total_users');

    $total_users = $db->fetch_field($query, 'total_users');

    $page = $mybb->get_input('page', MyBB::INPUT_INT);

    $per_page = $mybb->get_input('newpoints_recount_from_logs', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;

    $end = $start + $per_page;

    $query = $db->simple_select(
        'users',
        'uid',
        '',
        ['order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page]
    );

    $log_type_income = LOGGING_TYPE_INCOME;

    $log_type_charge = LOGGING_TYPE_CHARGE;

    while ($user_data = $db->fetch_array($query)) {
        $user_id = (int)$user_data['uid'];

        $total_income = (float)($db->fetch_field(
            $db->simple_select(
                'newpoints_log',
                'SUM(points) AS total_income',
                "uid='{$user_id}' AND log_type='{$log_type_income}' AND instance_id='{$instance->instance_id}'",
            ),
            'total_income'
        ) ?? 0);

        $total_charges = (float)($db->fetch_field(
            $db->simple_select(
                'newpoints_log',
                'SUM(points) AS total_charges',
                "uid='{$user_id}' AND log_type='{$log_type_charge}' AND instance_id='{$instance->instance_id}'",
            ),
            'total_charges'
        ) ?? 0);

        user_update($user_id, [$instance->users_column_get() => $total_income - $total_charges]);
    }

    check_proceed(
        $total_users,
        $end,
        ++$page,
        $per_page,
        'newpoints_recount_from_logs_instance_id" value="' . $mybb->get_input(
            'newpoints_recount_from_logs_instance_id',
            MyBB::INPUT_INT
        ) . '" /><input type="hidden" name="newpoints_recount_from_logs',
        'do_recount_newpoints_from_logs',
        $lang->sprintf(
            $lang->newpoints_recount_from_logs_success,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
        )
    );
}

// todo refactor to consider instances
function recount_rebuild_newpoints_recount(): void
{
    global $db, $mybb, $lang;

    try {
        $instance = instance_object(
            $mybb->get_input('newpoints_recount_from_settings_instance_id', MyBB::INPUT_INT)
        );
    } catch (Exception $e) {
        log_error(
            $mybb->get_input('newpoints_recount_from_settings_instance_id', MyBB::INPUT_INT),
            $e->getMessage()
        );

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=tools-recount_rebuild');

        exit;
    }

    $query = $db->simple_select('users', 'COUNT(uid) as total_users');

    $total_users = $db->fetch_field($query, 'total_users');

    $page = $mybb->get_input('page', MyBB::INPUT_INT);

    $per_page = $mybb->get_input('newpoints_recount_from_settings', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;

    $end = $start + $per_page;

    $query = $db->simple_select(
        'users',
        'uid, usergroup, additionalgroups',
        '',
        ['order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page]
    );

    while ($user_data = $db->fetch_array($query)) {
        $user_id = (int)$user_data['uid'];

        try {
            $user_instance = instance_object($instance->instance_id, $user_id);
        } catch (Exception $e) {
            log_error($instance->instance_id, $e->getMessage(), user_id: $user_id);

            continue;
        }

        $points = 0;

        if (!$user_instance->get_user_permission_rate_addition()) {
            //continue;
        }

        $first_posts = [];

        $threads_query = $db->simple_select(
            'threads',
            'firstpost, fid, poll, uid',
            "uid='" . $user_id . "' AND visible=1"
        );

        while ($thread = $db->fetch_array($threads_query)) {
            $forum_id = (int)$thread['fid'];

            $user_instance->set_forum($forum_id);

            if (!$user_instance->get_income_value(INCOME_TYPE_THREAD)) {
                continue;
            }

            if (($character_count = my_strlen(
                    $mybb->get_input('message')
                )) >= $user_instance->user_permissions[IncomePermissions::UserIncomePostMinimumCharacters]) {
                $bonus = $character_count * $user_instance->get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            $points += ($user_instance->get_income_value(INCOME_TYPE_THREAD) + $bonus);

            if (!empty($thread['poll'])) {
                $points += $user_instance->get_income_value(INCOME_TYPE_POLL);
            }

            $first_posts[] = (int)$thread['firstpost'];
        }

        $posts_query = $db->simple_select(
            'posts',
            'tid,fid,message,uid, pid',
            "uid='{$user_id}' AND pid NOT IN('" . implode("','", $first_posts) . "') AND visible=1"
        );

        while ($post_data = $db->fetch_array($posts_query)) {
            $post_id = (int)$post_data['pid'];

            $thread_id = (int)$post_data['tid'];

            $forum_id = (int)$post_data['fid'];

            $user_instance->set_forum($forum_id);

            if (!$user_instance->get_income_value(INCOME_TYPE_POST)) {
                continue;
            }

            if (($character_count = my_strlen(
                    $post_data['message']
                )) >= $user_instance->user_permissions[IncomePermissions::UserIncomePostMinimumCharacters]) {
                $bonus = $character_count *
                    $user_instance->get_income_value(INCOME_TYPE_POST_CHARACTER);
            } else {
                $bonus = 0;
            }

            $points += ($user_instance->get_income_value(INCOME_TYPE_POST) + $bonus);

            $thread_data = get_thread($post_data['tid']);

            $thread_user_id = (int)$thread_data['uid'];

            $forum_id = (int)$post_data['fid'];

            if ($thread_user_id !== $user_id) {
                try {
                    $user_instance = instance_object($user_instance->instance_id, $thread_user_id)
                        ->set_forum($forum_id);
                } catch (Exception $e) {
                    log_error(
                        $instance->instance_id,
                        $e->getMessage(),
                        user_id: $thread_user_id,
                        forum_id: $forum_id
                    );

                    continue;
                }

                if ($user_instance->get_user_permission_boolean(Permissions::CanGetPoints)) {
                    $income_value = $user_instance->get_income_value(INCOME_TYPE_THREAD_REPLY);

                    if ($income_value) {
                        try {
                            $user_instance->points_addition($income_value)
                                ->logger->log_income(
                                    'income_' . INCOME_TYPE_THREAD_REPLY,
                                    $income_value,
                                    $post_id,
                                    $thread_id,
                                    $forum_id,
                                );
                        } catch (Exception $e) {
                            log_error(
                                $user_instance->instance_id,
                                $e->getMessage(),
                                user_id: $user_instance->get_user_id(),
                                post_id: $user_instance->get_post_id(),
                                thread_id: $user_instance->get_thread_id(),
                                forum_id: $user_instance->get_forum_id(),
                            );

                            continue;
                        }
                    }
                }
            }
        }

        $query_polls = $db->simple_select(
            "pollvotes v LEFT JOIN {$db->table_prefix}polls p ON (p.pid=v.pid) LEFT JOIN {$db->table_prefix}threads t ON (t.tid=p.tid)",
            'p.tid, t.fid',
            "v.uid='{$user_id}'"
        );

        while ($vote_data = $db->fetch_array($query_polls)) {
            $forum_id = (int)$vote_data['fid'];

            $user_instance->set_forum($forum_id);

            $income_value = $user_instance->get_income_value(INCOME_TYPE_POLL_VOTE);

            if ($income_value) {
                $points += $income_value;
            }
        }

        $income_value = $user_instance->get_income_value(INCOME_TYPE_PRIVATE_MESSAGE);

        if ($income_value) {
            $pms_sent = $db->fetch_field(
                $db->simple_select(
                    'privatemessages',
                    'COUNT(pmid) AS numpms',
                    "fromid='{$user_id}' AND toid!='{$user_id}' AND receipt!='1'"
                ),
                'numpms'
            );

            $points += $pms_sent * $income_value;
        }

        $user_instance->set_forum(0);

        $db->update_query(
            'users',
            [
                $user_instance->users_column_get() =>
                    $user_instance->get_income_value(INCOME_TYPE_USER_REGISTRATION) + $points
            ],
            "uid='{$user_id}'"
        );
    }

    check_proceed(
        $total_users,
        $end,
        ++$page,
        $per_page,
        'newpoints_recount_from_settings_instance_id" value="' . $mybb->get_input(
            'newpoints_recount_from_settings_instance_id',
            MyBB::INPUT_INT
        ) . '" /><input type="hidden" name="newpoints_recount_from_settings',
        'do_recount_newpoints',
        $lang->sprintf(
            $lang->newpoints_recount_from_logs_success,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
        )
    );
}

function recount_rebuild_newpoints_reset(): void
{
    global $db, $mybb, $lang;

    try {
        $instance = instance_object($mybb->get_input('newpoints_reset_instance_id', MyBB::INPUT_INT));
    } catch (Exception $e) {
        log_error(
            $mybb->get_input('newpoints_reset_instance_id', MyBB::INPUT_INT),
            $e->getMessage(),
        );

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=tools-recount_rebuild');

        exit;
    }

    $query = $db->simple_select('users', 'COUNT(uid) as total_users');

    $total_users = $db->fetch_field($query, 'total_users');

    $page = $mybb->get_input('page', MyBB::INPUT_INT);

    $per_page = $mybb->get_input('newpoints_reset', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;

    $end = $start + $per_page;

    $query = $db->simple_select(
        'users',
        'uid,usergroup,additionalgroups',
        '',
        ['order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page]
    );

    while ($user_data = $db->fetch_array($query)) {
        $user_id = (int)$user_data['uid'];

        $db->update_query(
            'users',
            [
                $instance->users_column_get() => $mybb->get_input(
                    'newpoints_reset_amount',
                    MyBB::INPUT_FLOAT
                )
            ],
            "uid='{$user_id}'"
        );
    }

    check_proceed(
        $total_users,
        $end,
        ++$page,
        $per_page,
        'newpoints_reset_instance_id" value="' . $mybb->get_input(
            'newpoints_reset_instance_id',
            MyBB::INPUT_INT
        ) . '" /><input type="hidden" name="newpoints_reset_amount" value="' . $mybb->get_input(
            'newpoints_reset_amount',
            MyBB::INPUT_FLOAT
        ) . '" /><input type="hidden" name="newpoints_reset',
        'do_reset_newpoints',
        $lang->sprintf(
            $lang->newpoints_reset_success,
            $instance->get_display_name_upper(),
            $instance->get_display_name_lower(),
        )
    );
}

function my_alerts_install(): bool
{
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        global $db, $mybb;
        global $alertTypeManager;

        isset($alertTypeManager) || $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance(
            $db,
            $mybb->cache
        );

        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        $newpoints_my_alerts_formatters = [
            0 => [
                'plugin_code' => 'core',
                'alert_types' => ['add_points', 'subtract_points', 'donation_received'],
            ]
        ];

        $hook_arguments = [
            'newpoints_my_alerts_formatters' => &$newpoints_my_alerts_formatters,
        ];

        $hook_arguments = run_hooks('my_alerts_install', $hook_arguments);

        foreach ($newpoints_my_alerts_formatters as $formatter_key => &$formatter_data) {
            if (is_string($formatter_data['plugin_code']) && !empty($formatter_data['plugin_code'])) {
                $formatter_data['plugin_code'] = trim("{$formatter_data['plugin_code']}_");
            }

            if (!empty($formatter_data['plugin_code'])) {
                foreach ($formatter_data['alert_types'] as $object_key => &$alert_type) {
                    $alertType = new MybbStuff_MyAlerts_Entity_AlertType();

                    $alertType->setCode("newpoints_{$formatter_data['plugin_code']}{$alert_type}");

                    $alertType->setEnabled();

                    $alertType->setCanBeUserDisabled();

                    $alertTypeManager->add($alertType);
                }
            }
        }

        return true;
    }

    return false;
}

function my_alerts_uninstall(): bool
{
    if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        global $db, $mybb;
        global $alertTypeManager;

        isset($alertTypeManager) || $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::createInstance(
            $db,
            $mybb->cache
        );

        $alertTypeManager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();

        $newpoints_my_alerts_formatters = [
            0 => [
                'plugin_code' => 'core',
                'alert_types' => ['add_points', 'subtract_points', 'donation_received'],
            ]
        ];

        $hook_arguments = [
            'newpoints_my_alerts_formatters' => &$newpoints_my_alerts_formatters,
        ];

        $hook_arguments = run_hooks('my_alerts_uninstall', $hook_arguments);

        foreach ($newpoints_my_alerts_formatters as $formatter_key => &$formatter_data) {
            if (is_string($formatter_data['plugin_code']) && !empty($formatter_data['plugin_code'])) {
                $formatter_data['plugin_code'] = trim("{$formatter_data['plugin_code']}_");
            }

            if (!empty($formatter_data['plugin_code'])) {
                foreach ($formatter_data['alert_types'] as $object_key => &$alert_type) {
                    $alertTypeManager->deleteByCode(
                        "newpoints_{$formatter_data['plugin_code']}{$alert_type}"
                    );
                }
            }
        }
    }

    return false;
}

function build_permissions_row(
    int $instance_id,
    Form $form,
    string $field_name,
    array $field_definition,
    string $key,
    string $section = 'Main',
    bool $extra_text = false,
    string $language_prefix = 'newpoints_admin_instances_edit_'
): string {
    global $mybb, $lang;

    $form_input = '';

    $options = ['id' => $field_name, 'class' => $field_definition['form_class'] ?? ''];

    if (isset($field_definition['is_disabled']) && $field_definition['is_disabled']($instance_id) === true) {
        $options['id'] .= '" disabled="disabled';
    }

    switch ($field_definition['form_type']) {
        case FORM_TYPE_TEXT_FIELD:
            if ($extra_text) {
                $form_input .= '<div class="group_settings_bit">';

                $form_input .= $lang->{$language_prefix . $section . $key};

                $form_input .= '<br /><small class="input">';

                $form_input .= $lang->{$language_prefix . $section . $key . 'Description'};

                $form_input .= '</small><br />';
            }

            $options['max'] = $field_definition['size'];

            $form_input .= $form->generate_text_box(
                $field_name,
                $mybb->get_input($field_name),
                $options
            );

            if ($extra_text) {
                $form_input .= '</div>';
            }

            break;
        case FORM_TYPE_NUMERIC_FIELD:
            if ($extra_text) {
                $form_input .= '<div class="group_settings_bit">';

                $form_input .= $lang->{$language_prefix . $section . $key};

                $form_input .= '<br /><small class="input">';

                $form_input .= $lang->{$language_prefix . $section . $key . '_description'};

                $form_input .= '</small><br />';
            }

            $form_input .= $form->generate_numeric_field(
                $field_name,
                $mybb->get_input($field_name, MyBB::INPUT_INT),
                $options
            );

            if ($extra_text) {
                $form_input .= '</div>';
            }

            break;
        case FORM_TYPE_YES_NO_FIELD:
            $form_input .= $form->generate_yes_no_radio(
                $field_name,
                $mybb->get_input($field_name, MyBB::INPUT_INT),
                $options
            );

            break;
        case FORM_TYPE_SELECT_FIELD:
            if ($extra_text) {
                $form_input .= '<div class="group_settings_bit">';

                $form_input .= $lang->{$language_prefix . $section . $key};

                $form_input .= '<br /><small class="input">';

                $form_input .= $lang->{$language_prefix . $section . $key . '_description'};

                $form_input .= '</small><br />';
            }

            $form_input .= $form->generate_select_box(
                $field_name,
                isset($field_definition['form_function']) ? $field_definition['form_function'](
                ) : $field_definition['form_array'],
                [$mybb->get_input($field_name, MyBB::INPUT_INT)],
                $options
            );

            if ($extra_text) {
                $form_input .= '</div>';
            }

            break;
        case FORM_TYPE_CHECK_BOX:
            $form_input .= '<div class="user_settings_bit">';

            $options['checked'] = $mybb->get_input($field_name, MyBB::INPUT_INT);

            $form_input .= $form->generate_check_box(
                $field_name,
                1,
                $lang->{$language_prefix . $section . $key},
                $options
            );

            $form_input .= '</div>';

            break;
    }

    return $form_input;
}

/**
 * @param int $group_id
 *
 * @return string
 */
function retrieve_single_group_permissions_row(int $group_id, int $instance_id): string
{
    global $mybb, $lang;
    global $tables_data, $groups_cache;
    global $url;

    try {
        $instance = instance_object($instance_id);
    } catch (InvalidArgumentException $e) {
        log_error($instance_id, $e->getMessage());

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');

        exit;
    }

    $group_data = $groups_cache[$group_id];

    $existing_permissions = [];

    foreach (
        $instance->permissions_group_get(
            ["instance_id='{$instance->instance_id}'"],
            array_keys($tables_data['newpoints_group_permissions'])
        ) as $existing
    ) {
        $existing_permissions[$existing['group_id']] = $existing;
    }

    $permissions_cache = $instance->cache_get_group_permissions();

    $field_list = [];

    foreach ($tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
        if (!isset($field_definition['is_permission']) || empty($field_definition['dragging_permission'])) {
            continue;
        }

        $language_key = str_replace('newpoints_', '', $field_name);

        $field_list[$field_name] = $lang->{'newpoints_permission_group_' . $language_key};
    }

    $form = new Form('', '', '', 0, '', true);

    $form_container = new FormContainer();

    $permissions = [];

    if ($existing_permissions[$group_id]) {
        $permissions = $existing_permissions[$group_id];

        $default_checked = false;
    } elseif ($permissions_cache[$instance->instance_id][$group_id]) {
        $permissions = $permissions_cache[$instance->instance_id][$group_id];

        $default_checked = true;
    }

    if (!$permissions) {
        $permissions = $group_data;

        $default_checked = true;
    }

    $perms_checked = [];

    foreach ($field_list as $forum_permission => $forum_perm_title) {
        if ($permissions[$forum_permission] == 1) {
            $perms_checked[$forum_permission] = 1;
        } else {
            $perms_checked[$forum_permission] = 0;
        }
    }

    $group_title = htmlspecialchars_uni($group_data['title']);

    if (!empty($default_checked)) {
        $inherited_text = $lang->newpoints_admin_instances_permissions_form_inherited;
    } else {
        $inherited_text = $lang->newpoints_admin_instances_permissions_form_custom;
    }

    $form_container->output_cell(
        "<strong>{$group_title}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>"
    );

    $field_select = "<div class=\"quick_perm_fields\">\n";

    $field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_group_{$group_id}\">\n";

    foreach ($perms_checked as $perm => $value) {
        if ($value == 1) {
            $field_select .= "<li id=\"field-{$perm}\">{$field_list[$perm]}</li>";
        }
    }

    $field_select .= "</ul></div>\n";

    $field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_group_{$group_id}\">\n";

    foreach ($perms_checked as $perm => $value) {
        if ($value == 0) {
            $field_select .= "<li id=\"field-{$perm}\">{$field_list[$perm]}</li>";
        }
    }

    $field_select .= "</ul></div></div>\n";

    $field_select .= $form->generate_hidden_field(
        'fields_group_' . $group_id,
        implode(',', array_keys($perms_checked, 1)),
        ['id' => 'fields_group_' . $group_id]
    );

    $field_select = str_replace("\n", '', $field_select);

    $form_container->output_cell($field_select, ['colspan' => 2]);

    $permissions_url = $url->build(
            [
                'action' => 'group_permissions',
                'instance_id' => $instance->instance_id,
                'permission_id' => $permissions['permission_id'] ?? 0,
                'group_id' => $group_id
            ]
        ) . '#tab_group_permissions';

    $modal_url = $url->build(
        [
            'action' => 'group_permissions',
            'instance_id' => $instance->instance_id,
            'permission_id' => $permissions['permission_id'] ?? 0,
            'group_id' => $group_id,
            'ajax' => 1
        ]
    );

    if (empty($default_checked)) {
        $form_container->output_cell(
            "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_edit}</a>",
            ['class' => 'align_center']
        );

        $clear_group_permissions_url = $url->build(
            [
                'action' => 'clear_group_permission',
                'instance_id' => $instance->instance_id,
                'permission_id' => $permissions['permission_id'],
                'my_post_key' => $mybb->post_code
            ]
        );

        $form_container->output_cell(
            "<a href=\"{$clear_group_permissions_url}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->newpoints_admin_instances_permissions_clear_confirm}')\">{$lang->newpoints_admin_instances_permissions_form_clear}</a>",
            ['class' => 'align_center']
        );
    } else {
        $form_container->output_cell(
            "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_set}</a>",
            ['class' => 'align_center', 'colspan' => 2]
        );
    }

    $form_container->construct_row();

    return $form_container->output_row_cells(0, true);
}

/**
 * @param int $instance_id
 */
function save_quick_group_permissions(int $instance_id, array $permissions_data): void
{
    global $db, $inherit, $cache;
    global $tables_data, $groups_cache;

    try {
        $instance = instance_object($instance_id);
    } catch (InvalidArgumentException $e) {
        log_error($instance_id, $e->getMessage());

        return;
    }

    $permissions_cache = $instance->cache_get_group_permissions();

    $permission_fields = [];

    foreach ($tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
        if (!isset($field_definition['is_permission']) || empty($field_definition['dragging_permission'])) {
            continue;
        }

        $permission_fields[$field_name] = $field_definition['default'];
    }

    foreach ($groups_cache as $group_data) {
        $group_id = (int)$group_data['gid'];

        $existing_permissions = [];

        foreach ($permissions_cache[$instance->instance_id] ?? [] as $instance_permissions) {
            $existing_permissions[$instance_permissions['group_id']] = $instance_permissions;
        }

        if (!$existing_permissions) {
            foreach ($permission_fields as $field => $value) {
                $existing_permissions[$field] = $group_data[$field];
            }
        }

        $instance->permissions_group_delete(
            (int)($instance->permissions_group_get(
                ["instance_id='{$instance->instance_id}'", "group_id='{$group_id}'"],
                query_options: ['limit' => 1]
            )['permission_id'] ?? 0)
        );

        // Only insert the new ones if we're using group permissions
        if (empty($inherit[$group_id])) {
            $insert_data = [
                'instance_id' => $instance->instance_id,
                'group_id' => $group_id,
            ];

            foreach ($permissions_data as $permissions_name => $permissions_value) {
                if (isset($permissions_value[$group_id])) {
                    $insert_data[$permissions_name] = $permissions_value[$group_id];
                }
            }

            foreach ($permission_fields as $permissions_name => $value) {
                if (isset($insert_data[$permissions_name])) {
                    continue;
                }

                $insert_data[$permissions_name] = isset($existing_permissions[$permissions_name]) ? (int)$existing_permissions[$permissions_name] : 0;
            }

            $instance->permissions_group_insert($insert_data);
        }
    }

    $cache->update_usergroups();

    $cache->update_forumpermissions();
}

/**
 * @param int $group_id
 *
 * @return string
 */
function retrieve_single_forum_permissions_row(int $forum_id, int $instance_id): string
{
    global $mybb, $lang;
    global $tables_data, $forums_cache;
    global $url;

    try {
        $instance = instance_object($instance_id);
    } catch (InvalidArgumentException $e) {
        log_error($instance_id, $e->getMessage());

        flash_message($e->getMessage(), 'error');

        admin_redirect('index.php?module=newpoints-instances');

        exit;
    }

    $forum_data = $forums_cache[$forum_id];

    $existing_permissions = [];

    foreach (
        $instance->permissions_forum_get(
            ["instance_id='{$instance->instance_id}'"],
            array_keys($tables_data['newpoints_forum_permissions'])
        ) as $existing
    ) {
        $existing_permissions[$existing['forum_id']] = $existing;
    }

    $permissions_cache = $instance->cache_get_group_permissions();

    $field_list = [];

    foreach ($tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
        if (!isset($field_definition['is_permission']) || empty($field_definition['dragging_permission'])) {
            continue;
        }

        $language_key = str_replace('newpoints_', '', $field_name);

        $field_list[$field_name] = $lang->{'newpoints_permissions_forum_' . $language_key};
    }

    $form = new Form('', '', '', 0, '', true);

    $form_container = new FormContainer();

    $permissions = [];

    if ($existing_permissions[$forum_id]) {
        $permissions = $existing_permissions[$forum_id];

        $default_checked = false;
    } elseif ($permissions_cache[$instance->instance_id][$forum_id]) {
        $permissions = $permissions_cache[$instance->instance_id][$forum_id];

        $default_checked = true;
    }

    if (!$permissions) {
        $permissions = $forum_data;

        $default_checked = true;
    }

    $perms_checked = [];

    foreach ($field_list as $forum_permission => $forum_perm_title) {
        if ($permissions[$forum_permission] == 1) {
            $perms_checked[$forum_permission] = 1;
        } else {
            $perms_checked[$forum_permission] = 0;
        }
    }

    $forum_title = strip_tags($forum_data['name']);

    if (!empty($default_checked)) {
        $inherited_text = $lang->newpoints_admin_instances_permissions_form_inherited;
    } else {
        $inherited_text = $lang->newpoints_admin_instances_permissions_form_custom;
    }

    $form_container->output_cell(
        "<strong>{$forum_title}</strong> <small style=\"vertical-align: middle;\">({$inherited_text})</small>"
    );

    $field_select = "<div class=\"quick_perm_fields\">\n";

    $field_select .= "<div class=\"enabled\"><ul id=\"fields_enabled_forum_{$forum_id}\">\n";

    foreach ($perms_checked as $perm => $value) {
        if ($value == 1) {
            $field_select .= "<li id=\"field-{$perm}\">{$field_list[$perm]}</li>";
        }
    }

    $field_select .= "</ul></div>\n";

    $field_select .= "<div class=\"disabled\"><ul id=\"fields_disabled_forum_{$forum_id}\">\n";

    foreach ($perms_checked as $perm => $value) {
        if ($value == 0) {
            $field_select .= "<li id=\"field-{$perm}\">{$field_list[$perm]}</li>";
        }
    }

    $field_select .= "</ul></div></div>\n";

    $field_select .= $form->generate_hidden_field(
        'fields_forum_' . $forum_id,
        implode(',', array_keys($perms_checked, 1)),
        ['id' => 'fields_forum_' . $forum_id]
    );

    $field_select = str_replace("\n", '', $field_select);

    $form_container->output_cell($field_select, ['colspan' => 2]);

    $permissions_url = $url->build(
            [
                'action' => 'forum_permissions',
                'instance_id' => $instance->instance_id,
                'permission_id' => $permissions['permission_id'] ?? 0,
                'forum_id' => $forum_id
            ]
        ) . '#tab_forum_permissions';

    $modal_url = $url->build(
        [
            'action' => 'forum_permissions',
            'instance_id' => $instance->instance_id,
            'permission_id' => $permissions['permission_id'] ?? 0,
            'forum_id' => $forum_id,
            'ajax' => 1
        ]
    );

    if (empty($default_checked)) {
        $form_container->output_cell(
            "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_edit}</a>",
            ['class' => 'align_center']
        );

        $clear_forum_permissions_url = $url->build(
            [
                'action' => 'clear_forum_permission',
                'instance_id' => $instance->instance_id,
                'permission_id' => $permissions['permission_id'],
                'my_post_key' => $mybb->post_code
            ]
        );

        $form_container->output_cell(
            "<a href=\"{$clear_forum_permissions_url}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->newpoints_admin_instances_permissions_clear_confirm}')\">{$lang->newpoints_admin_instances_permissions_form_clear}</a>",
            ['class' => 'align_center']
        );
    } else {
        $form_container->output_cell(
            "<a href=\"{$permissions_url}\" onclick=\"MyBB.popupWindow('{$modal_url}', null, true); return false;\">{$lang->newpoints_admin_instances_permissions_form_set}</a>",
            ['class' => 'align_center', 'colspan' => 2]
        );
    }

    $form_container->construct_row();

    return $form_container->output_row_cells(0, true);
}

/**
 * @param int $instance_id
 */
function save_quick_forum_permissions(int $instance_id, array $permissions_data): void
{
    global $db, $inherit, $cache;
    global $tables_data, $forums_cache;

    try {
        $instance = instance_object($instance_id);
    } catch (InvalidArgumentException $e) {
        log_error($instance_id, $e->getMessage());

        return;
    }

    $permissions_cache = $instance->cache_get_forum_permissions();

    $permission_fields = [];

    foreach ($tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
        if (!isset($field_definition['is_permission']) || empty($field_definition['dragging_permission'])) {
            continue;
        }

        $permission_fields[$field_name] = $field_definition['default'];
    }

    foreach ($forums_cache as $forum_data) {
        $forum_id = (int)$forum_data['fid'];

        $existing_permissions = [];

        foreach ($permissions_cache[$instance->instance_id] ?? [] as $instance_permissions) {
            $existing_permissions[$instance_permissions['forum_id']] = $instance_permissions;
        }

        if (!$existing_permissions) {
            foreach ($permission_fields as $field => $value) {
                $forum_permissions = fetch_forum_permissions(
                    $forum_id,
                    '',
                    []
                );

                $existing_permissions[$field] = $forum_permissions[$field] ?? TABLES_DATA['newpoints_forum_permissions'][$field]['default'];
            }
        }

        $instance->permissions_forum_delete(
            (int)($instance->permissions_forum_get(
                ["instance_id='{$instance->instance_id}'", "forum_id='{$forum_id}'"],
                query_options: ['limit' => 1]
            )['permission_id'] ?? 0)
        );

        // Only insert the new ones if we're using forum permissions
        if (empty($inherit[$forum_id])) {
            $insert_data = [
                'instance_id' => $instance->instance_id,
                'forum_id' => $forum_id,
            ];

            foreach ($permissions_data as $permissions_name => $permissions_value) {
                if (isset($permissions_value[$forum_id])) {
                    $insert_data[$permissions_name] = $permissions_value[$forum_id];
                }
            }

            foreach ($permission_fields as $permissions_name => $value) {
                if (isset($insert_data[$permissions_name])) {
                    continue;
                }

                $insert_data[$permissions_name] = isset($existing_permissions[$permissions_name]) ? (int)$existing_permissions[$permissions_name] : 0;
            }

            $instance->permissions_forum_insert($insert_data);
        }
    }

    $cache->update_usergroups();

    $cache->update_forumpermissions();
}