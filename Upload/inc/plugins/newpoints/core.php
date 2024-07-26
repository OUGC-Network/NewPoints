<?php

/***************************************************************************
 *
 *    Newpoints plugin (/inc/plugins/newpoints/core.php)
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

namespace Newpoints\Core;

use pluginSystem;

use const Newpoints\ROOT;

function load_language(): bool
{
    global $lang;

    isset($lang->newpoints) || $lang->load('newpoints');

    return true;
}

function add_hooks(string $namespace): bool
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }

    return true;
}

function run_hooks(string $hook_name = '', mixed &$hook_arguments = ''): mixed
{
    global $plugins;

    if ($plugins instanceof pluginSystem) {
        $hook_arguments = $plugins->run_hooks('newpoints_' . $hook_name, $hook_arguments);
    }

    return $hook_arguments;
}

function get_setting(string $setting_key = '')
{
    global $mybb;

    return isset(SETTINGS[$setting_key]) ? SETTINGS[$setting_key] : (
    isset($mybb->settings['newpoints_' . $setting_key]) ? $mybb->settings['newpoints_' . $setting_key] : false
    );
}

function get_template_name(string $template_name = ''): string
{
    $template_prefix = '';

    if ($template_name) {
        $template_prefix = '_';
    }

    return "newpoints{$template_prefix}{$template_name}";
}

function get_template(string $template_name = '', bool $enable_html_comments = true): string
{
    global $templates;

    if (DEBUG) {
        $file_path = ROOT . "/templates/{$template_name}.html";

        $template_contents = file_get_contents($file_path);

        $templates->cache[get_template_name($template_name)] = $template_contents;
    } elseif (my_strpos($template_name, '/') !== false) {
        $template_name = substr($template_name, strpos($template_name, '/') + 1);
    }

    return $templates->render(get_template_name($template_name), true, $enable_html_comments);
}