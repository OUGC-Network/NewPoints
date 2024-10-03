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
            'size' => '16,2',
            'default' => 0
        ],
        'pointspost' => [
            'type' => 'DECIMAL',
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
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'usergroups' => [
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
        'newpoints_rate' => [
            'type' => 'DECIMAL',
            'size' => '16,2',
            'default' => 1,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_allowance' => [
            'type' => 'DECIMAL',
            'size' => '16,2',
            'default' => 0,
            'formType' => 'numericField',
            'formOptions' => [
                //'min' => 0,
                'step' => 0.01,
            ]
        ],
        'newpoints_allowance_period' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'numericField'
        ],
        'newpoints_allowance_primary_only' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formType' => 'checkBox'
        ],
        'newpoints_allowance_last_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'forums' => [
        'newpoints_rate' => [
            'type' => 'DECIMAL',
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

const FORM_TYPE_CHECK_BOX = 'checkBox';

const FORM_TYPE_NUMERIC_FIELD = 'numericField';

const POST_VISIBLE_STATUS_DRAFT = -2;

const POST_VISIBLE_STATUS_SOFT_DELETED = -1;

const POST_VISIBLE_STATUS_UNAPPROVED = 0;

const POST_VISIBLE_STATUS_VISIBLE = 1;

const INCOME_TYPE_POST_NEW = 'newpost';

const INCOME_TYPE_POST_MINIMUM_CHARACTERS = 'minchar';

const INCOME_TYPE_POST_PER_CHARACTER = 'perchar';

const INCOME_TYPE_POST_PER_REPLY = 'perreply';

const INCOME_TYPE_THREAD_NEW = 'newthread';

const INCOME_TYPE_PAGE_VIEW = 'pageview';

const INCOME_TYPE_VISIT = 'visit';

const INCOME_TYPE_VISIT_MINUTES = 'visit_minutes';

const INCOME_TYPE_POLL_NEW = 'newpoll';

const INCOME_TYPE_POLL_VOTE = 'pervote';

const INCOME_TYPE_USER_REGISTRATION = 'newreg';

const INCOME_TYPE_USER_REFERRAL = 'referral';

const INCOME_TYPE_PRIVATE_MESSAGE_NEW = 'pmsent';

const INCOME_TYPE_PRIVATE_THREAD_RATE_NEW = 'perrate';