<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints.php)
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

use NewPoints\Core\IncomePermissions;
use NewPoints\Core\IncomeRates;
use NewPoints\Core\Permissions;

use function NewPoints\Core\add_hooks;
use function NewPoints\Core\check_permissions;
use function NewPoints\Core\count_characters;
use function NewPoints\Core\find_replace_template_sets;
use function NewPoints\Core\get_group;
use function NewPoints\Core\instance_object;
use function NewPoints\Core\js_special_characters;
use function NewPoints\Core\language_load;
use function NewPoints\Core\log_add;
use function NewPoints\Core\log_error;
use function NewPoints\Core\log_remove;
use function NewPoints\Core\plugins_load;
use function NewPoints\Core\points_add;
use function NewPoints\Core\points_format;
use function NewPoints\Core\points_update;
use function NewPoints\Core\private_message_send;
use function NewPoints\Core\rules_get;
use function NewPoints\Core\rules_get_all;
use function NewPoints\Core\rules_rebuild_cache;
use function NewPoints\Core\settings_add;
use function NewPoints\Core\settings_add_group;
use function NewPoints\Core\settings_load;
use function NewPoints\Core\settings_load_init;
use function NewPoints\Core\settings_rebuild_cache;
use function NewPoints\Core\settings_remove;
use function NewPoints\Core\templates_add;
use function NewPoints\Core\templates_rebuild;
use function NewPoints\Core\templates_remove;
use function NewPoints\Core\users_get_by_username;
use function NewPoints\Core\users_update;
use function NewPoints\Admin\plugin_activation;
use function NewPoints\Admin\plugin_deactivation;
use function NewPoints\Admin\plugin_information;
use function NewPoints\Admin\plugin_installation;
use function NewPoints\Admin\plugin_is_installed;
use function NewPoints\Admin\plugin_uninstallation;

use const NewPoints\ROOT;
use const NewPoints\Core\INSTANCE_DEFAULT_ID;
use const NewPoints\Core\PRIVATE_MESSAGE_CURRENT_USER_ID;

const NEWPOINTS_VERSION = '3.1.6';

const NEWPOINTS_VERSION_CODE = 3106;

const MAX_DONATIONS_CONTROL = 5; // Maximum donations someone can send each 15 minutes

const NP_HOOKS = 0;

defined('IN_MYBB') || die('Direct initialization of this file is not allowed.');

// You can uncomment the lines below to avoid storing some settings in the DB
define('NewPoints\Core\SETTINGS', [
    //'disable_plugins' => true,
    //'income_post' => 10,
    'my_alerts_version' => '2.1.0',
    'disable_backups' => false,
]);

define('NewPoints\Core\DEBUG', false);

define('NewPoints\DECIMAL_DATA_TYPE_SIZE', '16,4');

define('NewPoints\DECIMAL_DATA_TYPE_STEP', 0.0001);

define('NewPoints\ROOT', MYBB_ROOT . 'inc/plugins/newpoints');

define('NewPoints\ROOT_PLUGINS', ROOT . '/plugins');

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

require_once ROOT . '/core.php';
require_once ROOT . '/system/Permissions.php';
require_once ROOT . '/system/IncomeRates.php';
require_once ROOT . '/system/IncomePermissions.php';
require_once ROOT . '/classes.php';
require_once ROOT . '/system/Instance.php';
require_once ROOT . '/system/Url.php';

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';
    require_once ROOT . '/hooks/admin.php';

    add_hooks('NewPoints\Hooks\Admin');
} else {
    require_once ROOT . '/hooks/forum.php';

    add_hooks('NewPoints\Hooks\Forum');
}

require_once ROOT . '/hooks/shared.php';

add_hooks('NewPoints\Hooks\Shared');

if (defined('IN_ADMINCP')) {
    function newpoints_info(): array
    {
        return plugin_information();
    }

    function newpoints_install(): void
    {
        plugin_installation();
    }

    function newpoints_is_installed(): bool
    {
        return plugin_is_installed();
    }

    function newpoints_uninstall(): void
    {
        plugin_uninstallation();
    }

    function newpoints_activate(): void
    {
        plugin_activation();
    }

    function newpoints_deactivate(): void
    {
        plugin_deactivation();
    }
}

/**************************************************************************************/
/****************** FUNCTIONS THAT CAN/SHOULD BE USED BY PLUGINS **********************/
/**************************************************************************************/

#[Deprecated(message: 'use count_characters() instead', since: '3')]
function newpoints_count_characters(string $message): int
{
    return count_characters($message);
}

#[Deprecated(message: 'use js_special_characters() instead', since: '3')]
function newpoints_jsspecialchars(string $str): string
{
    return js_special_characters($str);
}

#[Deprecated(message: 'use templates_remove() instead', since: '3')]
function newpoints_remove_templates($templates): bool
{
    return templates_remove(explode(',', $templates));
}

#[Deprecated(message: 'use templates_add() instead', since: '3')]
function newpoints_add_template(string $name, string $contents, $sid = -1): bool
{
    return templates_add($name, $contents, $sid);
}

#[Deprecated(message: 'use templates_rebuild() instead', since: '3')]
function newpoints_rebuild_templates(): bool
{
    return templates_rebuild();
}

