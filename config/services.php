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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'oto' => [
        'url' => env('OTO_API_URL', 'https://api.tryoto.com/rest/v2'),
        'refresh_token' => env('OTO_REFRESH_TOKEN', 'AMf-vBwsG7J61J_1EkBNW_wnKdQc4Xyalpz59J1QittknHfsekYzdv-1sDxoeD1oaw5_OBxmnVtjkwzm7nAUsfkEZoZpmMQtAINMhJLIWxAiJ1xnX9IY4ksBrIGoiGFG1ULhV8nT-a7ucNxD28bjK-cf6bOPEVWYpVDdQToxKpvgEXp2yQTujA3HT5XMIo_x31f1k6I41WA3pdKzsrwSCU_NQSijp1oBxQ'),
        'access_token' => env('OTO_ACCESS_TOKEN'),
    ],

    // Where the admin dashboard's browser reaches the websocket server, when that differs from
    // REVERB_* (which is how this server reaches it). Leave unset to use REVERB_*.
    'dashboard_live' => [
        'host' => env('DASHBOARD_REVERB_HOST'),
        'port' => env('DASHBOARD_REVERB_PORT'),
        'scheme' => env('DASHBOARD_REVERB_SCHEME'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID', 'car-mediator-platform'),
        'credentials_path' => storage_path('app/json/firebase/car-mediator-platform-firebase-adminsdk-fbsvc-1d8876fe49.json'),
        'timeout' => env('FCM_TIMEOUT', 10),
        'ttl' => env('FCM_TTL', 3600),
        // Data-only Android messages (no top-level `notification` block). Off by
        // default so installed clients keep displaying pushes in background; flip
        // once app_version adoption of the new client is high. See notification
        // upgrade plan, Phase 6.
        'android_data_only' => env('NOTIFICATIONS_ANDROID_DATA_ONLY', false),
        'queue' => env('NOTIFICATIONS_QUEUE', 'notifications'),
        // Escape hatch: dispatch push jobs synchronously if no worker is confirmed running yet.
        'queue_sync' => env('NOTIFICATIONS_QUEUE_SYNC', false),
        'max_send_attempts' => 3,
        'retry_delays' => [10, 60, 300],
    ],

];
