<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/system/core.php)
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

namespace NewPoints\System;

use Exception;
use NewPoints\Core\IncomePermissions;
use NewPoints\Core\IncomeRates;
use NewPoints\Core\Permissions;

use function NewPoints\Core\cache_get_instances;
use function NewPoints\Core\count_characters;
use function NewPoints\Core\get_setting;
use function NewPoints\Core\log_error;
use function NewPoints\Core\run_hooks;
use function NewPoints\Core\templates_get;

use const NewPoints\Core\URL;
use const NewPoints\Core\ALL_UNLIMITED_VALUE;
use const NewPoints\Core\GUEST_GROUP_ID;
use const NewPoints\Core\INCOME_TYPE_PAGE_VIEW;
use const NewPoints\Core\INCOME_TYPE_POLL;
use const NewPoints\Core\INCOME_TYPE_POLL_VOTE;
use const NewPoints\Core\INCOME_TYPE_POST;
use const NewPoints\Core\INCOME_TYPE_POST_CHARACTER;
use const NewPoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const NewPoints\Core\INCOME_TYPE_THREAD;
use const NewPoints\Core\INCOME_TYPE_THREAD_RATE;
use const NewPoints\Core\INCOME_TYPE_THREAD_REPLY;
use const NewPoints\Core\INCOME_TYPE_USER_REFERRAL;
use const NewPoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const NewPoints\Core\INCOME_TYPE_VISIT;
use const NewPoints\Core\LOGGING_TYPE_CHARGE;
use const NewPoints\Core\LOGGING_TYPE_INCOME;
use const NewPoints\Core\TABLES_DATA;

class Instance
{
    public int $instance_id;

    private array $instance_data;

    public Logger $logger;

    private int $forum_id = 0;

    private int $thread_id = 0;

    private int $primary_id = 0;

    private int $secondary_id = 0;

    private int $tertiary_id = 0;

    public int $post_id = 0;

    private int $user_id = 0;

    private array $user_data = [];

    public array $user_permissions = [];

    public string $user_groups = '';

    private array $forum_permissions = [];

    private array $tables_data = [];

    public \NewPoints\System\Url $url;

    private array $menu_items = [];

    /**
     * @throws Exception
     */
    public function __construct(int $instance_id, int $user_id = 0)
    {
        if (!($this->instance_data = cache_get_instances($instance_id))) {
            throw new Exception("Instance with ID $instance_id does not exist.");
        }

        $this->tables_data = TABLES_DATA;

        $this->instance_id = (int)$this->instance_data['instance_id'];

        require_once MYBB_ROOT . 'inc/plugins/newpoints/system/Logger.php';

        $this->logger = new Logger($this);

        require_once MYBB_ROOT . 'inc/plugins/newpoints/system/Url.php';

        $this->url = new \NewPoints\System\Url($this->get_script_name());

        if ($user_id <= 0) {
            global $mybb;

            $this->user_id = (int)$mybb->user['uid'];

            $this->user_data = &$mybb->user;
        } else {
            $this->user_id = $user_id;

            $this->user_data = get_user($this->user_id);
        }

        $this->user_groups = ($this->user_data['usergroup'] ?? '') . ',' . ($this->user_data['additionalgroups'] ?? '');

        $this->run_hooks('instance_construct_start', $this);

        $this->user_permissions = $this->set_user_permissions();

        $this->run_hooks('instance_construct_end', $this);
    }

