<?php
/**
 * Auth component capabilities definition
 *
 * Each capability key must be formatted component:capability_type
 * Example: auth:add (component 'auth', action 'add')
 */

$capabilities = [
    'core:authenticated' => [
        'captype' => 'read',
    ],
    'core:viewdashboard' => [
        'captype' => 'read',
    ],
];

