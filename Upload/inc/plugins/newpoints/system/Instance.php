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
use function Newpoints\Core\instance_object;
use function Newpoints\Core\points_add;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_format;
use function Newpoints\Core\run_hooks;

use const Newpoints\Core\ALL_UNLIMITED_VALUE;
use const Newpoints\Core\GUEST_GROUP_ID;
use const Newpoints\Core\INCOME_TYPE_USER_ALLOWANCE;
use const Newpoints\Core\URL;
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
use const Newpoints\Core\INSTANCE_DEFAULT_ID;
use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;
use const Newpoints\Core\TABLES_DATA;

class Instance
{
    private array $instance_data;

    public int $instance_id;

    public Logger $logger;

    private int $user_id = 0;

    private int $forum_id = 0;

    private int $thread_id = 0;

    private int $primary_id = 0;

    private int $secondary_id = 0;

    private int $tertiary_id = 0;

    public int $post_id = 0;

    private int $income_type = LOGGING_TYPE_INCOME;

    public array $user_permissions = [];

    private int $current_user_id;

    /**
     * @throws Exception
     */
    public function __construct(int $instance_id)
    {
        if (!($this->instance_data = cache_get_instances($instance_id))) {
            throw new Exception("Instance with ID $instance_id does not exist.");
        }

        $this->instance_id = (int)$this->instance_data['instance_id'];

        require_once MYBB_ROOT . 'inc/plugins/newpoints/system/logger.php';

        $this->logger = new Logger($this);

        global $mybb;

        $this->current_user_id = (int)$mybb->user['uid'];

        $this->set_user($this->current_user_id);
    }

    public function set(string $class): void
    {
        $this->$class = new $class($this);
    }

    protected function get_currency_name_singular(): string
    {
        return $this->instance_data['currency_name_singular'] ?? $this->instance_data['currency_name_plural'];
    }

    protected function get_currency_name_plural(): string
    {
        return (string)$this->instance_data['currency_name_plural'];
    }

    public function notifications_private_message_enabled(): bool
    {
        return !empty($this->instance_data['enable_notifications_private_message']);
    }

    public function notifications_alert_enabled(): bool
    {
        return !empty($this->instance_data['enable_notifications_alert']);
    }

    public function get_user_id(): int
    {
        return $this->user_id;
    }

    public function get_data(): array
    {
        return $this->instance_data;
    }

    public function get_users_column_name(): string
    {
        return $this->instance_data['users_column_name'] ?? 'newpoints';
    }

    public function get_script_file(): string
    {
        return $this->instance_data['script_file'] ?? URL;
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

    public function set_user(int $user_id): void
    {
        $this->user_id = $user_id;

        $this->user_permissions = $this->set_user_permissions();
    }

    public function set_forum(int $forum_id): void
    {
        $this->forum_id = $forum_id;
    }

    public function set_thread(int $thread_id): void
    {
        $this->thread_id = $thread_id;
    }

    public function set_post(int $post_id): void
    {
        $this->post_id = $post_id;
    }

    public function set_primary_id(int $primary_id): void
    {
        $this->primary_id = $primary_id;
    }

    public function set_secondary_id(int $secondary_id): void
    {
        $this->secondary_id = $secondary_id;
    }

    public function set_tertiary_id(int $tertiary_id): void
    {
        $this->tertiary_id = $tertiary_id;
    }

    private function set_income_type(int $income_type): void
    {
        if ($income_type === LOGGING_TYPE_INCOME) {
            $this->income_type = LOGGING_TYPE_INCOME;
        } elseif ($income_type === LOGGING_TYPE_CHARGE) {
            $this->income_type = LOGGING_TYPE_CHARGE;
        }
    }

    public function users_column_exists(): bool
    {
        global $db;

        return $db->field_exists($this->instance_data['users_column_name'], 'users');
    }

    public function income_page_view(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_PAGE_VIEW)
            * $this->permission_get_rate_addition();

        if ($income_value) {
            try {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_PAGE_VIEW,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            } catch (Exception $e) {
                // Handle exception
                return false;
            }

            return true;
        }

        return false;
    }

    public function income_visit(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        global $mybb;

        $income_value = $this->get_income_value(INCOME_TYPE_VISIT)
            * $this->permission_get_rate_addition();

        if ($income_value &&
            (TIME_NOW - $mybb->user['lastactive']) > $this->user_permissions[IncomePermissions::UserIncomeVisitMinutes] * 60) {
            try {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_VISIT,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            } catch (Exception $e) {
                // Handle exception
                return false;
            }

            return true;
        }

        return false;
    }

