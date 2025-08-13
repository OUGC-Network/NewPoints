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

use function Newpoints\Core\instance_get;

class Core
{
    private array $instance_data = [];

    public int $instance_id;

    public Logger $logger;

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
}