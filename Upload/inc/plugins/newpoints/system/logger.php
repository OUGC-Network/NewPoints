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

use InvalidArgumentException;
use RuntimeException;

use function Newpoints\Core\alert_send;
use function Newpoints\Core\language_load;
use function Newpoints\Core\private_message_send;

use const Newpoints\Core\LOGGING_TYPE_CHARGE;
use const Newpoints\Core\LOGGING_TYPE_INCOME;
use const Newpoints\Core\PRIVATE_MESSAGE_ENGINE_ID;

class Logger
{
    private Instance $core;

    public function __construct(Instance &$core)
    {
        $this->core = $core;
    }

    /**
     * Create a new log entry
     *
     * @param string $log_action action taken
     * @param int $user_id $uid of who's executed the action
     * @param float $log_points
     * @param int $primary_id
     * @param int $secondary_id
     * @param int $tertiary_id
     * @param int $log_type
     * @return int false if something went wrong
     */
    public function log_action(
        string $log_action,
        int $user_id = 0,
        float $log_points = 0,
        int $primary_id = 0,
        int $secondary_id = 0,
        int $tertiary_id = 0,
        int $log_type = 0
    ): int {
        if (!$log_action) {
            throw new InvalidArgumentException('Log action cannot be empty.');
        }

        if (empty($user_id) || !($user_data = get_user($user_id))) {
            throw new InvalidArgumentException('User ID cannot be empty.');
        }

        $log_points = abs($log_points);

        global $db;

        language_load();

        $log_id = (int)$db->insert_query(
            'newpoints_log',
            [
                'action' => $db->escape_string($log_action),
                'date' => TIME_NOW,
                'uid' => $user_id,
                'username' => $db->escape_string($user_data['username']),
                'points' => $log_points,
                'log_primary_id' => $primary_id,
                'log_secondary_id' => $secondary_id,
                'log_tertiary_id' => $tertiary_id,
                'log_type' => $log_type,
                'instance_id' => $this->core->instance_id
            ]
        );

        if (!$log_id) {
            throw new RuntimeException('Failed to create log entry.');
        }

        switch ($log_type) {
            case LOGGING_TYPE_CHARGE:
                if ($this->core->notifications_private_message_enabled()) {
                    private_message_send(
                        [
                            'language' => $user_data['language'],
                            'subject' => [
                                'newpoints_log_pm_subtract_subject',
                                $this->core->get_display_name_upper($log_points),
                                $this->core->get_display_name_lower($log_points),
                                strip_tags($this->core->points_format($log_points)),
                            ],
                            'message' => [
                                'newpoints_log_pm_subtract_message',
                                $this->core->get_display_name_upper($log_points),
                                $this->core->get_display_name_lower($log_points),
                                $user_data['username'],
                                strip_tags($this->core->points_format($log_points)),
                            ],
                            'touid' => $user_id
                        ],
                        PRIVATE_MESSAGE_ENGINE_ID,
                        true
                    );
                }

                alert_send(
                    $user_id,
                    $log_id,
                    'core',
                    'subtract_points',
                    $this->core->instance_id,
                );
                break;
            default:
                if ($this->core->notifications_private_message_enabled()) {
                    private_message_send(
                        [
                            'language' => $user_data['language'],
                            'subject' => [
                                'newpoints_log_pm_add_subject',
                                $this->core->get_display_name_upper($log_points),
                                $this->core->get_display_name_lower($log_points),
                                strip_tags($this->core->points_format($log_points)),
                            ],
                            'message' => [
                                'newpoints_log_pm_add_message',
                                $this->core->get_display_name_upper($log_points),
                                $this->core->get_display_name_lower($log_points),
                                $user_data['username'],
                                strip_tags($this->core->points_format($log_points)),
                            ],
                            'touid' => $user_id
                        ],
                        PRIVATE_MESSAGE_ENGINE_ID,
                        true
                    );
                }

                alert_send(
                    $user_id,
                    $log_id,
                    'core',
                    'add_points',
                    $this->core->instance_id,
                );
                break;
        }

        return $log_id;
    }

    /**
     * Create a new income log entry
     *
     * @param string $log_action action taken
     * @param int $user_id $uid of who's executed the action
     * @param float $log_points
     * @param int $primary_id
     * @param int $secondary_id
     * @param int $tertiary_id
     * @return int false if something went wrong
     */
    public function log_income(
        string $log_action,
        int $user_id = 0,
        float $log_points = 0,
        int $primary_id = 0,
        int $secondary_id = 0,
        int $tertiary_id = 0
    ): int {
        return $this->log_action(
            $log_action,
            $user_id,
            $log_points,
            $primary_id,
            $secondary_id,
            $tertiary_id,
            LOGGING_TYPE_INCOME
        );
    }

    /**
     * Create a new charge log entry
     *
     * @param string $log_action action taken
     * @param int $user_id $uid of who's executed the action
     * @param float $log_points
     * @param int $primary_id
     * @param int $secondary_id
     * @param int $tertiary_id
     * @return int false if something went wrong
     */
    public function log_charge(
        string $log_action,
        int $user_id = 0,
        float $log_points = 0,
        int $primary_id = 0,
        int $secondary_id = 0,
        int $tertiary_id = 0
    ): int {
        return $this->log_action(
            $log_action,
            $user_id,
            $log_points,
            $primary_id,
            $secondary_id,
            $tertiary_id,
            LOGGING_TYPE_CHARGE
        );
    }
}