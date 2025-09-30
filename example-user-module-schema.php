<?php

/**
 * Example User Module Database Schema
 * This file defines the database tables for the user module
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
            'password_hash' => [
                'type' => 'varchar',
                'length' => 255,
                'null' => false,
                'comment' => 'Hashed password'
            ],
            'first_name' => [
                'type' => 'varchar',
                'length' => 100,
                'null' => true,
                'comment' => 'User first name'
            ],
            'last_name' => [
                'type' => 'varchar',
                'length' => 100,
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
            'email_verified' => [
                'type' => 'boolean',
                'default' => false,
                'null' => false,
                'comment' => 'Whether email is verified'
            ],
            'last_login' => [
                'type' => 'timestamp',
                'null' => true,
                'comment' => 'Unix timestamp of last login'
            ],
            'timecreated' => [
                'type' => 'timestamp',
                'null' => false,
                'comment' => 'Unix timestamp when user was created'
            ],
            'timemodified' => [
                'type' => 'timestamp',
                'null' => false,
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
            'user_id' => [
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
            'avatar_url' => [
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
                'type' => 'json',
                'null' => true,
                'comment' => 'User preferences as JSON'
            ],
            'timecreated' => [
                'type' => 'timestamp',
                'null' => false
            ],
            'timemodified' => [
                'type' => 'timestamp',
                'null' => false
            ]
        ],
        'indexes' => [
            'user_id' => 'user_id',
            'timezone' => 'timezone',
            'language' => 'language'
        ],
        'unique' => [
            'user_id' => 'user_id'
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
            'user_id' => [
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
                'type' => 'timestamp',
                'null' => false,
                'comment' => 'Unix timestamp when session expires'
            ],
            'timecreated' => [
                'type' => 'timestamp',
                'null' => false
            ],
            'timemodified' => [
                'type' => 'timestamp',
                'null' => false
            ]
        ],
        'indexes' => [
            'user_id' => 'user_id',
            'expires_at' => 'expires_at',
            'ip_address' => 'ip_address'
        ]
    ]
];
