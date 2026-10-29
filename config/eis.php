<?php

return [

    /*
    |--------------------------------------------------------------------------
    | EIS Base URL
    |--------------------------------------------------------------------------
    */
    'base_url' => env('EIS_BASE_URL', 'https://eis-api.mra.mw'),

    'system_user_id' => env('EIS_SYSTEM_USER_ID', 1),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */
    'terminal_id' => env('EIS_TERMINAL_ID'),

    /*
    |--------------------------------------------------------------------------
    | API Version
    |--------------------------------------------------------------------------
    */
    'version' => 'v1',

    /*
    |--------------------------------------------------------------------------
    | Request Settings
    |--------------------------------------------------------------------------
    */
    'timeout' => env('EIS_TIMEOUT', 30),

    // Health probes allow up to 20 seconds for connection and total request time.
    'health_connect_timeout' => env('EIS_HEALTH_CONNECT_TIMEOUT', 20),
    'health_timeout' => env('EIS_HEALTH_TIMEOUT', 20),

    'retry' => [
        'times' => 3,
        'sleep' => 1000,
    ],

    /*
    |--------------------------------------------------------------------------
    | EIS Activation Settings
    |--------------------------------------------------------------------------
    */
    'activation' => [
        'generate_code' => env('EIS_GENERATE_ACTIVATION_CODE', true),
        'code_prefix' => env('EIS_ACTIVATION_CODE_PREFIX', 'TAC-'),
    ],

    'productId' => env('EIS_PRODUCT_ID', 1),
];