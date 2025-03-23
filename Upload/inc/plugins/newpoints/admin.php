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

namespace Newpoints\Admin;

use MyBB;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_AlertType;
use PluginLibrary;
use stdClass;

use function Newpoints\Core\get_income_value;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\language_load;
use function Newpoints\Core\log_add;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\rules_get_all;
use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\settings_rebuild;
use function Newpoints\Core\task_delete;
use function Newpoints\Core\task_disable;
use function Newpoints\Core\task_enable;
use function Newpoints\Core\templates_rebuild;
use function Newpoints\Core\user_can_get_points;
use function Newpoints\Core\user_update;
use function Newpoints\Core\users_get_group_permissions;

use const Newpoints\Core\FIELDS_DATA;
use const Newpoints\Core\INCOME_TYPE_POLL;
use const Newpoints\Core\INCOME_TYPE_POLL_VOTE;
use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\INCOME_TYPE_USER_REFERRAL;
use const Newpoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;
use const Newpoints\Core\TABLES_DATA;
use const Newpoints\ROOT;

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

function plugin_activation(): bool
{
    // todo: remove old templates from the global templates set
    global $db, $cache, $mybb;

    language_load();

    $plugin_information = plugin_information();

    plugin_library_load();

    db_verify_tables();

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
            'newpoints' => ['title' => 'NewPoints', 'description' => 'Handles Newpoints automatic features.'],
            'backupnewpoints' => [
                'title' => 'Backup NewPoints',
                'description' => "Creates a backup of NewPoints default tables and users's points."
            ]
        ] as $task_name => $task_data
    ) {
        task_enable($task_name, $task_data['title'], $task_data['description']);
    }

    permissions_update();

    rules_rebuild_cache();

    my_alerts_install();

    /*~*~* RUN UPDATES START *~*~*/

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
                ['newpoints_income_user_allowance' => (float)$group['newpoints_allowance']],
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
                ['newpoints_income_user_allowance_minutes' => (int)($group['newpoints_allowance_period'] / 60)],
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
                ['newpoints_income_user_allowance_primary_only' => (int)$group['newpoints_allowance_primary_only']],
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
                ['newpoints_income_user_allowance_last_stamp' => (int)$group['newpoints_allowance_last_stamp']],
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
                ['newpoints_rate_addition' => (float)$group['newpoints_rate']],
                "gid='{$group_id}'"
            );
        }

        $db->drop_column('usergroups', 'newpoints_rate');
    }

    /*~*~* RUN UPDATES END *~*~*/

    $cache->update_usergroups();

    $cache->update_forums();

    $plugins_list['newpoints'] = $plugin_information['versioncode'];

    $cache->update('ougc_plugins', $plugins_list);

    return true;
}

function plugin_deactivation(): bool
{
    foreach (['newpoints', 'backupnewpoints'] as $task_name) {
        task_disable($task_name);
    }

    permissions_update(PERMISSION_DISABLE);

    return true;
}

function plugin_installation(): bool
{
    global $cache;

    plugin_library_load();

    db_verify_tables();

    db_verify_columns();

    settings_rebuild();

    templates_rebuild();

    rules_rebuild_cache();

    $cache->update_usergroups();

    $cache->update_forums();

    return true;
}

function plugin_is_installed(): bool
{
    return db_verify_tables_exists() && db_verify_columns_exists(TABLES_DATA) && db_verify_columns_exists();
}

function plugin_uninstallation(): bool
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

    return true;
}

function permissions_update(int $action = PERMISSION_ENABLE): bool
{
    change_admin_permission('newpoints', false, $action);
    change_admin_permission('newpoints', 'plugins', $action);
    change_admin_permission('newpoints', 'settings', $action);
    change_admin_permission('newpoints', 'log', $action);
    change_admin_permission('newpoints', 'forumrules', $action);
    change_admin_permission('newpoints', 'grouprules', $action);
    change_admin_permission('newpoints', 'stats', $action);
    change_admin_permission('newpoints', 'upgrades', $action);

    return true;
}

