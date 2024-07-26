<?php
/***************************************************************************
 *
 *   NewPoints plugin (/inc/plugins/newpoints.php)
 *     Author: Pirata Nervo
 *   Copyright: © 2009-2012 Pirata Nervo
 *
 *   Website: http://www.mybb-plugins.com
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

use function Newpoints\Core\add_hooks;
use function Newpoints\Core\check_permissions;
use function Newpoints\Core\find_replace_template_sets;
use function Newpoints\Core\get_group;
use function Newpoints\Core\js_special_characters;
use function Newpoints\Core\language_load;
use function Newpoints\Core\log_add;
use function Newpoints\Core\log_remove;
use function Newpoints\Core\plugins_load;
use function Newpoints\Core\points_add;
use function Newpoints\Core\points_format;
use function Newpoints\Core\points_update;
use function Newpoints\Core\private_message_send;
use function Newpoints\Core\rules_get;
use function Newpoints\Core\rules_get_all;
use function Newpoints\Core\rules_rebuild_cache;
use function Newpoints\Core\settings_add;
use function Newpoints\Core\settings_add_group;
use function Newpoints\Core\settings_load;
use function Newpoints\Core\settings_rebuild_cache;
use function Newpoints\Core\settings_remove;
use function Newpoints\Core\templates_add;
use function Newpoints\Core\templates_rebuild;
use function Newpoints\Core\templates_remove;
use function Newpoints\Core\users_get_by_username;
use function Newpoints\Core\users_update;

use const Newpoints\ROOT;

defined('IN_MYBB') || die('Direct initialization of this file is not allowed.');

// You can uncomment the lines below to avoid storing some settings in the DB
define('Newpoints\Core\SETTINGS', [
    //'key' => '',
]);

define('Newpoints\Core\DEBUG', false);

define('Newpoints\ROOT', constant('MYBB_ROOT') . 'inc/plugins/newpoints');

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

require_once ROOT . '/core.php';
require_once ROOT . '/classes.php';

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';

    require_once ROOT . '/hooks/admin.php';

    add_hooks('Newpoints\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    add_hooks('Newpoints\Hooks\Forum');
}

require_once ROOT . '/hooks/shared.php';

add_hooks('Newpoints\Hooks\Shared');

if (!defined('IN_MYBB')) {
    die('This file cannot be accessed directly.');
}

// Load NewPoints' settings whenever NewPoints plugin is executed
// Adds one additional query per page
// TODO: Perhaps use Plugin Library to modify the init.php file to load settings from both tables (MyBB's and NewPoints')
// OR: Go back to the old method and put the settings in the settings table but keep a copy in NewPoints' settings table
// but also add a page on ACP to run the check and fix any missing settings or perhaps do the check via task.
if (defined('IN_ADMINCP')) {
    global $mybb;

    // Plugins get "require_once" on Plugins List and Plugins Check and we do not want to load our settings when our file is required by those
    if ($mybb->get_input('module') != 'config-plugins' && $GLOBALS['db']->table_exists('newpoints_settings')) {
        newpoints_load_settings();
    }
} else {
    newpoints_load_settings();
}

define('NEWPOINTS_VERSION', '2.1.2');
define('NEWPOINTS_VERSION_CODE', '212');
define('MAX_DONATIONS_CONTROL', '5'); // Maximum donations someone can send each 15 minutes

// load plugins and do other stuff
if (defined('IN_ADMINCP')) {
    define('NP_HOOKS', 1); // 1 means Admin
} else {
    define('NP_HOOKS', 2); // 2 means outside ACP
}

// load hooks
require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/hooks.php';

if (defined('IN_ADMINCP')) {
    global $db, $mybb;

    function newpoints_info()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        return newpoints_plugin_info();
    }

    function newpoints_install()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_install();
    }

    function newpoints_is_installed()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        return newpoints_plugin_is_installed();
    }

    function newpoints_uninstall()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_uninstall();
    }

    function newpoints_do_template_edits()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_do_template_edits();
    }

    function newpoints_undo_template_edits()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_undo_template_edits();
    }

    function newpoints_activate()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_activate();
    }

    function newpoints_deactivate()
    {
        require_once constant('MYBB_ROOT') . 'inc/plugins/newpoints/core/plugin.php';
        newpoints_plugin_deactivate();
    }
}

/**************************************************************************************/
/****************** FUNCTIONS THAT CAN/SHOULD BE USED BY PLUGINS **********************/
/**************************************************************************************/

