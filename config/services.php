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

    // 'auth_api' => [
    //     'url' => env('AUTH_API_URL', 'http://10.101.0.85/newhris_api/api'),
    //     'timeout' => env('AUTH_API_TIMEOUT', 30),
    //     'retry_attempts' => env('AUTH_API_RETRY', 3),
    // ],

    'eci_hris' => [
        'base_url' => env('ECI_HRIS_API_URL', 'http://10.101.0.85/newhris_api'),
        'api_key' => env('ECI_HRIS_API_KEY', ''),
        'timeout' => env('ECI_HRIS_TIMEOUT', 30),
        'login_endpoint' => env('ECI_HRIS_LOGIN_ENDPOINT', '/api/login2.php'), // Sesuaikan dengan endpoint yang benar
    ],

    'auth_api' => [
        'url' => env('AUTH_API_URL', 'http://10.101.0.85/newhris_api'),
    ],

];