    private function set_user_permissions(): array
    {
        static $user_permissions = null;

        if (isset($user_permissions)) {
            return $user_permissions;
        }

        if (!$this->user_id) {
            $user_permissions = array_merge(
                user_permissions($this->user_id),
                $this->get_group_permissions()
            );

            return $user_permissions;
        }

        $user_groups_ids = array_filter(
            array_map(
                'intval',
                explode(',', "{$this->user_data['usergroup']},{$this->user_data['additionalgroups']}")
            )
        );

        $fields_data = $this->tables_data['newpoints_group_permissions'];

        $user_permissions = [];

        foreach ($fields_data as $permission_name => $data_field_data) {
            if (!isset($data_field_data['is_permission'])) {
                continue;
            }

            foreach ($user_groups_ids as $group_id) {
                $group_permissions = $this->get_group_permissions($group_id);

                if (!$group_permissions) {
                    $group_permissions = usergroup_permissions($group_id);
                }

                if (!empty($data_field_data['zero_unlimited']) && empty($group_permissions[$permission_name]) ||
                    !empty($data_field_data['zero_unlimited']) && isset($user_permissions[$permission_name]) && empty($user_permissions[$permission_name])) {
                    $user_permissions[$permission_name] = ALL_UNLIMITED_VALUE;

                    continue 2;
                }

                if (isset($user_permissions[$permission_name])) {
                    if (!empty($data_field_data['closest_to'])) {
                        if (
                            abs(
                                $data_field_data['closest_to'] - $user_permissions[$permission_name]
                            ) >
                            abs($group_permissions[$permission_name] - $data_field_data['closest_to'])) {
                            $user_permissions[$permission_name] = $group_permissions[$permission_name];
                        }
                    } elseif (!empty($data_field_data['lowest'])) {
                        $user_permissions[$permission_name] = min(
                            $user_permissions[$permission_name],
                            $group_permissions[$permission_name]
                        );
                    } else {
                        $user_permissions[$permission_name] = max(
                            $user_permissions[$permission_name],
                            $group_permissions[$permission_name]
                        );
                    }
                } else {
                    $user_permissions[$permission_name] = $group_permissions[$permission_name];
                }
            }
        }

        $user_permissions = array_merge(
            user_permissions($this->user_id),
            $user_permissions
        );

        return $user_permissions;
    }

    public function is_enabled(): bool
    {
        return !empty($this->instance_data['is_enabled']) && $this->users_column_exists();
    }

    public function plugins_enabled(): bool
    {
        return !$this->instance_data['disable_plugins'];
    }

    public function notifications_private_message_enabled(): bool
    {
        return !empty($this->instance_data['enable_notifications_private_message']);
    }

    public function notifications_alert_enabled(): bool
    {
        return !empty($this->instance_data['enable_notifications_alert']);
    }

    public function set_forum(int $forum_id): self
    {
        $this->forum_id = $forum_id;

        $this->forum_permissions = fetch_forum_permissions($this->forum_id, $this->user_groups, []);

        return $this;
    }

    public function set_thread(int $thread_id): self
    {
        $this->thread_id = $thread_id;

        return $this;
    }

    public function set_post(int $post_id): self
    {
        $this->post_id = $post_id;

        return $this;
    }

    public function set_primary_id(int $primary_id): self
    {
        $this->primary_id = $primary_id;

        return $this;
    }

    public function set_secondary_id(int $secondary_id): self
    {
        $this->secondary_id = $secondary_id;

        return $this;
    }

    public function set_tertiary_id(int $tertiary_id): self
    {
        $this->tertiary_id = $tertiary_id;

        return $this;
    }

    public function append_tables_data(string $table_name, array $fields_data): void
    {
        $this->tables_data[$table_name] = array_merge(
            $this->tables_data[$table_name] ?? [],
            $fields_data,
        );
    }

    public function get_user_data(): array
    {
        return $this->user_data;
    }

    public function get_user_column_value(): float
    {
        return (float)$this->get_user_data()[$this->users_column_get()];
    }

    public function get_data(): array
    {
        return $this->instance_data;
    }

    public function get_user_id(): int
    {
        return $this->user_id ?? 0;
    }

    private function get_user_permission(string $permission_key): string|int|float
    {
        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions();

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            // todo, should be isset ?
            if (!empty($custom_permissions[$permission_key])) {
                return $custom_permissions[$permission_key];
            }

            if (isset($this->forum_permissions[$permission_key])) {
                return $this->forum_permissions[$permission_key];
            }
        }

