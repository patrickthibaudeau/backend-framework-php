<?php

/**
 * User Module Database Schema
 * This file defines the database tables for the core user module
 *
 * Return an array where keys are table names and values are table definitions
 */

return [
    // Users table
    'users' => [
        'fields' => [
            'id' => [
                'type' => 'int',
                'length' => 11,
                'auto_increment' => true,
                'primary' => true,
                'null' => false,
                'comment' => 'Unique user ID'
            ],
            'auth' => [
                'type' => 'varchar',
                'length' => 100,
                'null' => false,
                'comment' => 'Authentication type (e.g., manaul, ldap, oauth, saml2)'
            ],
            'username' => [
                'type' => 'varchar',
                'length' => 100,
                'null' => false,
                'comment' => 'Unique username'
            ],
            'email' => [
                'type' => 'varchar',
                'length' => 255,
                'null' => false,
                'comment' => 'User email address'
            ],
            'password' => [
                'type' => 'varchar',
                'length' => 255,
                'null' => false,
                'comment' => 'Hashed password'
            ],
            'firstname' => [
                'type' => 'varchar',
                'length' => 255,
                'null' => true,
                'comment' => 'User first name'
            ],
            'lastname' => [
                'type' => 'varchar',
                'length' => 255,
                'null' => true,
                'comment' => 'User last name'
            ],
            'status' => [
                'type' => 'varchar',
                'length' => 20,
                'default' => 'active',
                'null' => false,
                'comment' => 'User status: active, inactive, suspended'
            ],
            'emailverified' => [
                'type' => 'boolean',
                'default' => 0,
                'null' => false,
                'comment' => 'Whether email is verified'
            ],
            'lastlogin' => [
                'type' => 'int',
                'length' => 11,
                'null' => true,
                'default' => null,
                'comment' => 'Unix timestamp of last login'
            ],
            'timecreated' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'default' => 0,
                'comment' => 'Unix timestamp when user was created'
            ],
            'timemodified' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'default' => 0,
                'comment' => 'Unix timestamp when user was last modified'
            ]
        ],
        'indexes' => [
            'username' => 'username',
            'email' => 'email',
            'status' => 'status',
            'timecreated' => 'timecreated'
        ],
        'unique' => [
            'username' => 'username',
            'email' => 'email'
        ]
    ],

    // User profiles table
    'user_profiles' => [
        'fields' => [
            'id' => [
                'type' => 'int',
                'length' => 11,
                'auto_increment' => true,
                'primary' => true,
                'null' => false
            ],
            'userid' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'comment' => 'Foreign key to users.id'
            ],
            'bio' => [
                'type' => 'text',
                'null' => true,
                'comment' => 'User biography'
            ],
            'avatarurl' => [
                'type' => 'varchar',
                'length' => 500,
                'null' => true,
                'comment' => 'URL to user avatar image'
            ],
            'phone' => [
                'type' => 'varchar',
                'length' => 20,
                'null' => true,
                'comment' => 'User phone number'
            ],
            'mobile' => [
                'type' => 'varchar',
                'length' => 20,
                'null' => true,
                'comment' => 'User mobile phone number'
            ],
            'timezone' => [
                'type' => 'varchar',
                'length' => 50,
                'default' => 'UTC',
                'null' => false,
                'comment' => 'User timezone'
            ],
            'language' => [
                'type' => 'varchar',
                'length' => 10,
                'default' => 'en',
                'null' => false,
                'comment' => 'User preferred language'
            ],
            'preferences' => [
                'type' => 'longtext',
                'null' => true,
                'comment' => 'User preferences as JSON text'
            ],
            'timecreated' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'default' => 0
            ],
            'timemodified' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'default' => 0
            ]
        ],
        'indexes' => [
            'userid' => 'userid',
            'timezone' => 'timezone',
            'language' => 'language'
        ],
        'unique' => [
            'userid' => 'userid'
        ]
    ],

    // User sessions table
    'user_sessions' => [
        'fields' => [
            'id' => [
                'type' => 'varchar',
                'length' => 128,
                'primary' => true,
                'null' => false,
                'comment' => 'Session ID'
            ],
            'userid' => [
                'type' => 'int',
                'length' => 11,
                'null' => true,
                'comment' => 'User ID (null for anonymous sessions)'
            ],
            'ip_address' => [
                'type' => 'varchar',
                'length' => 45,
                'null' => true,
                'comment' => 'IP address (supports IPv6)'
            ],
            'user_agent' => [
                'type' => 'text',
                'null' => true,
                'comment' => 'User agent string'
            ],
            'data' => [
                'type' => 'longtext',
                'null' => true,
                'comment' => 'Serialized session data'
            ],
            'expires_at' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'default' => 0,
                'comment' => 'Unix timestamp when session expires'
            ],
            'timecreated' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'default' => 0
            ],
            'timemodified' => [
                'type' => 'int',
                'length' => 11,
                'null' => false,
                'default' => 0
            ]
        ],
        'indexes' => [
            'userid' => 'userid',
            'expires_at' => 'expires_at',
            'ip_address' => 'ip_address'
        ]
    ]
];
