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

namespace Newpoints\System;

use Exception;
use Newpoints\Core\IncomePermissions;
use Newpoints\Core\IncomeRates;
use Newpoints\Core\Permissions;

use function Newpoints\Core\cache_get_instances;
use function Newpoints\Core\count_characters;
use function Newpoints\Core\get_setting;
use function Newpoints\Core\run_hooks;
use function Newpoints\Core\templates_get;

use const Newpoints\Core\URL;
use const Newpoints\Core\ALL_UNLIMITED_VALUE;
use const Newpoints\Core\GUEST_GROUP_ID;
use const Newpoints\Core\INCOME_TYPE_USER_ALLOWANCE;
use const Newpoints\Core\INCOME_TYPE_PAGE_VIEW;
use const Newpoints\Core\INCOME_TYPE_POLL;
use const Newpoints\Core\INCOME_TYPE_POLL_VOTE;
use const Newpoints\Core\INCOME_TYPE_POST;
use const Newpoints\Core\INCOME_TYPE_POST_CHARACTER;
use const Newpoints\Core\INCOME_TYPE_PRIVATE_MESSAGE;
use const Newpoints\Core\INCOME_TYPE_THREAD;
use const Newpoints\Core\INCOME_TYPE_THREAD_RATE;
use const Newpoints\Core\INCOME_TYPE_THREAD_REPLY;
use const Newpoints\Core\INCOME_TYPE_USER_REFERRAL;
use const Newpoints\Core\INCOME_TYPE_USER_REGISTRATION;
use const Newpoints\Core\INCOME_TYPE_VISIT;
use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;
use const Newpoints\Core\TABLES_DATA;

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

    private int $income_type = LOGGING_TYPE_INCOME;

    private int $user_id = 0;

    private array $user_data = [];

    public array $user_permissions = [];

    public string $user_groups = '';

    private array $tables_data = [];

    public \Newpoints\System\Url $url;

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

        $this->url = new \Newpoints\System\Url($this->get_script_name());

        if ($user_id <= 0) {
            global $mybb;

            $this->user_id = (int)$mybb->user['uid'];

            $this->user_data = &$mybb->user;
        } else {
            $this->user_id = $user_id;

            $this->user_data = get_user($this->user_id);
        }

        $this->user_groups = ($this->user_data['usergroup'] ?? '') . ',' . ($this->user_data['additionalgroups'] ?? '');

        run_hooks('instance_construct_start', $this);

        $this->user_permissions = $this->get_user_permissions();
    }

    public function get_user_data(): array
    {
        return $this->user_data;
    }

    public function get_user_column_value(): float
    {
        return (float)$this->get_user_data()[$this->users_column_get()];
    }

    public function is_enabled(): bool
    {
        return !empty($this->instance_data['is_enabled']) && $this->users_column_exists();
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

    private function set_income_type(int $income_type = LOGGING_TYPE_INCOME): void
    {
        if ($income_type === LOGGING_TYPE_INCOME) {
            $this->income_type = LOGGING_TYPE_INCOME;
        } elseif ($income_type === LOGGING_TYPE_CHARGE) {
            $this->income_type = LOGGING_TYPE_CHARGE;
        }
    }

    public function append_tables_data(string $table_name, array $fields_data): void
    {
        $this->tables_data[$table_name] = array_merge(
            $this->tables_data[$table_name] ?? [],
            $fields_data,
        );
    }

    public function get_data(): array
    {
        return $this->instance_data;
    }

    public function get_user_id(): int
    {
        return $this->user_id ?? 0;
    }

    private function get_user_permissions(): array
    {
        static $user_permissions = [];

        if (isset($user_permissions[$this->instance_id][$this->user_id])) {
            return $user_permissions[$this->instance_id][$this->user_id];
        }

        $user_permissions[$this->instance_id][$this->user_id] = user_permissions($this->user_id);

        if ($this->user_id) {
            $user_groups_ids = array_filter(
                array_map(
                    'intval',
                    explode(',', "{$this->user_data['usergroup']},{$this->user_data['additionalgroups']}")
                )
            );

            $data_fields = $this->tables_data['newpoints_group_permissions'];

            foreach ($data_fields as $permission_name => $data_field_data) {
                if (!isset($data_field_data['is_permission'])) {
                    continue;
                }

                foreach ($user_groups_ids as $group_id) {
                    $group_permissions = $this->get_group_permissions($group_id);

                    if (!empty($data_field_data['zero_unlimited']) && empty($group_permissions[$permission_name]) ||
                        !empty($data_field_data['zero_unlimited']) && isset($user_permissions[$this->instance_id][$this->user_id][$permission_name]) && empty($user_permissions[$this->instance_id][$this->user_id][$permission_name])) {
                        $user_permissions[$this->instance_id][$this->user_id][$permission_name] = ALL_UNLIMITED_VALUE;

                        continue 2;
                    }

                    if (isset($user_permissions[$this->instance_id][$this->user_id][$permission_name])) {
                        if (empty($data_field_data['lowest'])) {
                            $user_permissions[$this->instance_id][$this->user_id][$permission_name] = max(
                                $user_permissions[$this->instance_id][$this->user_id][$permission_name],
                                $group_permissions[$permission_name]
                            );
                        } else {
                            $user_permissions[$this->instance_id][$this->user_id][$permission_name] = min(
                                $user_permissions[$this->instance_id][$this->user_id][$permission_name],
                                $group_permissions[$permission_name]
                            );
                        }
                    } else {
                        $user_permissions[$this->instance_id][$this->user_id][$permission_name] = $group_permissions[$permission_name];
                    }
                }
            }
        } else {
            $user_permissions[$this->instance_id][$this->user_id] = array_merge(
                $user_permissions[$this->instance_id][$this->user_id],
                $this->get_group_permissions()
            );
        }

        return $user_permissions[$this->instance_id][$this->user_id];
    }

    public function get_user_permissions_rate_addition(string $permission_key = IncomeRates::RateAddition): float
    {
        $user_rate = 1;

        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions()[$this->instance_id] ?? [];

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                $user_rate *= $custom_permissions[$permission_key];
            } else {
                $forum_permissions = fetch_forum_permissions($this->forum_id, $this->user_groups, []);

                if (isset($forum_permissions[$permission_key])) {
                    $user_rate *= $forum_permissions[$permission_key];
                } else {
                    global $cache;

                    $forum_cache = $cache->read('forums');

                    $forum_data = $forum_cache[$this->forum_id] ?? [];

                    if (isset($forum_data[$permission_key])) {
                        $user_rate *= $forum_data[$permission_key];
                    }
                }
            }
        }

        return ($user_rate * $this->user_permissions[$permission_key]);
    }

    public function get_user_permissions_rate_substraction(
        string $permission_key = IncomeRates::RateSubtraction,
        bool $current_user = false
    ): float {
        $user_rate = 1;

        if ($this->forum_id) {
            if ($current_user) {
                $forum_permissions = fetch_forum_permissions($this->forum_id, $this->user_groups, []);
            } else {
                $forum_permissions = fetch_forum_permissions($this->forum_id, $this->user_groups, []);
            }

            if (isset($forum_permissions[$permission_key])) {
                $user_rate *= ($forum_permissions[$permission_key] / 100);
            } else {
                global $cache;

                $forum_cache = $cache->read('forums');

                $forum_data = $forum_cache[$this->forum_id] ?? [];

                if (isset($forum_data[$permission_key])) {
                    $user_rate *= ($forum_data[$permission_key] / 100);
                }
            }
        }

        if ($current_user) {
            $user_rate *= ($this->user_permissions[$permission_key] / 100);
        } else {
            $user_rate *= ($this->user_permissions[$permission_key] / 100);
        }

        return $user_rate;
    }


    public function get_user_permissions_boolean(string $permission_key): bool
    {
        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions()[$this->instance_id] ?? [];

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                return !empty($custom_permissions[$permission_key]);
            }

            $forum_permissions = fetch_forum_permissions($this->forum_id, $this->user_groups, []);

            if (isset($forum_permissions[$permission_key])) {
                return !empty($forum_permissions[$permission_key]);
            }

            global $cache;

            $forum_cache = $cache->read('forums');

            $forum_data = $forum_cache[$this->forum_id] ?? [];

            if (isset($forum_data[$permission_key])) {
                return !empty($forum_data[$permission_key]);
            }
        }

        return !empty($this->user_permissions[$permission_key]);
    }

    public function get_user_permissions_int(string $permission_key): int
    {
        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions()[$this->instance_id] ?? [];

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                return (int)$custom_permissions[$permission_key];
            }

            $forum_permissions = fetch_forum_permissions($this->forum_id, $this->user_groups, []);

            if (isset($forum_permissions[$permission_key])) {
                return (int)$forum_permissions[$permission_key];
            }

            global $cache;

            $forum_cache = $cache->read('forums');

            $forum_data = $forum_cache[$this->forum_id] ?? [];

            if (isset($forum_data[$permission_key])) {
                return (int)$forum_data[$permission_key];
            }
        }

        return (int)$this->user_permissions[$permission_key];
    }

    public function get_user_permissions_float(string $permission_key): float
    {
        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions()[$this->instance_id] ?? [];

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                return (float)$custom_permissions[$permission_key];
            }

            $forum_permissions = fetch_forum_permissions($this->forum_id, $this->user_groups, []);

            if (isset($forum_permissions[$permission_key])) {
                return (float)$forum_permissions[$permission_key];
            }

            global $cache;

            $forum_cache = $cache->read('forums');

            $forum_data = $forum_cache[$this->forum_id] ?? [];

            if (isset($forum_data[$permission_key])) {
                return (float)$forum_data[$permission_key];
            }
        }

        return (float)$this->user_permissions[$permission_key];
    }

    public function get_group_permissions(int $group_id = GUEST_GROUP_ID): array
    {
        static $group_permissions = [];

        if (isset($group_permissions[$this->instance_id][$group_id])) {
            return $group_permissions[$this->instance_id][$group_id];
        }

        $group_permissions[$this->instance_id][$group_id] = [];

        $data_fields = $this->tables_data['newpoints_group_permissions'];

        $hook_arguments = [
            'data_fields' => &$data_fields,
        ];

        // todo, similar to `admin_user_groups_edit_graph_start`
        $hook_arguments = run_hooks('admin_user_groups_edit_graph_start', $hook_arguments);

        foreach ($data_fields as $data_field_key => $data_field_data) {
            if (!isset($data_field_data['is_permission'])) {
                continue;
            }

            $group_permissions[$this->instance_id][$group_id][$data_field_key] = $data_field_data['default'];
        }

        global $cache;

        $groups_cache = (array)$cache->read('usergroups');

        foreach ($groups_cache[$group_id] as $permission_key => $permission_value) {
            if (str_starts_with($permission_key, 'newpoints_') &&
                isset($group_permissions[$this->instance_id][$group_id][$permission_key])) {
                $group_permissions[$this->instance_id][$group_id][$permission_key] = match ($data_fields[$permission_key]['type']) {
                    'INT', 'TINYINT', 'SMALLINT' => (int)$permission_value,
                    'FLOAT', 'DECIMAL' => (float)$permission_value,
                    default => $permission_value,
                };
            }
        }

        $permissions_cache = $this->cache_get_group_permissions();

        if (!empty($permissions_cache[$this->instance_id][$group_id])) {
            $group_permissions[$this->instance_id][$group_id] = array_merge(
                $group_permissions[$this->instance_id][$group_id],
                $permissions_cache[$this->instance_id][$group_id]
            );
        }

        return $group_permissions[$this->instance_id][$group_id];
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

    public function get_income_value(string $income_type): float
    {
        $income_value = 1;

        $global_setting_key = 'income_' . $income_type;

        $group_setting_key = 'newpoints_income_' . $income_type;

        switch ($income_type) {
            case INCOME_TYPE_THREAD:
            case INCOME_TYPE_THREAD_REPLY:
            case INCOME_TYPE_THREAD_RATE:
            case INCOME_TYPE_POST:
            case INCOME_TYPE_POST_CHARACTER:
            case INCOME_TYPE_PAGE_VIEW:
            case INCOME_TYPE_VISIT:
            case INCOME_TYPE_POLL:
            case INCOME_TYPE_POLL_VOTE:
            case INCOME_TYPE_USER_ALLOWANCE:
            case INCOME_TYPE_USER_REGISTRATION:
            case INCOME_TYPE_USER_REFERRAL:
            case INCOME_TYPE_PRIVATE_MESSAGE:
                $income_value = get_setting($global_setting_key, $this->instance_id) === false ?
                    $this->user_permissions[$group_setting_key] :
                    get_setting($global_setting_key, $this->instance_id);
                break;
        }

        if ($this->forum_id) {
            $forum_data = get_forum($this->forum_id);

            $income_value *= $forum_data[Permissions::Rate];
        }

        return (float)$income_value;
    }

    public function get_income_type(): int
    {
        return $this->income_type;
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

    public function income_page_view(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_PAGE_VIEW)
            * $this->get_user_permissions_rate_addition();

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
            \Newpoints\Core\log_error(
                $this->instance_id,
                $e->getMessage(),
                user_id: $this->get_user_id(),
                post_id: $this->get_post_id(),
                thread_id: $this->get_thread_id(),
                forum_id: $this->get_forum_id(),
                income_type: $this->get_income_type(),
            );
        }

        return $this;
    }

    public function income_visit(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_VISIT)
            * $this->get_user_permissions_rate_addition();

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
            \Newpoints\Core\log_error(
                $this->instance_id,
                $e->getMessage(),
                user_id: $this->get_user_id(),
                post_id: $this->get_post_id(),
                thread_id: $this->get_thread_id(),
                forum_id: $this->get_forum_id(),
                income_type: $this->get_income_type(),
            );
        }

        return $this;
    }

    public function income_thread_reply(?int $multiplier = null): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_REPLY);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if ($multiplier !== null) {
            $income_value *= $multiplier;
        }

        if ($income_value) {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                    \Newpoints\Core\log_error(
                        $this->instance_id,
                        $e->getMessage(),
                        user_id: $this->get_user_id(),
                        post_id: $this->get_post_id(),
                        thread_id: $this->get_thread_id(),
                        forum_id: $this->get_forum_id(),
                        income_type: $this->get_income_type(),
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
                    \Newpoints\Core\log_error(
                        $this->instance_id,
                        $e->getMessage(),
                        user_id: $this->get_user_id(),
                        post_id: $this->get_post_id(),
                        thread_id: $this->get_thread_id(),
                        forum_id: $this->get_forum_id(),
                        income_type: $this->get_income_type(),
                    );
                }
            }
        }

        return $this;
    }

    public function income_thread(?string $message = null): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    public function income_thread_rating(?string $message = null): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_RATE);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    // todo, logic for rating delete is missing
    public function income_post(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POST);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    public function income_post_characters(?string $message = null, ?int $characters_count = null): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
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

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    /**
     * @throws Exception
     */
    public function income_poll(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        if (!$this->thread_id || !($thread_data = get_thread($this->thread_id)) || empty($thread_data['poll'])) {
            //return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    public function income_poll_vote(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL_VOTE);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    public function income_registration(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REGISTRATION);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            try {
                $this->points_subtraction($income_value)
                    ->logger->log_charge(
                        'income_' . INCOME_TYPE_USER_REGISTRATION,
                        $income_value,
                    );
            } catch (Exception $e) {
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    public function income_referral(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REFERRAL);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    public function income_private_message(): self
    {
        if (!$this->get_user_permissions_boolean(Permissions::CanGetPoints)) {
            return $this;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_PRIVATE_MESSAGE);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->get_user_permissions_rate_substraction();
        } else {
            $income_value *= $this->get_user_permissions_rate_addition();
        }

        if (!$income_value) {
            return $this;
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
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
                \Newpoints\Core\log_error(
                    $this->instance_id,
                    $e->getMessage(),
                    user_id: $this->get_user_id(),
                    post_id: $this->get_post_id(),
                    thread_id: $this->get_thread_id(),
                    forum_id: $this->get_forum_id(),
                    income_type: $this->get_income_type(),
                );
            }
        }

        return $this;
    }

    public function charge_thread_reply(?int $multiplier = null): self
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_thread_reply($multiplier);
    }

    public function charge_thread(): self
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_thread();
    }

    public function charge_post(): self
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_post();
    }

    public function charge_post_characters(?string $message = null, ?int $characters_count = null): self
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_post_characters($message, $characters_count);
    }

    public function charge_poll(): self
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_poll();
    }

    // todo, the logic for this is missing
    public function charge_poll_vote(): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_poll_vote();
    }

    public function settings_get_value(string $setting_key = ''): bool|string|int|float
    {
        return get_setting($setting_key, $this->instance_id);
    }

    /**
     * Adds/Subtracts points to a user
     *
     * @param float $points the number of points to add or subtract (if a negative value)
     * @param bool $subtract
     * @return Instance
     * @throws Exception
     */
    public function points_addition(float $points, bool $subtract = false): self
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
            (int)$this->settings_get_value('main_decimal')
        );

        $instance_column_name = $this->users_column_get();

        if ($subtract) {
            try {
                $db->write_query(
                    'UPDATE `' . $db->table_prefix . 'users`
SET `' . $instance_column_name . '`=`' . $instance_column_name . '`-(' . $points_rounded . ')
WHERE `uid`=\'' . $this->user_id . '\''
                );
            } catch (Exception $e) {
            }
        } else {
            try {
                $db->write_query(
                    'UPDATE `' . $db->table_prefix . 'users`
SET `' . $instance_column_name . '`=`' . $instance_column_name . '`+(' . $points_rounded . ')
WHERE `uid`=\'' . $this->user_id . '\''
                );
            } catch (Exception $e) {
            }
        }

        return $this;
    }

    public function points_subtraction(float $points): self
    {
        return $this->points_addition($points, true);
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
        $currency_prefix = $this->settings_get_value('main_cursuffix');

        $points_formatted = my_number_format(round($points, (int)$this->settings_get_value('main_decimal')));

        $currency_suffix = $this->settings_get_value('main_curprefix');

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

        $hook_arguments = run_hooks('permissions_group_insert_update_start', $hook_arguments);

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

        $hook_arguments = run_hooks('permissions_group_delete_start', $hook_arguments);

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

        $hook_arguments = run_hooks('permissions_forum_insert_update_start', $hook_arguments);

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

        $hook_arguments = run_hooks('permissions_forum_delete_start', $hook_arguments);

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

        $hook_arguments = run_hooks('cache_update_groups_start', $hook_arguments);

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

        $hook_arguments = run_hooks('cache_update_groups_end', $hook_arguments);

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

        $hook_arguments = run_hooks('cache_update_forums_start', $hook_arguments);

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

        $hook_arguments = run_hooks('cache_update_forums_end', $hook_arguments);

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

        return (array)$cache->read('newpoints_forum_permissions');
    }
}

// todo, maybe check displaygroup when building permissions ?