#[Deprecated(message: 'use settings_remove() instead', since: '3')]
function newpoints_remove_settings(string $settings): bool
{
    return settings_remove(explode(',', str_replace("'", '', $settings)));
}

#[Deprecated(message: 'use settings_add() instead', since: '3')]
function newpoints_add_setting(
    string $name,
    string $plugin,
    string $title,
    string $description,
    string $type,
    string $value = '',
    int $disporder = 0
): void {
    settings_add($name, $plugin, $title, $description, $type, $value, $disporder);
}

#[Deprecated(message: 'use settings_add_group() instead', since: '3')]
function newpoints_add_settings(string $plugin, array $settings): bool
{
    return settings_add_group($plugin, $settings);
}

#[Deprecated(message: 'use points_addition() instead', since: '3')]
function newpoints_addpoints(
    int $user_id,
    float $points,
    float $forumrate = 1,
    float $grouprate = 1,
    bool $isstring = false,
    bool $immediate = false
): bool {
    try {
        instance_object(INSTANCE_DEFAULT_ID, $user_id);

        return points_add($user_id, $points, $forumrate, $grouprate, $isstring, $immediate);
    } catch (Exception $e) {
        log_error(INSTANCE_DEFAULT_ID, $e->getMessage());

        return false;
    }
}

#[Deprecated(message: 'use points_update() instead', since: '3')]
function newpoints_update_addpoints(): void
{
    points_update();
}

#[Deprecated(message: 'use rules_get() instead', since: '3')]
function newpoints_getrules(string $type, int $id): array
{
    return rules_get($type, $id);
}

#[Deprecated(message: 'use rules_get_all() instead', since: '3')]
function newpoints_getallrules($type): array
{
    return rules_get_all($type);
}

#[Deprecated(message: 'use rules_rebuild_cache() instead', since: '3')]
function newpoints_rebuild_rules_cache(array &$rules = []): bool
{
    return rules_rebuild_cache($rules);
}

#[Deprecated(message: 'use points_format() instead', since: '3')]
function newpoints_format_points(float $points): string
{
    return points_format($points);
}

#[Deprecated(message: 'use private_message_send() instead', since: '3')]
function newpoints_send_pm(array $private_message_data, int $from_user_id = PRIVATE_MESSAGE_CURRENT_USER_ID): bool
{
    return private_message_send($private_message_data, $from_user_id);
}

#[Deprecated(message: 'use users_get_by_username() instead', since: '3')]
function newpoints_getuser_byname(string $username, string $fields = '*'): array
{
    return users_get_by_username($username, $fields);
}

#[Deprecated(message: 'use get_group() instead', since: '3')]
function newpoints_get_usergroup(int $gid): array
{
    return get_group($gid);
}

#[Deprecated(message: 'use find_replace_template_sets() instead', since: '3')]
function newpoints_find_replace_templatesets(string $title, string $find, string $replace): bool
{
    return find_replace_template_sets($title, $find, $replace);
}

#[Deprecated(message: 'use log_add() instead', since: '3')]
function newpoints_log(string $log_action, string $log_data = '', string $username = '', int $user_id = 0): int
{
    return log_add($log_action, $log_data, $username, $user_id);
}

#[Deprecated(message: 'use log_remove() instead', since: '3')]
function newpoints_remove_log(array $action): bool
{
    return log_remove($action);
}

#[Deprecated(message: 'use check_permissions() instead', since: '3')]
function newpoints_check_permissions(string $groups_comma): bool
{
    return check_permissions($groups_comma);
}

#[Deprecated(message: 'use plugins_load() instead', since: '3')]
function newpoints_load_plugins(): bool
{
    return plugins_load();
}

#[Deprecated(message: 'use settings_load() instead', since: '3')]
function newpoints_load_settings(): void
{
    settings_load();
}

#[Deprecated(message: 'use settings_rebuild_cache() instead', since: '3')]
function newpoints_rebuild_settings_cache(array &$settings = []): array
{
    return settings_rebuild_cache($settings);
}

#[Deprecated(message: 'use language_load() instead', since: '3')]
function newpoints_lang_load(string $plugin): bool
{
    return language_load($plugin);
}

#[Deprecated(message: 'use users_update() instead', since: '3')]
function newpoints_update_users(): bool
{
    return users_update();
}

function reload_newpoints_settings(): bool
{
    settings_rebuild_cache();

    return true;
}

settings_load_init();

plugins_load();

(function () {
    global $groupzerolesser, $grouppermbyswitch, $fpermfields;

    foreach (
        [
            IncomeRates::RateSubtraction,
            IncomePermissions::UserIncomePostMinimumCharacters,
            IncomePermissions::UserIncomeVisitMinutes
        ] as $group_permission_key
    ) {
        $groupzerolesser[] = $group_permission_key;

        $grouppermbyswitch[$group_permission_key] = Permissions::CanGetPoints;
    }

    $fpermfields[] = Permissions::CanGetPoints;

    $fpermfields[] = Permissions::Rate;

    $fpermfields[] = Permissions::ViewLockCost;

    $fpermfields[] = Permissions::PostLockCost;
})();

global $newpoints_globals, $newpoints_profile;

$newpoints_globals = $newpoints_profile = [];

//todo, build an income table in forums, so users can see their income rates per forum if there are custom forum permissions

//todo, refactor url handler to class