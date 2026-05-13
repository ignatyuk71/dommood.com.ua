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

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'nova_poshta' => [
        'api_key' => env('NOVA_POSHTA_API_KEY'),
        'api_url' => env('NOVA_POSHTA_API_URL', 'https://api.novaposhta.ua/v2.0/json/'),
        'sender_city_ref' => env('NOVA_POSHTA_SENDER_CITY_REF'),
        'sender_warehouse_ref' => env('NOVA_POSHTA_SENDER_WAREHOUSE_REF'),
        'default_weight' => env('NOVA_POSHTA_DEFAULT_WEIGHT', 1),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
