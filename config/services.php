<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'expo' => [
        'push_url' => env('EXPO_PUSH_URL', 'https://exp.host/--/api/v2/push/send'),
        // Only needed when "Enhanced push security" is enabled on the Expo project.
        'access_token' => env('EXPO_ACCESS_TOKEN'),
        // "deferred" needs no queue worker; use "database" once one runs.
        'queue_connection' => env('EXPO_PUSH_QUEUE_CONNECTION', 'deferred'),
    ],

    // Latest Android build on the Play Store, announced to older apps. Native
    // changes cannot reach them over the air: they must update from the store.
    'play_store' => [
        'url' => env('PLAY_STORE_URL', 'https://play.google.com/store/apps/details?id=com.sedrge.LoveGames'),
        'latest_version' => env('ANDROID_LATEST_VERSION'),
        // Older apps are blocked until they update (e.g. a breaking API change)
        'min_version' => env('ANDROID_MIN_VERSION'),
    ],

];