function db_tables(array $tables_objects = TABLES_DATA): array
{
    $tables_data = [];

    foreach ($tables_objects as $table_name => $table_columns) {
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

function db_verify_tables(array $tables_objects = TABLES_DATA): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (db_tables($tables_objects) as $table_name => $table_columns) {
        if ($db->table_exists($table_name)) {
            foreach ($table_columns as $field_name => $field_data) {
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

            foreach ($table_columns as $field_name => $field_data) {
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

    db_verify_indexes($tables_objects);

    return true;
}

function db_verify_indexes(array $tables_objects = TABLES_DATA): bool
{
    global $db;

    foreach (db_tables($tables_objects) as $table_name => $table_columns) {
        if (!$db->table_exists($table_name)) {
            continue;
        }

        if (isset($table_columns['unique_key'])) {
            foreach ($table_columns['unique_key'] as $key_name => $key_value) {
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

function db_verify_columns(array $fields_objects = FIELDS_DATA): bool
{
    global $db;

    foreach ($fields_objects as $table_name => $table_columns) {
        if (!$db->table_exists($table_name)) {
            continue;
        }

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

    foreach ($fields_objects as $table_name => $table_columns) {
        if (!$db->table_exists($table_name)) {
            $is_installed_each = false;

            continue;
        }

        foreach ($table_columns as $field_name => $field_data) {
            if (!isset($field_data['type'])) {
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

    foreach ($tables_objects as $table_name => $table_columns) {
        $db->drop_table($table_name);
    }

    return true;
}

function db_drop_columns(array $tables_objects = FIELDS_DATA): bool
{
    global $db;

    foreach ($tables_objects as $table_name => $table_columns) {
        if ($db->table_exists($table_name)) {
            foreach ($table_columns as $field_name => $field_data) {
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

function recount_rebuild_newpoints_recount_from_logs()
{
    global $db, $mybb, $lang;

    $query = $db->simple_select('users', 'COUNT(uid) as total_users');

    $total_users = $db->fetch_field($query, 'total_users');

    $page = $mybb->get_input('page', MyBB::INPUT_INT);

    $per_page = $mybb->get_input('newpoints_recount', MyBB::INPUT_INT);

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
                "uid='{$user_id}' AND log_type='{$log_type_income}'",
            ),
            'total_income'
        ) ?? 0);

        $total_charges = (float)($db->fetch_field(
            $db->simple_select(
                'newpoints_log',
                'SUM(points) AS total_charges',
                "uid='{$user_id}' AND log_type='{$log_type_charge}'",
            ),
            'total_charges'
        ) ?? 0);

        $user_income = $total_income - $total_charges;

        user_update($user_id, ['newpoints' => $total_income - $total_charges]);
    }

    check_proceed(
        $total_users,
        $end,
        ++$page,
        $per_page,
        'newpoints_recount',
        'do_recount_newpoints_from_logs',
        $lang->newpoints_recount_from_logs_success
    );
}

function recount_rebuild_newpoints_recount()
{
    global $db, $mybb, $lang;

    $query = $db->simple_select('users', 'COUNT(uid) as total_users');

    $total_users = $db->fetch_field($query, 'total_users');

    $page = $mybb->get_input('page', MyBB::INPUT_INT);

    $per_page = $mybb->get_input('newpoints_recount', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;

    $end = $start + $per_page;

    $forum_rules = rules_get_all('forum');

    $query = $db->simple_select(
        'users',
        'uid,usergroup,additionalgroups',
        '',
        ['order_by' => 'uid', 'order_dir' => 'asc', 'limit_start' => $start, 'limit' => $per_page]
    );

    while ($user_data = $db->fetch_array($query)) {
        $points = 0;

        $user_id = (int)$user_data['uid'];

        $user_group_permissions = users_get_group_permissions($user_id);

        if (empty($user_group_permissions['newpoints_rate_addition'])) {
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

            if (!get_income_value(INCOME_TYPE_THREAD, $user_id, $forum_id)) {
                continue;
            }

            if (empty($forum_rules[$thread['fid']])) {
                $forum_rules[$thread['fid']]['rate'] = 1;
            }

            if (empty($forum_rules[$thread['fid']]['rate'])) {
                continue;
            }

            if (($character_count = my_strlen(
                    $mybb->get_input('message')
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $character_count * get_income_value(INCOME_TYPE_POST_CHARACTER, $user_id, $forum_id);
            } else {
                $bonus = 0;
            }

            $points += (get_income_value(INCOME_TYPE_THREAD, $user_id, $forum_id) + $bonus) *
                $forum_rules[$thread['fid']]['rate'];

            if (!empty($thread['poll'])) {
                $points += get_income_value(INCOME_TYPE_POLL, $user_id, $forum_id) *
                    $forum_rules[$thread['fid']]['rate'];
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

            if (!get_income_value(INCOME_TYPE_POST, $user_id, $forum_id)) {
                continue;
            }

            if (!$forum_rules[$post_data['fid']]) {
                $forum_rules[$post_data['fid']]['rate'] = 1;
            }

            if (empty($forum_rules[$post_data['fid']]['rate'])) {
                continue;
            }

            if (($character_count = my_strlen(
                    $post_data['message']
                )) >= $user_group_permissions['newpoints_income_post_minimum_characters']) {
                $bonus = $character_count *
                    get_income_value(INCOME_TYPE_POST_CHARACTER, $user_id, $forum_id);
            } else {
                $bonus = 0;
            }

            $points += (get_income_value(INCOME_TYPE_POST, $user_id, $forum_id) + $bonus) *
                $forum_rules[$post_data['fid']]['rate'];

            $thread_data = get_thread($post_data['tid']);

            $thread_user_id = (int)$thread_data['uid'];

            $forum_id = (int)$post_data['fid'];

            if ($thread_user_id !== $user_id && user_can_get_points($thread_user_id, $forum_id)) {
                $income_value = get_income_value(INCOME_TYPE_THREAD_REPLY, $thread_user_id, $forum_id);

                $thread_user_group_permissions = users_get_group_permissions($thread_user_id);

                $income_value = $income_value * $thread_user_group_permissions['newpoints_rate_addition'];

                if ($income_value) {
                    points_add_simple(
                        $thread_user_id,
                        $income_value
                    );

                    log_add(
                        'income_' . INCOME_TYPE_THREAD_REPLY,
                        '',
                        get_user($thread_user_id)['username'] ?? '',
                        $thread_user_id,
                        $income_value,
                        $post_id,
                        $thread_id,
                        $forum_id,
                        LOGGING_TYPE_INCOME
                    );
                }
            }
        }

        $query_polls = $db->simple_select(
            "pollvotes v LEFT JOIN {$db->table_prefix}poll p ON (p.pid=v.pid) LEFT JOIN {$db->table_prefix}threads t ON (t.tid=p.tid)",
            'p.tid, t.fid',
            "v.uid='{$user_id}'"
        );

        while ($vote_data = $db->fetch_array($query_polls)) {
            $thread_id = (int)$vote_data['tid'];

            $forum_id = (int)$vote_data['fid'];

            $income_value = get_income_value(INCOME_TYPE_POLL_VOTE, $user_id, $forum_id);

            if ($income_value) {
                $points += $income_value;
            }
        }

        $income_value = get_income_value(INCOME_TYPE_PRIVATE_MESSAGE, $user_id);

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

        $db->update_query(
            'users',
            [
                'newpoints' => get_income_value(INCOME_TYPE_USER_REGISTRATION, $user_id) + $points *
                    $user_group_permissions['newpoints_rate_addition']
            ],
            "uid='{$user_id}'"
        );
    }

    check_proceed(
        $total_users,
        $end,
        ++$page,
        $per_page,
        'newpoints_recount',
        'do_recount_newpoints',
        $lang->newpoints_recount_success
    );
}

function recount_rebuild_newpoints_reset()
{
    global $db, $mybb, $lang;

    $query = $db->simple_select('users', 'COUNT(uid) as total_users');

    $total_users = $db->fetch_field($query, 'total_users');

    $page = $mybb->get_input('page', MyBB::INPUT_INT);

    $per_page = $mybb->get_input('newpoints_recount', MyBB::INPUT_INT);

    $start = ($page - 1) * $per_page;

    $end = $start + $per_page;

    $forum_rules = rules_get_all('forum');

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
            ['newpoints' => $mybb->get_input('newpoints_reset', MyBB::INPUT_FLOAT)],
            "uid='{$user_id}'"
        );
    }

    check_proceed(
        $total_users,
        $end,
        ++$page,
        $per_page,
        'newpoints_reset',
        'do_reset_newpoints',
        $lang->newpoints_reset_success
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
                'alert_types' => ['add_points', 'subtract_points'],
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
                'alert_types' => ['add_points', 'subtract_points'],
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