        return $this->user_permissions[$permission_key] ?? '';
    }

    public function get_user_permission_rate_addition(string $permission_key = IncomeRates::RateAddition): float
    {
        $user_rate = 1;

        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions();

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                $user_rate *= $custom_permissions[$permission_key];
            }

            if (isset($this->forum_permissions[$permission_key])) {
                return ($user_rate * $this->forum_permissions[$permission_key]);
            }
        }

        return ($user_rate * $this->user_permissions[$permission_key]);
    }

    public function get_user_permission_rate_substraction(
        string $permission_key = IncomeRates::RateSubtraction,
    ): float {
        return ($this->get_user_permission_rate_addition($permission_key) / 100);
    }

    // this helper function is used when a permission is both a group and forum permission
    public function get_user_permission_boolean(string $permission_key): bool
    {
        $forum_permission = true;

        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions();

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                $forum_permission = !empty($custom_permissions[$permission_key]);
            }

            if (isset($this->forum_permissions[$permission_key])) {
                return $forum_permission && $this->forum_permissions[$permission_key];
            }
        }

        return $forum_permission && $this->user_permissions[$permission_key];
    }

    public function get_user_permission_int(string $permission_key): int
    {
        return (int)$this->get_user_permission($permission_key);
    }

    public function get_user_permission_float(string $permission_key): float
    {
        return (float)$this->get_user_permission($permission_key);
    }

    private function get_group_permissions(int $group_id = GUEST_GROUP_ID): array
    {
        static $group_permissions = [];

        if (isset($group_permissions[$group_id])) {
            return $group_permissions[$group_id];
        }

        $group_permissions[$group_id] = [];

        $fields_data = $this->tables_data['newpoints_group_permissions'];

        $hook_arguments = [
            'fields_data' => &$fields_data,
            'data_fields' => &$fields_data,
        ];

        // todo, similar to `admin_user_groups_edit_graph_start`
        $hook_arguments = $this->run_hooks('admin_user_groups_edit_graph_start', $hook_arguments);

        foreach ($fields_data as $data_field_key => $data_field_data) {
            if (!isset($data_field_data['is_permission'])) {
                continue;
            }

            $group_permissions[$group_id][$data_field_key] = $data_field_data['default'];
        }

        global $cache;

        $groups_cache = (array)$cache->read('usergroups');

        foreach ($groups_cache[$group_id] as $permission_key => $permission_value) {
            if (str_starts_with($permission_key, 'newpoints_') &&
                isset($group_permissions[$group_id][$permission_key])) {
                $group_permissions[$group_id][$permission_key] = match ($fields_data[$permission_key]['type']) {
                    'INT', 'TINYINT', 'SMALLINT' => (int)$permission_value,
                    'FLOAT', 'DECIMAL' => (float)$permission_value,
                    default => $permission_value,
                };
            }
        }

        $permissions_cache = $this->cache_get_group_permissions()[$this->instance_id] ?? [];

        if (!empty($permissions_cache[$group_id])) {
            $group_permissions[$group_id] = array_merge(
                $group_permissions[$group_id],
                $permissions_cache[$group_id]
            );
        }

        return $group_permissions[$group_id];
    }

    public function get_forum_permissions(?int $forum_id = null): array
    {
        $permissions_cache = $this->cache_get_group_permissions()[$this->instance_id] ?? [];

        if (empty($permissions_cache[$forum_id ?? $this->forum_id])) {
            return [];
        }

        return $permissions_cache[$forum_id ?? $this->forum_id];
    }

    public function get_forum_id(): int
    {
        return $this->forum_id;
    }

    public function get_thread_id(): int
    {
        return $this->thread_id;
    }

    public function get_post_id(): int
    {
        return $this->post_id;
    }

    public function get_script_name(): string
    {
        return $this->instance_data['script_name'] ?? URL;
    }

    protected function get_currency_name_singular(): string
    {
        return $this->instance_data['currency_name_singular'] ?? $this->instance_data['currency_name_plural'];
    }

    protected function get_currency_name_plural(): string
    {
        return (string)$this->instance_data['currency_name_plural'];
    }

    public function get_display_name_upper(?float $points = null): string
    {
        if ($points === null || (int)$points != $points || $points > 1) {
            return ucwords($this->get_currency_name_plural());
        }

        return ucwords($this->get_currency_name_singular());
    }

    public function get_display_name_lower(?float $points = null): string
    {
        if ($points === null || (int)$points != $points || $points > 1) {
            return my_strtolower($this->get_currency_name_plural());
        }

        return my_strtolower($this->get_currency_name_singular());
    }

    public function get_income_value(string $income_permission, int $income_type = LOGGING_TYPE_INCOME): float
    {
        $user_rate = 1;

        $income_value = 0;

        $global_setting_key = 'income_' . $income_permission;

        $group_setting_key = 'newpoints_income_' . $income_permission;

        $income_value = ($this->settings_get_value($global_setting_key) === false ?
            $this->user_permissions[$group_setting_key] :
            $this->settings_get_value($global_setting_key)) ?? $this->user_permissions[$income_permission];

        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions();

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[Permissions::Rate])) {
                $user_rate *= $custom_permissions[Permissions::Rate];
            }

            if (isset($this->forum_permissions[Permissions::Rate])) {
                return ($user_rate * $this->forum_permissions[Permissions::Rate] * $income_value);
            }
        }

        if ($income_type === LOGGING_TYPE_INCOME) {
            return ($user_rate * $this->get_user_permission_rate_addition() * $income_value);
        } else {
            return ($user_rate * $this->get_user_permission_rate_substraction() * $income_value);
        }
    }

    public function get_menu_items(): array
    {
        return $this->menu_items;
    }

    public function users_column_exists(): bool
    {
        global $db;

        return !empty($this->instance_data['users_column_name']) &&
            $db->field_exists($this->instance_data['users_column_name'], 'users');
    }

    public function users_column_get(): string
    {
        return $this->instance_data['users_column_name'] ?? 'newpoints';
    }

    public function income_page_view(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_PAGE_VIEW);

        if (!$income_value) {
            return $this;
        }

        try {
            $this->points_addition($income_value)
                ->logger->log_income(
                    'income_' . INCOME_TYPE_PAGE_VIEW,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );
        } catch (Exception $e) {
            log_error(
                $this->instance_id,
                $e->getMessage(),
                user_id: $this->get_user_id(),
                post_id: $this->get_post_id(),
                thread_id: $this->get_thread_id(),
                forum_id: $this->get_forum_id(),
                income_type: $income_type
            );
        }

        return $this;
    }

    public function income_visit(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_VISIT);

        if (!$income_value) {
            return $this;
        }

        if (!((TIME_NOW - $this->user_data['lastactive']) > $this->user_permissions[IncomePermissions::UserIncomeVisitMinutes] * 60)) {
            return $this;
        }

        try {
            $this->points_addition($income_value)
                ->logger->log_income(
                    'income_' . INCOME_TYPE_VISIT,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );
        } catch (Exception $e) {
            log_error(
                $this->instance_id,
                $e->getMessage(),
                user_id: $this->get_user_id(),
                post_id: $this->get_post_id(),
                thread_id: $this->get_thread_id(),
                forum_id: $this->get_forum_id(),
                income_type: $income_type
            );
        }

        return $this;
    }

    public function income_thread_reply(?int $multiplier = null, int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_REPLY);

        if ($multiplier !== null) {
            $income_value *= $multiplier;
        }

        if ($income_value) {
            if ($income_type === LOGGING_TYPE_CHARGE) {
                try {
                    $this->points_subtraction($income_value)
                        ->logger->log_charge(
                            'income_' . INCOME_TYPE_THREAD_REPLY,
                            $income_value,
                            $this->post_id,
                            $this->thread_id,
                            $this->forum_id,
                        );
                } catch (Exception $e) {
                    log_error(
                        $this->instance_id,
                        $e->getMessage(),
                        user_id: $this->get_user_id(),
                        post_id: $this->get_post_id(),
                        thread_id: $this->get_thread_id(),
                        forum_id: $this->get_forum_id(),
                        income_type: $income_type
                    );
                }
            } else {
                try {
                    $this->points_addition($income_value)
                        ->logger->log_income(
                            'income_' . INCOME_TYPE_THREAD_REPLY,
                            $this->user_id,
                            $income_value,
                            $this->post_id,
                            $this->thread_id,
                            $this->forum_id,
                        );
                } catch (Exception $e) {
                    log_error(
                        $this->instance_id,
                        $e->getMessage(),
                        user_id: $this->get_user_id(),
                        post_id: $this->get_post_id(),
                        thread_id: $this->get_thread_id(),
                        forum_id: $this->get_forum_id(),
                        income_type: $income_type
                    );
                }
            }
        }

        return $this;
    }

    public function income_thread(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_THREAD,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_THREAD,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    public function income_thread_rating(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_RATE);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_THREAD_RATE,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_THREAD_RATE,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    // todo, logic for rating delete is missing
    public function income_post(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POST);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_POST,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_POST,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    public function income_post_characters(
        ?string $message = null,
        ?int $characters_count = null,
        int $income_type = LOGGING_TYPE_INCOME
    ): self {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        if ($characters_count === null) {
            $characters_count = count_characters($message);
        }

        $income_value = 0;

        if ($characters_count >= $this->user_permissions[IncomePermissions::UserIncomePostMinimumCharacters]) {
            $income_value = $characters_count * $this->get_income_value(INCOME_TYPE_POST_CHARACTER);
        }

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_POST_CHARACTER,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_POST_CHARACTER,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function income_poll(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            $thread_data = get_thread($this->get_thread_id());

            if (empty($thread_data['poll'])) {
                return $this;
            }

            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_POLL,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_POLL,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    public function income_poll_vote(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL_VOTE);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_POLL_VOTE,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_POLL_VOTE,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    public function income_registration(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REGISTRATION);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_USER_REGISTRATION,
                        $income_value,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_USER_REGISTRATION,
                        $income_value,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    public function income_referral(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REFERRAL);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_USER_REFERRAL,
                        $income_value,
                        $this->primary_id,
                        $this->secondary_id,
                        $this->tertiary_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_USER_REFERRAL,
                        $income_value,
                        $this->primary_id,
                        $this->secondary_id,
                        $this->tertiary_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    public function income_private_message(int $income_type = LOGGING_TYPE_INCOME): self
    {
        if (!$this->get_user_permission_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_PRIVATE_MESSAGE);

        if (!$income_value) {
            return $this;
        }

        if ($income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_PRIVATE_MESSAGE,
                        $income_value,
                        $this->primary_id,
                        $this->secondary_id,
                        $this->tertiary_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        } else {
            try {
                $this->points_addition($income_value)
                    ->logger->log_income(
                        'income_' . INCOME_TYPE_PRIVATE_MESSAGE,
                        $income_value,
                        $this->primary_id,
                        $this->secondary_id,
                        $this->tertiary_id,
                    );
            } catch (Exception $e) {
                log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $income_type
                );
            }
        }

        return $this;
    }

    public function charge_thread_reply(?int $multiplier = null): self
    {
        return $this->income_thread_reply($multiplier, LOGGING_TYPE_CHARGE);
    }

    public function charge_thread(): self
    {
        return $this->income_thread(LOGGING_TYPE_CHARGE);
    }

    public function charge_post(): self
    {
        return $this->income_post(LOGGING_TYPE_CHARGE);
    }

    public function charge_post_characters(?string $message = null, ?int $characters_count = null): self
    {
        return $this->income_post_characters($message, $characters_count, LOGGING_TYPE_CHARGE);
    }

    public function charge_poll(): self
    {
        return $this->income_poll(LOGGING_TYPE_CHARGE);
    }

    // todo, the logic for this is missing
    public function charge_poll_vote(): self
    {
        return $this->income_poll_vote(LOGGING_TYPE_CHARGE);
    }

    public function settings_get_value(string $setting_key = ''): bool|string|int|float
    {
        return get_setting($setting_key, $this->instance_id);
    }

    public function set_menu_item(array $menu_item): self
    {
        $this->menu_items[] = $menu_item;

        return $this;
    }

    /**
     * Adds/Subtracts points to a user
     *
     * @param float $points the number of points to add or subtract (if a negative value)
     * @param bool $income_type
     * @return Instance
     * @throws Exception
     */
    public function points_addition(float $points, int $income_type = LOGGING_TYPE_INCOME): self
    {
        global $db;

        $points = abs($points);

        if (!$points) {
            throw new Exception('Invalid points value');
        }

        if (!$this->user_id) {
            throw new Exception('Invalid user ID');
        }

        $points_rounded = round(
            $points,
            (int)$this->instance_data['decimal_digits']
        );

        $instance_column_name = $this->users_column_get();

        if ($income_type === LOGGING_TYPE_INCOME) {
            try {
                $db->write_query(
                    'UPDATE `' . $db->table_prefix . 'users`
SET `' . $instance_column_name . '`=`' . $instance_column_name . '`+(' . $points_rounded . ')
WHERE `uid`=\'' . $this->user_id . '\''
                );
            } catch (Exception $e) {
            }
        } else {
            try {
                $db->write_query(
                    'UPDATE `' . $db->table_prefix . 'users`
SET `' . $instance_column_name . '`=`' . $instance_column_name . '`-(' . $points_rounded . ')
WHERE `uid`=\'' . $this->user_id . '\''
                );
            } catch (Exception $e) {
            }
        }

        return $this;
    }

    public function points_subtraction(float $points): self
    {
        return $this->points_addition($points, LOGGING_TYPE_CHARGE);
    }

    /**
     * Formats points according to the settings
     *
     * @param float $points the amount of points
     * @return string formated points
     *
     */
    public function points_format(float $points): string
    {
        $currency_prefix = $this->instance_data['currency_suffix'] ?? '';

        $points_formatted = my_number_format(round($points, (int)($this->instance_data['decimal_digits'] ?? 0)));

        $currency_suffix = $this->instance_data['currency_prefix'] ?? '';

        return eval(templates_get('points_format', false));
    }

    public function permissions_group_insert(
        array $permission_data,
        bool $is_update = false,
        int $permission_id = 0
    ): int {
        global $db;

        $insert_data = [];

        $hook_arguments = [
            'insert_data' => &$insert_data,
            'permission_data' => &$permission_data,
            'is_update' => $is_update,
            'instance_id' => $this->instance_id,
            'permission_id' => &$permission_id,
        ];

        $hook_arguments = $this->run_hooks('permissions_group_insert_update_start', $hook_arguments);

        foreach ($this->tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
            if (isset($permission_data[$field_name])) {
                $insert_data[$field_name] = match ($field_definition['type']) {
                    'INT', 'TINYINT', 'SMALLINT' => (int)$permission_data[$field_name],
                    'FLOAT', 'DECIMAL' => (float)$permission_data[$field_name],
                    default => $db->escape_string($permission_data[$field_name]),
                };
            }
        }

        global $db;

        if ($is_update) {
            $db->update_query('newpoints_group_permissions', $insert_data, "permission_id='{$permission_id}'");
        } else {
            $permission_id = (int)$db->insert_query('newpoints_group_permissions', $insert_data);
        }

        return $permission_id;
    }

    public function permissions_group_update(array $permission_data, int $permission_id): int
    {
        return $this->permissions_group_insert($permission_data, true, $permission_id);
    }

    public function permissions_group_get(
        array $where_clauses = [],
        array $query_fields = [],
        array $query_options = []
    ): array {
        global $db;

        $query = $db->simple_select(
            'newpoints_group_permissions',
            implode(',', array_merge(['permission_id'], $query_fields)),
            implode(' AND ', $where_clauses),
            $query_options
        );

        if (isset($query_options['limit']) && $query_options['limit'] === 1) {
            return (array)$db->fetch_array($query);
        }

        $permission_objects = [];

        while ($permission_data = $db->fetch_array($query)) {
            $permission_data['permission_id'] = (int)$permission_data['permission_id'];

            $permission_objects[$permission_data['permission_id']] = $permission_data;
        }

        return $permission_objects;
    }

    public function permissions_group_delete(int $permission_id): bool
    {
        global $db;

        $hook_arguments = [
            'permission_id' => &$permission_id,
        ];

        $hook_arguments = $this->run_hooks('permissions_group_delete_start', $hook_arguments);

        try {
            $db->delete_query('newpoints_group_permissions', "permission_id='{$permission_id}'");

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function permissions_forum_insert(
        array $permission_data,
        bool $is_update = false,
        int $permission_id = 0
    ): int {
        global $db;

        $insert_data = [];

        $hook_arguments = [
            'insert_data' => &$insert_data,
            'permission_data' => &$permission_data,
            'is_update' => $is_update,
            'instance_id' => $this->instance_id,
            'permission_id' => &$permission_id,
        ];

        $hook_arguments = $this->run_hooks('permissions_forum_insert_update_start', $hook_arguments);

        foreach ($this->tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
            if (isset($permission_data[$field_name])) {
                $insert_data[$field_name] = match ($field_definition['type']) {
                    'INT', 'TINYINT', 'SMALLINT' => (int)$permission_data[$field_name],
                    'FLOAT', 'DECIMAL' => (float)$permission_data[$field_name],
                    default => $db->escape_string($permission_data[$field_name]),
                };
            }
        }

        global $db;

        if ($is_update) {
            $db->update_query('newpoints_forum_permissions', $insert_data, "permission_id='{$permission_id}'");
        } else {
            $permission_id = (int)$db->insert_query('newpoints_forum_permissions', $insert_data);
        }

        return $permission_id;
    }

    public function permissions_forum_update(array $permission_data, int $permission_id): int
    {
        return $this->permissions_forum_insert($permission_data, true, $permission_id);
    }

    public function permissions_forum_get(
        array $where_clauses = [],
        array $query_fields = [],
        array $query_options = []
    ): array {
        global $db;

        $query = $db->simple_select(
            'newpoints_forum_permissions',
            implode(',', array_merge(['permission_id'], $query_fields)),
            implode(' AND ', $where_clauses),
            $query_options
        );

        if (isset($query_options['limit']) && $query_options['limit'] === 1) {
            return (array)$db->fetch_array($query);
        }

        $permission_objects = [];

        while ($permission_data = $db->fetch_array($query)) {
            $permission_data['permission_id'] = (int)$permission_data['permission_id'];

            $permission_objects[$permission_data['permission_id']] = $permission_data;
        }

        return $permission_objects;
    }

    public function permissions_forum_delete(int $permission_id): bool
    {
        global $db;

        $hook_arguments = [
            'permission_id' => &$permission_id,
        ];

        $hook_arguments = $this->run_hooks('permissions_forum_delete_start', $hook_arguments);

        try {
            $db->delete_query('newpoints_forum_permissions', "permission_id='{$permission_id}'");

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function cache_update_group_permissions(): array
    {
        global $db, $cache;

        $cache_data = [];

        $hook_arguments = [
            'cache_data' => &$cache_data,
        ];

        $hook_arguments = $this->run_hooks('cache_update_groups_start', $hook_arguments);

        $permissions_objects = $this->permissions_group_get(
            [],
            array_keys($this->tables_data['newpoints_group_permissions'])
        );

        foreach ($permissions_objects as $permission_id => $permission_data) {
            $group_id = (int)$permission_data['group_id'];

            $cache_data[(int)$permission_data['instance_id']][$group_id] = [];

            foreach ($this->tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
                if (isset($permission_data[$field_name])) {
                    $cache_data[(int)$permission_data['instance_id']][$group_id][$field_name] = match ($field_definition['type']) {
                        'INT', 'TINYINT', 'SMALLINT' => (int)$permission_data[$field_name],
                        'FLOAT', 'DECIMAL' => (float)$permission_data[$field_name],
                        default => $db->escape_string($permission_data[$field_name]),
                    };
                }
            }
        }

        $hook_arguments = $this->run_hooks('cache_update_groups_end', $hook_arguments);

        $cache->update('newpoints_group_permissions', $cache_data);

        return $cache_data;
    }

    public function cache_update_forum_permissions(): array
    {
        global $db, $cache;

        $cache_data = [];

        $hook_arguments = [
            'cache_data' => &$cache_data,
        ];

        $hook_arguments = $this->run_hooks('cache_update_forums_start', $hook_arguments);

        $permissions_objects = $this->permissions_forum_get(
            [],
            array_keys($this->tables_data['newpoints_forum_permissions'])
        );

        foreach ($permissions_objects as $permission_id => $permission_data) {
            $forum_id = (int)$permission_data['forum_id'];

            $cache_data[(int)$permission_data['instance_id']][$forum_id] = [];

            foreach ($this->tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
                if (isset($permission_data[$field_name])) {
                    $cache_data[(int)$permission_data['instance_id']][$forum_id][$field_name] = match ($field_definition['type']) {
                        'INT', 'TINYINT', 'SMALLINT' => (int)$permission_data[$field_name],
                        'FLOAT', 'DECIMAL' => (float)$permission_data[$field_name],
                        default => $db->escape_string($permission_data[$field_name]),
                    };
                }
            }
        }

        $hook_arguments = $this->run_hooks('cache_update_forums_end', $hook_arguments);

        $cache->update('newpoints_forum_permissions', $cache_data);

        return $cache_data;
    }

    public function cache_get_group_permissions(): array
    {
        global $cache;

        return (array)$cache->read('newpoints_group_permissions');
    }

    public function cache_get_forum_permissions(): array
    {
        global $cache;

        $cache_data = $cache->read('newpoints_forum_permissions')[$this->instance_id] ?? [];

        if (!$cache_data) {
            global $cache;

            $cache_data = $cache->read('forums') ?? [];
        }

        return $cache_data;
    }

    public function run_hooks(string $hook_name = '', array|Instance &$hook_arguments = []): array|Instance
    {
        if (!$this->plugins_enabled()) {
            return $hook_arguments;
        }

        return run_hooks($hook_name, $hook_arguments);
    }

    public function get_instance_data(): array
    {
        return $this->instance_data;
    }
}

// todo, maybe check displaygroup when building permissions ?