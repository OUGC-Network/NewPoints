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
 *    NewPoints plugin for MyBB - A complex but efficient points system for MyBB.
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

const TABLES_DATA = [
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
            'size' => '16,2',
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
            'size' => '16,2',
            'default' => 0
        ],
        'pointspost' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
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
            'size' => '16,2',
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
    ]
];

const FIELDS_DATA = [
    'users' => [
        'newpoints' => [
            'type' => 'DECIMAL',
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                'min' => '',
                'step' => 0.01,
            ]
        ],
    ],
    'usergroups' => [
        'newpoints_can_get_points' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'checkBox'
        ],
        'newpoints_can_see_page' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'checkBox'
        ],
        'newpoints_can_see_stats' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'checkBox'
        ],
        'newpoints_can_donate' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'checkBox'
        ],
        'newpoints_rate_addition' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 1,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_rate_subtraction' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 100,
            'formType' => 'numericField',
            'formOptions' => [
                //'max' => 100,
            ]
        ],
        'newpoints_income_thread' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_thread_reply' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_thread_rate' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_post' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_post_minimum_characters' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'numericField',
        ],
        'newpoints_income_post_character' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_page_view' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_visit' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_visit_minutes' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_poll' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_poll_vote' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_user_allowance' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_user_allowance_minutes' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
        ],
        'newpoints_income_user_allowance_primary_only' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'checkBox'
        ],
        'newpoints_income_user_allowance_last_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'newpoints_income_user_registration' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_user_referral' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_income_private_message' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ]
    ],
    'forumpermissions' => [
        'newpoints_can_get_points' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1,
            'formType' => 'checkBox'
        ]
    ],
    'forums' => [
        'newpoints_rate' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 1,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_view_lock_points' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_post_lock_points' => [
            'type' => 'DECIMAL',
            'unsigned' => true,
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
    ],
];

const URL = 'newpoints.php';

const RULE_TYPE_FORUM = 'forum';

const RULE_TYPE_GROUP = 'group';

const TASK_ENABLE = 1;

const TASK_DEACTIVATE = 0;

const TASK_DELETE = -1;

const FORM_TYPE_CHECK_BOX = 'checkBox';

const FORM_TYPE_NUMERIC_FIELD = 'numericField';

const FORM_TYPE_SELECT_FIELD = 'selectField';

const FORM_TYPE_PHP_CODE = 'phpFunction';

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