    public function income_thread_reply(?int $multiplier = null): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_REPLY);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if ($multiplier !== null) {
            $income_value *= $multiplier;
        }

        if ($income_value) {
            try {
                if ($this->income_type === LOGGING_TYPE_CHARGE) {
                    $this->logger->log_charge(
                        'income_' . INCOME_TYPE_THREAD_REPLY,
                        $this->user_id,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );

                    $this->points_subtract(
                        $this->user_id,
                        $income_value,
                    );
                } else {
                    instance_object(INSTANCE_DEFAULT_ID)->logger->log_income(
                        'income_' . INCOME_TYPE_THREAD_REPLY,
                        $this->user_id,
                        $income_value,
                        $this->post_id,
                        $this->thread_id,
                        $this->forum_id,
                    );

                    $this->points_add(
                        $this->user_id,
                        $income_value
                    );
                }
            } catch (Exception $e) {
                // Handle exception
                return false;
            }

            return true;
        }

        return false;
    }

    public function income_thread(?string $message = null): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                instance_object(INSTANCE_DEFAULT_ID)->logger->log_charge(
                    'income_' . INCOME_TYPE_THREAD,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_THREAD,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function income_thread_rating(?string $message = null): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_RATE);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_THREAD_RATE,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_THREAD_RATE,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    // todo, logic for rating delete is missing

    public function income_post(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POST);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            if ($this->instance_id === 3) {
                _dump(2, $this->get_income_value(INCOME_TYPE_POST));
            }
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_POST,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_POST,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function income_post_characters(?string $message = null, ?int $characters_count = null): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
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
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_POST_CHARACTER,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_POST_CHARACTER,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function income_poll(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_POLL,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_POLL,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function income_poll_vote(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL_VOTE);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_POLL_VOTE,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_POLL_VOTE,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function income_registration(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REGISTRATION);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_USER_REGISTRATION,
                    $this->user_id,
                    $income_value,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_USER_REGISTRATION,
                    $this->user_id,
                    $income_value,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function income_referral(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REFERRAL);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_USER_REFERRAL,
                    $this->user_id,
                    $income_value,
                    $this->primary_id,
                    $this->secondary_id,
                    $this->tertiary_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_USER_REFERRAL,
                    $this->user_id,
                    $income_value,
                    $this->primary_id,
                    $this->secondary_id,
                    $this->tertiary_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function income_private_message(): bool
    {
        if (!$this->permission_check_boolean(Permissions::CanGetPoints)) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_PRIVATE_MESSAGE);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= $this->permission_get_rate_substraction();
        } else {
            $income_value *= $this->permission_get_rate_addition();
        }

        if (!$income_value) {
            return false;
        }

        try {
            if ($this->income_type === LOGGING_TYPE_CHARGE) {
                $this->logger->log_charge(
                    'income_' . INCOME_TYPE_PRIVATE_MESSAGE,
                    $this->user_id,
                    $income_value,
                    $this->primary_id,
                    $this->secondary_id,
                    $this->tertiary_id,
                );

                $this->points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_PRIVATE_MESSAGE,
                    $this->user_id,
                    $income_value,
                    $this->primary_id,
                    $this->secondary_id,
                    $this->tertiary_id,
                );

                $this->points_add(
                    $this->user_id,
                    $income_value
                );
            }
        } catch (Exception $e) {
            // Handle exception
            return false;
        }

        return true;
    }

    public function charge_thread_reply(?int $multiplier = null): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_thread_reply($multiplier);
    }

    public function charge_thread(): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_thread();
    }

    public function charge_post(): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_post();
    }

    public function charge_post_characters(?string $message = null, ?int $characters_count = null): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_post_characters($message, $characters_count);
    }

    public function charge_poll(): bool
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
     * @param int $user_id the id of the user
     * @param float $points the number of points to add or subtract (if a negative value)
     * Note: some pages (by other plugins) do not run queries on shutdown so adding this to shutdown may not be good if you're not sure if it will run.
     * @return bool
     */
    public function points_add(int $user_id, float $points): bool
    {
        return points_add_simple($user_id, $points, 0, $this->instance_id);
    }

    public function points_subtract(int $user_id, float $points): bool
    {
        return points_add($user_id, -abs($points), 1, 1, false, true, $this->instance_id);
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
        return points_format($points, $this->instance_id);
    }

    public function permissions_group_insert(
        array $permission_data,
        bool $is_update = false,
        int $permission_id = 0
    ): int {
        global $db;

        $tables_data = TABLES_DATA['newpoints_group_permissions'];

        $hook_arguments = [
            'insert_data' => &$insert_data,
            'permission_data' => &$permission_data,
            'is_update' => $is_update,
            'instance_id' => $this->instance_id,
            'permission_id' => &$permission_id,
            'table_fields' => &$tables_data,
        ];

        $insert_data = [];

        foreach ($tables_data as $field_name => $field_definition) {
            if (isset($permission_data[$field_name])) {
                $insert_data[$field_name] = match ($field_definition['type']) {
                    'INT', 'TINYINT', 'SMALLINT' => (int)$permission_data[$field_name],
                    'FLOAT', 'DECIMAL' => (float)$permission_data[$field_name],
                    default => $db->escape_string($permission_data[$field_name]),
                };
            }
        }

        global $db;

        $hook_arguments = run_hooks('permissions_insert_update_end', $hook_arguments);

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
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function permissions_forum_insert(
        array $permission_data,
        bool $is_update = false,
        int $permission_id = 0
    ): int {
        global $db;

        $tables_data = TABLES_DATA['newpoints_forum_permissions'];

        $hook_arguments = [
            'insert_data' => &$insert_data,
            'permission_data' => &$permission_data,
            'is_update' => $is_update,
            'instance_id' => $this->instance_id,
            'permission_id' => &$permission_id,
            'table_fields' => &$tables_data,
        ];

        $insert_data = [];

        foreach ($tables_data as $field_name => $field_definition) {
            if (isset($permission_data[$field_name])) {
                $insert_data[$field_name] = match ($field_definition['type']) {
                    'INT', 'TINYINT', 'SMALLINT' => (int)$permission_data[$field_name],
                    'FLOAT', 'DECIMAL' => (float)$permission_data[$field_name],
                    default => $db->escape_string($permission_data[$field_name]),
                };
            }
        }

        global $db;

        $hook_arguments = run_hooks('permissions_insert_update_end', $hook_arguments);

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

    public function permissions_forum_delete(int $permission_id): bool
    {
        global $db;

        $hook_arguments = [
            'permission_id' => &$permission_id,
        ];

        $hook_arguments = run_hooks('permissions_forum_delete_start', $hook_arguments);

        try {
            $db->delete_query('newpoints_forum_permissions', "permission_id='{$permission_id}'");
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    public function cache_update_group_permissions(): array
    {
        global $db, $cache;

        $cache_data = [];

        $tables_data = TABLES_DATA;

        $hook_arguments = [
            'cache_data' => &$cache_data,
            'table_fields' => &$tables_data,
        ];

        $hook_arguments = run_hooks('cache_update_groups_start', $hook_arguments);

        $permissions_objects = $this->permissions_group_get(
            [],
            array_keys($tables_data['newpoints_group_permissions'])
        );

        foreach ($permissions_objects as $permission_id => $permission_data) {
            $instance_id = (int)$permission_data['instance_id'];

            $group_id = (int)$permission_data['group_id'];

            $cache_data[$instance_id][$group_id] = [];

            foreach ($tables_data['newpoints_group_permissions'] as $field_name => $field_definition) {
                if (isset($permission_data[$field_name])) {
                    $cache_data[$instance_id][$group_id][$field_name] = match ($field_definition['type']) {
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

    public function cache_update_forum_permissions(): array
    {
        global $db, $cache;

        $cache_data = [];

        $tables_data = TABLES_DATA;

        $hook_arguments = [
            'cache_data' => &$cache_data,
            'table_fields' => &$tables_data,
        ];

        $hook_arguments = run_hooks('cache_update_forums_start', $hook_arguments);

        $permissions_objects = $this->permissions_forum_get(
            [],
            array_keys($tables_data['newpoints_forum_permissions'])
        );

        foreach ($permissions_objects as $permission_id => $permission_data) {
            $instance_id = (int)$permission_data['instance_id'];

            $forum_id = (int)$permission_data['forum_id'];

            $cache_data[$instance_id][$forum_id] = [];

            foreach ($tables_data['newpoints_forum_permissions'] as $field_name => $field_definition) {
                if (isset($permission_data[$field_name])) {
                    $cache_data[$instance_id][$forum_id][$field_name] = match ($field_definition['type']) {
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

    public function is_enabled(): bool
    {
        return !empty($this->instance_data['is_enabled']) && $this->users_column_exists();
    }

    /**
     * get group permissions for a specific instance
     *
     * @return array group permissions for the specific instance
     */
    public function permissions_get_group(int $group_id = GUEST_GROUP_ID): array
    {
        static $instance_group_permissions = [];

        if (isset($instance_group_permissions[$this->instance_id][$group_id])) {
            return $instance_group_permissions[$this->instance_id][$group_id];
        }

        $instance_group_permissions[$this->instance_id][$group_id] = [];

        $data_fields = TABLES_DATA['newpoints_group_permissions'];

        $hook_arguments = [
            'data_fields' => &$data_fields,
        ];

        // todo, similar to `admin_user_groups_edit_graph_start`
        $hook_arguments = run_hooks('admin_user_groups_edit_graph_start', $hook_arguments);

        foreach ($data_fields as $data_field_key => $data_field_data) {
            if (!isset($data_field_data['is_permission'])) {
                continue;
            }

            $instance_group_permissions[$this->instance_id][$group_id][$data_field_key] = $data_field_data['default'];
        }

        global $db, $cache;

        $groups_cache = (array)$cache->read('usergroups');

        foreach ($groups_cache[$group_id] as $permission_key => $permission_value) {
            if (str_starts_with($permission_key, 'newpoints_') &&
                isset($instance_group_permissions[$this->instance_id][$group_id][$permission_key])) {
                $instance_group_permissions[$this->instance_id][$group_id][$permission_key] = match ($data_fields[$permission_key]['type']) {
                    'INT', 'TINYINT', 'SMALLINT' => (int)$permission_value,
                    'FLOAT', 'DECIMAL' => (float)$permission_value,
                    default => $permission_value,
                };
            }
        }

        $permissions_cache = $this->cache_get_group_permissions();

        if (!empty($permissions_cache[$this->instance_id][$group_id])) {
            $instance_group_permissions[$this->instance_id][$group_id] = array_merge(
                $instance_group_permissions[$this->instance_id][$group_id],
                $permissions_cache[$this->instance_id][$group_id]
            );
        }

        return $instance_group_permissions[$this->instance_id][$group_id];
    }

    /**
     * get user permissions for a specific instance
     *
     * @return array user permissions for the specific instance
     */
    public function set_user_permissions(): array
    {
        static $user_permissions = [];

        if (!isset($user_permissions[$this->instance_id][$this->user_id])) {
            $user_permissions[$this->instance_id][$this->user_id] = [];

            $user_data = get_user($this->user_id);

            if (!empty($user_data['uid'])) {
                $user_groups_ids = array_filter(
                    array_map(
                        'intval',
                        explode(',', "{$user_data['usergroup']},{$user_data['additionalgroups']}")
                    )
                );

                $data_fields = TABLES_DATA['newpoints_group_permissions'];

                foreach ($data_fields as $permission_name => $data_field_data) {
                    if (!isset($data_field_data['is_permission'])) {
                        continue;
                    }

                    foreach ($user_groups_ids as $group_id) {
                        $group_permissions = $this->permissions_get_group($group_id);

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
                $user_permissions[$this->instance_id][$this->user_id] = $this->permissions_get_group();
            }
        }

        return $user_permissions[$this->instance_id][$this->user_id];
    }

    public function permission_check_boolean(string $permission_key): bool
    {
        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions()[$this->instance_id] ?? [];

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                return !empty($custom_permissions[$permission_key]);
            }

            $forum_permissions = fetch_forum_permissions($this->forum_id, '', []);

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

    public function permission_check_float(string $permission_key): float
    {
        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions()[$this->instance_id] ?? [];

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[$permission_key])) {
                return (float)$custom_permissions[$permission_key];
            }

            $forum_permissions = fetch_forum_permissions($this->forum_id, '', []);

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

    public function permission_get_rate_addition(): float
    {
        $user_rate = 1;

        if ($this->forum_id) {
            $custom_permissions = $this->cache_get_forum_permissions()[$this->instance_id] ?? [];

            $custom_permissions = $custom_permissions[$this->forum_id] ?? [];

            if (isset($custom_permissions[IncomeRates::RateAddition])) {
                $user_rate *= $custom_permissions[IncomeRates::RateAddition];
            } else {
                $forum_permissions = fetch_forum_permissions($this->forum_id, '', []);

                if (isset($forum_permissions[IncomeRates::RateAddition])) {
                    $user_rate *= $forum_permissions[IncomeRates::RateAddition];
                } else {
                    global $cache;

                    $forum_cache = $cache->read('forums');

                    $forum_data = $forum_cache[$this->forum_id] ?? [];

                    if (isset($forum_data[IncomeRates::RateAddition])) {
                        $user_rate *= $forum_data[IncomeRates::RateAddition];
                    }
                }
            }
        }

        $user_rate *= $this->user_permissions[IncomeRates::RateAddition];

        return $user_rate;
    }

    public function permission_get_rate_substraction(): float
    {
        $user_rate = 1;

        if ($this->forum_id) {
            $forum_permissions = fetch_forum_permissions($this->forum_id, '', []);

            if (isset($forum_permissions[IncomeRates::RateSubtraction])) {
                $user_rate *= ($forum_permissions[IncomeRates::RateSubtraction] / 100);
            } else {
                global $cache;

                $forum_cache = $cache->read('forums');

                $forum_data = $forum_cache[$this->forum_id] ?? [];

                if (isset($forum_data[IncomeRates::RateSubtraction])) {
                    $user_rate *= ($forum_data[IncomeRates::RateSubtraction] / 100);
                }
            }
        }

        $user_rate *= ($this->user_permissions[IncomeRates::RateSubtraction] / 100);

        return $user_rate;
    }
}

// todo, maybe check displaygroup when building permissions ?