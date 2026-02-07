<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/alert_formatters/core_subtract_points_formatter.php)
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

namespace NewPoints\MyAlerts\Formatters;

use Exception;
use MybbStuff_MyAlerts_Entity_Alert;
use MybbStuff_MyAlerts_Formatter_AbstractFormatter;

use function NewPoints\Core\instance_object;
use function NewPoints\Core\language_load;
use function NewPoints\Core\log_error;
use function NewPoints\Core\main_file_name;

class newpoints_core_subtract_points_formatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    public function init(): bool
    {
        return language_load();
    }

    /**
     * Format an alert into it's output string to be used in both the main alerts listing page and the popup.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to format.
     *
     * @return string The formatted alert string.
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert): string
    {
        try {
            $instance = instance_object((int)($alert->getExtraDetails()['instance_id'] ?? 0));
        } catch (Exception $e) {
            log_error(
                (int)($alert->getExtraDetails()['instance_id'] ?? 0),
                $e->getMessage(),
            );

            return '';
        }

        if (!$instance->is_enabled()) {
            return '';
        }

        $details = $alert->toArray();

        $log_id = (int)$details['object_id'];

        $log_data = $instance->logger->get($log_id);

        $points = (float)$log_data['points'];

        return $this->lang->sprintf(
            $this->lang->newpoints_alert_text_core_subtract_points,
            $instance->get_display_name_upper($points),
            $instance->get_display_name_lower($points),
            $outputAlert['username'],
            $instance->points_format($points)
        );
    }

    /**
     * Build a link to an alert's content so that the system can redirect to it.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert The alert to build the link for.
     *
     * @return string The built alert, preferably an absolute link.
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert): string
    {
        global $settings;

        try {
            $instance = instance_object((int)($alert->getExtraDetails()['instance_id'] ?? 0));

            if ($instance->is_enabled()) {
                return $settings['bburl'] . '/' . main_file_name();
            }
        } catch (Exception $e) {
            log_error(
                (int)($alert->getExtraDetails()['instance_id'] ?? 0),
                $e->getMessage(),
            );
        }

        return $settings['bburl'];
    }
}