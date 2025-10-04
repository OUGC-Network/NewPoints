<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/system/Url.php)
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

use function NewPoints\Core\main_file_name;

class Url
{
    private string $url;

    public function __construct(?string $url = null)
    {
        if ($url === null) {
            $this->url = main_file_name();
        } else {
            $this->url = $url;
        }
    }

    public function set_url(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function get_url(): string
    {
        return $this->url;
    }

    public function build(array $url_parameters = []): string
    {
        if (($offset = strpos($this->get_url(), '#')) === false) {
            $offset = strlen($this->get_url());
        }

        return substr_replace(
            $this->get_url(),
            (str_contains($this->get_url(), '?') ? $separator = '&amp;' : '?') .
            http_build_query($url_parameters, '', '&amp;'),
            $offset,
            0
        );
    }

    public function build_absolute(array $url_params = []): string
    {
        global $mybb;

        return $mybb->settings['bburl'] . '/' . $this->build($url_params);
    }

}