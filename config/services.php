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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'phone' => [
        'username' => env('PHONE_USERNAME'),
        'password' => env('PHONE_PASSWORD'),
    ],

    'newebpay' => [
        'NEWBPAY'       => env('NEWBPAY'),
        'MerchantID'    => env('MerchantID'),
        'HashKey'       => env('HashKey'),
        'HashIV'        => env('HashIV'),
        'NEWBPAYSTATUS' => env('NEWBPAYSTATUS')
    ],

    'facebook' => [
        'app_id'                => env('FB_APP_ID'),
        'app_secret'            => env('FB_APP_SECRET'),
        'default_graph_version' => env('FB_APP_VERSION'),
    ],

    'line' => [
        'channel_id'     => env('Line_CHANNEL_ID'),
        'callback_url'   => env('LINE_CALLBACK_URL'),
        'channel_secret' => env('LINE_SECRET'),
    ],

];
