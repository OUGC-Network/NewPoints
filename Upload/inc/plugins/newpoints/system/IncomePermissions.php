<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/system/IncomePermissions.php)
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

namespace NewPoints\Core;

class IncomePermissions
{
    public const UserIncomeThread = 'newpoints_income_thread';
    public const UserIncomeThreadReply = 'newpoints_income_thread_reply';
    public const UserIncomeThreadRate = 'newpoints_income_thread_rate';
    public const UserIncomePost = 'newpoints_income_post';
    public const UserIncomePostMinimumCharacters = 'newpoints_income_post_minimum_characters';
    public const UserIncomePostCharacter = 'newpoints_income_post_character';
    public const UserIncomePageView = 'newpoints_income_page_view';
    public const UserIncomeVisit = 'newpoints_income_visit';
    public const UserIncomeVisitMinutes = 'newpoints_income_visit_minutes';
    public const UserIncomePoll = 'newpoints_income_poll';
    public const UserIncomePollVote = 'newpoints_income_poll_vote';
    public const UserIncomeUserAllowance = 'newpoints_income_user_allowance';
    public const UserIncomeUserAllowanceMinutes = 'newpoints_income_user_allowance_minutes';
    public const UserIncomeUserAllowancePrimaryOnly = 'newpoints_income_user_allowance_primary_only';
    public const UserIncomeUserAllowanceLastStamp = 'newpoints_income_user_allowance_last_stamp';
    public const UserIncomeUserRegistration = 'newpoints_income_user_registration';
    public const UserIncomeUserReferral = 'newpoints_income_user_referral';
    public const UserIncomePrivateMessage = 'newpoints_income_private_message';
}