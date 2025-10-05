<?php
/**
 * Auth component capabilities definition
 *
 * Each capability key must be formatted component:capability_type
 * Example: auth:add (component 'auth', action 'add')
 */

$capabilities = [
    'auth:add' => [
        'captype' => 'write',
    ],
    'auth:view' => [
        'captype' => 'read',
    ],
];