function newpoints_count_characters(string $message): int
{
    return \Newpoints\Core\count_characters($message);
}

function newpoints_jsspecialchars(string $str): string
{
    return js_special_characters($str);
}

function newpoints_remove_templates($templates): bool
{
    return templates_remove($templates);
}

function newpoints_add_template(string $name, string $contents, $sid = -1): bool
{
    return templates_add($name, $contents, $sid);
}

function newpoints_rebuild_templates(): bool
{
    return templates_rebuild();
}

function newpoints_remove_settings(string $settings): bool
{
    return settings_remove($settings);
}

function newpoints_add_setting(
    string $name,
    string $plugin,
    string $title,
    string $description,
    string $type,
    string $value = '',
    int $disporder = 0
): bool {
    return settings_add($name, $plugin, $title, $description, $type, $value, $disporder);
}

function newpoints_add_settings(string $plugin, array $settings): bool
{
    return settings_add_group($plugin, $settings);
}

/**
 * Adds/Subtracts points to a user
 *
 * @param int the id of the user
 * @param float the number of points to add or subtract (if a negative value)
 * @param int the forum income rate
 * @param int the user group income rate
 * @param bool if the uid is a string in case we don't have the uid we can update the points field by searching for the user name
 * @param bool true if you want to run the query immediatly. Default is false which means the query will be run on shut down. Note that if the previous paremeter is set to true, the query is run immediatly
 * Note: some pages (by other plugins) do not run queries on shutdown so adding this to shutdown may not be good if you're not sure if it will run.
 *
 */
function newpoints_addpoints(
    int $uid,
    float $points,
    int $forumrate = 1,
    int $grouprate = 1,
    bool $isstring = false,
    bool $immediate = false
): bool {
    return points_add($uid, $points, $forumrate = 1, $grouprate = 1, $isstring = false, $immediate);
}

function newpoints_update_addpoints()
{
    return points_update();
}

function newpoints_getrules(string $type, int $id): array
{
    return rules_get($type, $id);
}

function newpoints_getallrules($type): array
{
    return rules_get_all($type);
}

function newpoints_rebuild_rules_cache(array &$rules = array()): bool
{
    return rules_rebuild_cache($rules);
}

function newpoints_format_points(float $points): string
{
    return points_format($points);
}

function newpoints_send_pm(array $pm, int $fromid = 0): bool
{
    return private_message_send($pm, $fromid);
}

function newpoints_getuser_byname(string $username, string $fields = '*'): array
{
    return users_get_by_username($username, $fields);
}

function newpoints_get_usergroup(int $gid): array
{
    return get_group($gid);
}

function newpoints_find_replace_templatesets(string $title, string $find, string $replace): bool
{
    return find_replace_template_sets($title, $find, $replace);
}

function newpoints_log(string $action, string $data = '', string $username = '', int $uid = 0): bool
{
    return log_add($action, $data, $username, $uid);
}

function newpoints_remove_log(array $action): bool
{
    return log_remove($action);
}

function newpoints_check_permissions(string $groups_comma): bool
{
    return check_permissions($groups_comma);
}

function newpoints_load_plugins(): bool
{
    return plugins_load();
}

function newpoints_load_settings(): bool
{
    return settings_load();
}

function newpoints_rebuild_settings_cache(array &$settings = array()): array
{
    return settings_rebuild_cache($settings);
}

function newpoints_lang_load(string $plugin): bool
{
    return language_load($plugin);
}

function newpoints_update_users(): bool
{
    return users_update();
}