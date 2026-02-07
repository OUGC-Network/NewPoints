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

use function NewPoints\Core\alert_send;
use function NewPoints\Core\language_load;
use function NewPoints\Core\private_message_send;

use const NewPoints\Core\LOGGING_TYPE_CHARGE;
use const NewPoints\Core\LOGGING_TYPE_INCOME;

class Logger
{
    private Instance $instance;
    private int $log_id = 0;

    public function __construct(Instance &$instance)
    {
        $this->instance = $instance;
    }

    /**
     * Create a new log entry
     *
     * @param string $log_action action taken
     * @param float $log_points
     * @param int $primary_id
     * @param int $secondary_id
     * @param int $tertiary_id
     * @param int $log_type
     * @param int $user_id $uid of who's executed the action
     * @return Logger false if something went wrong
     */
    public function log_action(
        string $log_action,
        float $log_points = 0,
        int $primary_id = 0,
        int $secondary_id = 0,
        int $tertiary_id = 0,
        int $log_type = 0,
        int $user_id = 0,
    ): self {
        if (!$log_action) {
            throw new Exception('Log action cannot be empty.');
        }
        if ($user_id < 1) {
            $user_id = $this->instance->get_user_id();
        }

        if (!($user_data = get_user($user_id))) {
            throw new Exception('User ID cannot be empty.');
        }

        $log_points = abs($log_points);

        global $db;

        language_load();

        $this->log_id = (int)$db->insert_query(
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
                'instance_id' => $this->instance->instance_id
            ]
        );

        if (!$this->log_id) {
            throw new Exception('Failed to create log entry.');
        }

        switch ($log_type) {
            case LOGGING_TYPE_CHARGE:
                if ($this->instance->notifications_private_message_enabled()) {
                    private_message_send(
                        [
                            'language' => $user_data['language'],
                            'subject' => [
                                'newpoints_log_pm_subtract_subject',
                                $this->instance->get_display_name_upper($log_points),
                                $this->instance->get_display_name_lower($log_points),
                                strip_tags($this->instance->points_format($log_points)),
                            ],
                            'message' => [
                                'newpoints_log_pm_subtract_message',
                                $this->instance->get_display_name_upper($log_points),
                                $this->instance->get_display_name_lower($log_points),
                                $user_data['username'],
                                strip_tags($this->instance->points_format($log_points)),
                            ],
                            'touid' => $user_id
                        ],
                        admin_override: true,
                        instance_id: $this->instance->instance_id
                    );
                }

                alert_send(
                    $user_id,
                    $this->log_id,
                    'core',
                    'subtract_points',
                    $this->instance->instance_id,
                );
                break;
            case LOGGING_TYPE_INCOME:
                if ($this->instance->notifications_private_message_enabled()) {
                    private_message_send(
                        [
                            'language' => $user_data['language'],
                            'subject' => [
                                'newpoints_log_pm_add_subject',
                                $this->instance->get_display_name_upper($log_points),
                                $this->instance->get_display_name_lower($log_points),
                                strip_tags($this->instance->points_format($log_points)),
                            ],
                            'message' => [
                                'newpoints_log_pm_add_message',
                                $this->instance->get_display_name_upper($log_points),
                                $this->instance->get_display_name_lower($log_points),
                                $user_data['username'],
                                strip_tags($this->instance->points_format($log_points)),
                            ],
                            'touid' => $user_id
                        ],
                        admin_override: true,
                        instance_id: $this->instance->instance_id
                    );
                }

                alert_send(
                    $user_id,
                    $this->log_id,
                    'core',
                    'add_points',
                    $this->instance->instance_id,
                );

                break;
        }

        return $this;
    }

    /**
     * Create a new income log entry
     *
     * @param string $log_action action taken
     * @param float $log_points
     * @param int $primary_id
     * @param int $secondary_id
     * @param int $tertiary_id
     * @return Logger false if something went wrong
     */
    public function log_income(
        string $log_action,
        float $log_points = 0,
        int $primary_id = 0,
        int $secondary_id = 0,
        int $tertiary_id = 0
    ): self {
        return $this->log_action(
            $log_action,
            $log_points,
            $primary_id,
            $secondary_id,
            $tertiary_id,
            LOGGING_TYPE_INCOME,
            user_id: $this->instance->get_user_id(),
        );
    }

    /**
     * Create a new charge log entry
     *
     * @param string $log_action action taken
     * @param float $log_points
     * @param int $primary_id
     * @param int $secondary_id
     * @param int $tertiary_id
     * @return Logger false if something went wrong
     */
    public function log_charge(
        string $log_action,
        float $log_points = 0,
        int $primary_id = 0,
        int $secondary_id = 0,
        int $tertiary_id = 0
    ): self {
        return $this->log_action(
            $log_action,
            $log_points,
            $primary_id,
            $secondary_id,
            $tertiary_id,
            LOGGING_TYPE_CHARGE,
            user_id: $this->instance->get_user_id(),
        );
    }

    public function get(int $log_id): array
    {
        global $db;

        $query = $db->simple_select(
            'newpoints_log',
            '*',
            "lid='{$log_id}' AND instance_id='{$this->instance->instance_id}'",
            ['limit' => 1]
        );

        if (!$db->num_rows($query)) {
            return [];
        }

        return (array)$db->fetch_array($query);
    }

    public function get_log_id(): int
    {
        return $this->log_id;
    }

    public function delete(int $log_id): void
    {
        global $db;

        $db->delete_query('newpoints_log', "lid='{$log_id}' AND instance_id='{$this->instance->instance_id}'");
    }
}