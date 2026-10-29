<?php

return [
    'name' => 'Connector',
    'module_version' => '2.0',
    'pid' => 9,
    // Null follows Passport's configured prefix; override for a custom token route.
    'audit_oauth_token_path' => null,
    // Temporarily enable only during a reviewed system-superadmin deployment.
    'allow_split_migration_route' => env('CONNECTOR_ALLOW_SPLIT_MIGRATION_ROUTE', false),
];
