<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'eis' => [
        'base_url' => env('EIS_BASE_URL', 'http://dev-eis-api.mra.mw/api/v1'),
        'product_id' => env('EIS_PRODUCT_ID', 'Mbira ERP'),
    ],

    'onekhusa' => [
        'merchant_account_number' => env('ONEKHUSA_MERCHANT_ACCOUNT_NUMBER'),
        'api_key' => env('ONEKHUSA_API_KEY'),
        'api_secret' => env('ONEKHUSA_API_SECRET'),
        'organisation_id' => env('ONEKHUSA_ORGANISATION_ID'),
        'base_url' => env('ONEKHUSA_BASE_URL', 'https://api.onekhusa.com/sandbox/v1'),
    ],

];
