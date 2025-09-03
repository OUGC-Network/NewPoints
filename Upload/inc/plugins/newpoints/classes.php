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

use const Newpoints\DECIMAL_DATA_TYPE_SIZE;
use const Newpoints\DECIMAL_DATA_TYPE_STEP;

const URL = 'newpoints.php';

const RULE_TYPE_FORUM = 'forum';

const RULE_TYPE_GROUP = 'group';

const TASK_ENABLE = 1;

const TASK_DEACTIVATE = 0;

const TASK_DELETE = -1;

const FORM_TYPE_CHECK_BOX = 'check_box';

const FORM_TYPE_CHECK_BOX_LEGACY = 'checkBox';

const FORM_TYPE_NUMERIC_FIELD = 'numeric_field';

const FORM_TYPE_NUMERIC_FIELD_LEGACY = 'numericField';

const FORM_TYPE_SELECT_FIELD = 'select_field';

const FORM_TYPE_SELECT_FIELD_LEGACY = 'selectField';

const FORM_TYPE_PHP_CODE = 'php_function';

const FORM_TYPE_PHP_CODE_LEGACY = 'phpFunction';

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

const FORUM_PERMISSIONS = [
    Permissions::CanGetPoints => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 1,
        'form_type' => FORM_TYPE_CHECK_BOX
    ]
];

const GROUP_PERMISSIONS = [
    Permissions::CanSeePage => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 1,
        'form_type' => FORM_TYPE_CHECK_BOX
    ],
    Permissions::CanSeeStats => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 1,
        'form_type' => FORM_TYPE_CHECK_BOX
    ],
    Permissions::CanDonate => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX
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
        ]
    ],
    IncomeRates::RateSubtraction => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 100,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'max' => 100,
        ]
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
        ]
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
        ]
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
        ]
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
        ]
    ],
    IncomePermissions::UserIncomePostMinimumCharacters => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
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
        ]
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
        ]
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
        ]
    ],
    IncomePermissions::UserIncomeVisitMinutes => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
        'form_options' => [
            //'min' => 0,
            'step' => DECIMAL_DATA_TYPE_STEP,
        ]
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
        ]
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
        ]
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
        ]
    ],
    IncomePermissions::UserIncomeUserAllowanceMinutes => [
        'type' => 'DECIMAL',
        'unsigned' => true,
        'size' => DECIMAL_DATA_TYPE_SIZE,
        'default' => 0,
        'form_type' => FORM_TYPE_NUMERIC_FIELD,
    ],
    IncomePermissions::UserIncomeUserAllowancePrimaryOnly => [
        'type' => 'TINYINT',
        'unsigned' => true,
        'default' => 0,
        'form_type' => FORM_TYPE_CHECK_BOX
    ],
    IncomePermissions::UserIncomeUserAllowanceLastStamp => [
        'type' => 'INT',
        'unsigned' => true,
        'default' => 0
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
        ]
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
        ]
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
        ]
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
    'newpoints_instances' => array_merge([
        'instance_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'display_name_singular' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
        'display_name_plural' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
        'enable_notifications_private_message' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'enable_notifications_alert' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'users_column_name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'unique' => true,
        ],
        'unique_key' => [
            'users_column_name' => 'users_column_name',
        ],
    ], GROUP_PERMISSIONS),
]);

define('Newpoints\Core\FIELDS_DATA', [
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
    'usergroups' => array_merge(FORUM_PERMISSIONS, GROUP_PERMISSIONS),
    'forumpermissions' => FORUM_PERMISSIONS,
    'forums' => [
        'newpoints_rate' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => DECIMAL_DATA_TYPE_SIZE,
            'default' => 1,
            'form_type' => FORM_TYPE_NUMERIC_FIELD,
            'form_options' => [
                //'min' => 0,
                'step' => DECIMAL_DATA_TYPE_STEP,
            ]
        ],
        'newpoints_view_lock_points' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => DECIMAL_DATA_TYPE_SIZE,
            'default' => 0,
            'form_type' => FORM_TYPE_NUMERIC_FIELD,
            'form_options' => [
                //'min' => 0,
                'step' => DECIMAL_DATA_TYPE_STEP,
            ]
        ],
        'newpoints_post_lock_points' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => DECIMAL_DATA_TYPE_SIZE,
            'default' => 0,
            'form_type' => FORM_TYPE_NUMERIC_FIELD,
            'form_options' => [
                //'min' => 0,
                'step' => DECIMAL_DATA_TYPE_STEP,
            ]
        ],
    ],
]);