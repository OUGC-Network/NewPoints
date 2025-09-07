<?php

/***************************************************************************
 *
 *    NewPoints plugin (/inc/plugins/newpoints/classes.php)
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

namespace Newpoints\Core;

use Exception;

use InvalidArgumentException;

use const Newpoints\DECIMAL_DATA_TYPE_SIZE;
use const Newpoints\DECIMAL_DATA_TYPE_STEP;

const URL = 'newpoints.php';

const RULE_TYPE_FORUM = 'forum';

const RULE_TYPE_GROUP = 'group';

const TASK_ENABLE = 1;

const TASK_DEACTIVATE = 0;

const TASK_DELETE = -1;

const FORM_TYPE_TEXT_FIELD = 'text_field';

const FORM_TYPE_CHECK_BOX = 'check_box';

const FORM_TYPE_CHECK_BOX_LEGACY = 'checkBox';

const FORM_TYPE_NUMERIC_FIELD = 'numeric_field';

const FORM_TYPE_NUMERIC_FIELD_LEGACY = 'numericField';

const FORM_TYPE_SELECT_FIELD_LEGACY = 'selectField';

const FORM_TYPE_PHP_CODE = 'php_function';

const FORM_TYPE_PHP_CODE_LEGACY = 'phpFunction';

const FORM_TYPE_SELECT_FIELD = 'select_field';

const FORM_TYPE_YES_NO_FIELD = 'yes_no_field';

const POST_VISIBLE_STATUS_DRAFT = -2;

const POST_VISIBLE_STATUS_SOFT_DELETED = -1;

const POST_VISIBLE_STATUS_UNAPPROVED = 0;

const POST_VISIBLE_STATUS_VISIBLE = 1;

const INCOME_TYPES = [
    'thread' => [],
    'thread_reply' => [],
    'thread_rate' => [],
    'post' => ['post_minimum_characters' => 'numeric'],
    'post_character' => [],
    'page_view' => [],
    'visit' => ['visit_minutes' => 'numeric'],
    'poll' => [],
    'poll_vote' => [],
    'user_allowance' => [],
    'user_registration' => [],
    'user_referral' => [],
    'private_message' => [],
];

const INCOME_TYPE_THREAD = 'thread';

const INCOME_TYPE_THREAD_REPLY = 'thread_reply';

const INCOME_TYPE_THREAD_RATE = 'thread_rate';

const INCOME_TYPE_POST = 'post';

const INCOME_TYPE_POST_CHARACTER = 'post_character';

const INCOME_TYPE_PAGE_VIEW = 'page_view';

const INCOME_TYPE_VISIT = 'visit';

const INCOME_TYPE_POLL = 'poll';

const INCOME_TYPE_POLL_VOTE = 'poll_vote';

const INCOME_TYPE_USER_ALLOWANCE = 'user_allowance';

const INCOME_TYPE_USER_REGISTRATION = 'user_registration';

const INCOME_TYPE_USER_REFERRAL = 'user_referral';

const INCOME_TYPE_PRIVATE_MESSAGE = 'private_message';

const LOGGING_TYPE_INCOME = 1;

const LOGGING_TYPE_CHARGE = 2;

const PRIVATE_MESSAGE_ENGINE_ID = 0;

const PRIVATE_MESSAGE_CURRENT_USER_ID = 0;

const INSTANCE_DEFAULT_ID = 1;

const ALL_UNLIMITED_VALUE = -1;

const GUEST_GROUP_ID = 1;

const FORUM_PERMISSIONS = [
    Permissions::CanGetPoints => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX,
        'is_permission' => true,
        'dragging_permission' => true,
        'form_category' => 'general',
    ],
    Permissions::Rate => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 1,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'is_permission' => true,
        'form_category' => 'rates',
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ]
    ],
    Permissions::ViewLockCost => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'is_permission' => true,
        'form_category' => 'rates',
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ]
    ],
    Permissions::PostLockCost => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'is_permission' => true,
        'form_category' => 'rates',
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ]
    ],
];

const GROUP_PERMISSIONS = [
    Permissions::CanGetPoints => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX,
        'is_permission' => true,
        'dragging_permission' => true,
        'form_category' => 'general',
    ],
    Permissions::CanSeePage => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX,
        'is_permission' => true,
        'dragging_permission' => true,
        'form_category' => 'general',
    ],
    Permissions::CanSeeStats => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX,
        'is_permission' => true,
        'dragging_permission' => true,
        'form_category' => 'general',
    ],
    Permissions::CanDonate => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX,
        'is_permission' => true,
        'dragging_permission' => true,
        'form_category' => 'general',
    ],
    IncomeRates::RateAddition => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 1,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'rates',
    ],
    IncomeRates::RateSubtraction => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 100,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'max' => 100,
        ],
        'is_permission' => true,
        'form_category' => 'rates',
    ],
    IncomePermissions::UserIncomeThread => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeThreadReply => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeThreadRate => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomePost => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomePostMinimumCharacters => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,

        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomePostCharacter => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomePageView => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeVisit => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeVisitMinutes => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomePoll => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomePollVote => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeUserAllowance => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeUserAllowanceMinutes => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,

        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeUserAllowancePrimaryOnly => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX,
        'is_permission' => true,
        'form_category' => 'income',
    ],
    // todo, how does this work with group permissions?
    IncomePermissions::UserIncomeUserAllowanceLastStamp => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 0,
    ],
    IncomePermissions::UserIncomeUserRegistration => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomeUserReferral => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ],
    IncomePermissions::UserIncomePrivateMessage => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ],
        'is_permission' => true,
        'form_category' => 'income',
    ]
];

define('Newpoints\Core\TABLES_DATA', [
    'newpoints_settings' => [
        'sid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'plugin' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 100,
            'default' => ''
        ],
        'title' => [
            'type' => 'VARCHAR',
            'size' => 100,
            'default' => ''
        ],
        'description' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'type' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'value' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'disporder' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'instance_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => INSTANCE_DEFAULT_ID
        ],
    ],
    'newpoints_log' => [
        'lid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'action' => [
            'type' => 'VARCHAR',
            'size' => 100,
            'default' => ''
        ],
        'data' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'date' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'username' => [
            'type' => 'VARCHAR',
            'size' => 100,
            'default' => ''
        ],
        'points' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => DECIMAL_DATA_TYPE_SIZE,
            'default' => 0
        ],
        'log_primary_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'log_secondary_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'log_tertiary_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'log_type' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'instance_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => INSTANCE_DEFAULT_ID
        ],
    ],
    'newpoints_error_log' => [
        'log_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'instance_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => INSTANCE_DEFAULT_ID
        ],
        'error_message' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'post_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'thread_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'forum_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'income_type' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'log_primary_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'log_secondary_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'log_tertiary_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'newpoints_forumrules' => [
        'rid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'fid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 100,
            'default' => ''
        ],
        'description' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'rate' => [
            'type' => 'FLOAT',
            'default' => 1
        ],
        /*'pointsview' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => \Newpoints\DECIMAL_DATA_TYPE_SIZE,
            'default' => 0
        ],
        'pointspost' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => \Newpoints\DECIMAL_DATA_TYPE_SIZE,
            'default' => 0
        ],*/
    ],
    'newpoints_grouprules' => [
        'rid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 100,
            'default' => ''
        ],
        'description' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'rate' => [
            'type' => 'FLOAT',
            'default' => 1
        ],
        /*'pointsearn' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => \Newpoints\DECIMAL_DATA_TYPE_SIZE,
            'default' => 0
        ],
        'period' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],*/
        'lastpay' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'newpoints_instances' => [
        'instance_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'currency_name_singular' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'form_type' => FORM_TYPE_TEXT_FIELD,
            'form_category' => 'main',
        ],
        'currency_name_plural' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'form_type' => FORM_TYPE_TEXT_FIELD,
            'form_category' => 'main',
        ],
        'enable_notifications_private_message' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'form_type' => FORM_TYPE_YES_NO_FIELD,
            'form_category' => 'main',
        ],
        'enable_notifications_alert' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'form_type' => FORM_TYPE_YES_NO_FIELD,
            'form_category' => 'main',
        ],
        'script_name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'form_category' => 'main',
            'form_type' => FORM_TYPE_TEXT_FIELD,
        ],
        'users_column_name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'unique' => true,
            'form_type' => FORM_TYPE_TEXT_FIELD,
            'form_category' => 'main',
            'is_disabled' => function (int $instance_id): bool {
                if ($instance_id === INSTANCE_DEFAULT_ID) {
                    return true;
                }

                try {
                    return instance_object($instance_id)->users_column_exists();
                } catch (Exception $e) {
                    \Newpoints\Core\log_error(
                        $instance_id,
                        $e->getMessage(),
                    );

                    return true;
                }
            }
        ],
        'is_enabled' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'form_type' => FORM_TYPE_YES_NO_FIELD,
            'form_category' => 'main',
        ],
        'display_order' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'form_type' => FORM_TYPE_NUMERIC_FIELD,
            'form_category' => 'main',
        ],
        'unique_key' => [
            'users_column_name' => 'users_column_name',
        ],
    ],
    'newpoints_group_permissions' => array_merge([
        'permission_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'instance_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'group_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ]
    ], GROUP_PERMISSIONS),
    'newpoints_forum_permissions' => array_merge([
        'permission_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'instance_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'forum_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ]
    ], FORUM_PERMISSIONS),
]);

const FIELDS_DATA = [
    'users' => [
        'newpoints' => [
            'type' => 'DECIMAL',
            'size' => DECIMAL_DATA_TYPE_SIZE,
            'default' => 0,
            'form_type' => FORM_TYPE_NUMERIC_FIELD,
            'form_options' => [
                'min' => '',
                'step' => DECIMAL_DATA_TYPE_STEP,
            ]
        ],
    ],
    'usergroups' => GROUP_PERMISSIONS,
    'forumpermissions' => FORUM_PERMISSIONS,
    'forums' => FORUM_PERMISSIONS,
];