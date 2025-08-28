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

use function Newpoints\Core\count_characters;
use function Newpoints\Core\get_income_value;
use function Newpoints\Core\instance_get;
use function Newpoints\Core\instance_object;
use function Newpoints\Core\points_add_simple;
use function Newpoints\Core\points_subtract;
use function Newpoints\Core\user_can_get_points;
use function Newpoints\Core\users_get_group_permissions;

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

class Instance
{
    private array $instance_data = [];

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

    /**
     * @throws Exception
     */
    public function __construct(int $instance_id)
    {
        if (!($this->instance_data = instance_get($instance_id))) {
            throw new Exception("Instance with ID $instance_id does not exist.");
        }

        $this->instance_id = (int)$this->instance_data['instance_id'];

        require_once MYBB_ROOT . 'inc/plugins/newpoints/system/logger.php';

        $this->logger = new Logger($this);
    }

    public function set(string $class): void
    {
        $this->$class = new $class($this);
    }

    protected function get_display_name_singular(): string
    {
        return $this->instance_data['display_name_singular'] ?? $this->instance_data['display_name_plural'];
    }

    protected function get_display_name_plural(): string
    {
        return $this->instance_data['display_name_plural'];
    }

    public function get_enable_notifications_private_message(): bool
    {
        return !empty($this->instance_data['enable_notifications_private_message']);
    }

    public function get_users_column_name()
    {
        return $this->instance_data['users_column_name'] ?? 'newpoints';
    }

    public function set_user(int $user_id): void
    {
        $this->user_id = $user_id;
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

    protected function get_enable_notifications_alert(): bool
    {
        return !empty($this->instance_data['enable_notifications_alert']);
    }

    public function get_display_name_upper(?float $points = null): string
    {
        if ($points === null || (int)$points != $points || $points > 1) {
            return ucwords($this->get_display_name_plural());
        }

        return ucwords($this->get_display_name_singular());
    }

    public function get_display_name_lower(?float $points = null): string
    {
        if ($points === null || (int)$points != $points || $points > 1) {
            return my_strtolower($this->get_display_name_plural());
        }

        return my_strtolower($this->get_display_name_singular());
    }

    private function user_can_get_points(): bool
    {
        return user_can_get_points($this->user_id, $this->forum_id, $this->instance_id);
    }

    private function get_income_value(string $income_type): float
    {
        return get_income_value($income_type, $this->user_id, $this->forum_id, $this->instance_id);
    }

    public function income_page_view(): bool
    {
        if (!$this->user_can_get_points()) {
            return false;
        }

        global $mybb;

        $income_value = $this->get_income_value(INCOME_TYPE_PAGE_VIEW);

        $income_value *= $mybb->usergroup[IncomeRates::RateAddition];

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

                points_add_simple(
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
        if (!$this->user_can_get_points()) {
            return false;
        }

        global $mybb;

        $income_value = $this->get_income_value(INCOME_TYPE_VISIT);

        $income_value *= $mybb->usergroup[IncomeRates::RateAddition];

        if ($income_value &&
            (TIME_NOW - $mybb->user['lastactive']) > $mybb->usergroup[IncomePermissions::UserIncomeVisitMinutes] * 60) {
            try {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_VISIT,
                    $this->user_id,
                    $income_value,
                    $this->post_id,
                    $this->thread_id,
                    $this->forum_id,
                );

                points_add_simple(
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
        if (!$this->user_can_get_points()) {
            return false;
        }

        // we are not the thread started so remove points from him/her
        $user_permissions = users_get_group_permissions($this->user_id);

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_REPLY);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                    points_subtract(
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

                    points_add_simple(
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

    public function charge_thread_reply(?int $multiplier = null): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_thread_reply($multiplier);
    }

    public function income_thread(?string $message = null): bool
    {
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
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

                points_add_simple(
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

    public function charge_thread(): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_thread();
    }

    public function income_thread_rating(?string $message = null): bool
    {
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_THREAD_RATE);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
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

                points_add_simple(
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
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POST);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
        }

        if (!$income_value) {
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

                points_subtract(
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

                points_add_simple(
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

    public function charge_post(): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_post();
    }

    public function income_post_characters(?string $message = null, ?int $characters_count = null): bool
    {
        if (!$this->user_can_get_points()) {
            return false;
        }

        // calculate points per character bonus
        // let's see if the number of characters in the post is greater than the minimum characters
        if ($characters_count === null) {
            $characters_count = count_characters($message);
        }

        $income_value = 0;

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($characters_count >= $user_permissions[IncomePermissions::UserIncomePostMinimumCharacters]) {
            $income_value = $characters_count * $this->get_income_value(INCOME_TYPE_POST_CHARACTER);
        }

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
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

                points_add_simple(
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

    public function charge_post_characters(?string $message = null, ?int $characters_count = null): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_post_characters($message, $characters_count);
    }

    public function income_poll(): bool
    {
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
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

                points_add_simple(
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

    public function charge_poll(): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_poll();
    }

    public function income_poll_vote(): bool
    {
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_POLL_VOTE);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
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

                points_add_simple(
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

    // todo, the logic for this is missing
    public function charge_poll_vote(): bool
    {
        $this->set_income_type(LOGGING_TYPE_CHARGE);

        return $this->income_poll_vote();
    }

    public function income_registration(): bool
    {
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REGISTRATION);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
                    $this->user_id,
                    $income_value
                );
            } else {
                $this->logger->log_income(
                    'income_' . INCOME_TYPE_USER_REGISTRATION,
                    $this->user_id,
                    $income_value,
                );

                points_add_simple(
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
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_USER_REFERRAL);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
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

                points_add_simple(
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
        if (!$this->user_can_get_points()) {
            return false;
        }

        $income_value = $this->get_income_value(INCOME_TYPE_PRIVATE_MESSAGE);

        $user_permissions = users_get_group_permissions($this->user_id);

        if ($this->income_type === LOGGING_TYPE_CHARGE) {
            $income_value *= ($user_permissions[IncomeRates::RateSubtraction] / 100);
        } else {
            $income_value *= $user_permissions[IncomeRates::RateAddition];
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

                points_subtract(
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

                points_add_simple(
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
}