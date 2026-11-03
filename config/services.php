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

    // Firebase service account (JSON key, path relative to the project root).
    // When set, Android pushes with buttons (messages, calls) go straight to
    // Firebase so the buttons show even when the app is closed.
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
    ],

    // Audio and video calls. New calls use the first available provider of
    // the list; "p2p" (direct WebRTC) is free and always available.
    'calls' => [
        'providers' => array_values(array_filter(array_map('trim', explode(',', (string) env('CALL_PROVIDERS', 'livekit,p2p'))))),
        'stun_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('CALL_STUN_URLS', 'stun:stun.l.google.com:19302,stun:stun1.l.google.com:19302'))))),
        // Relay for networks that block direct connections (optional)
        'turn_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('CALL_TURN_URLS', ''))))),
        'turn_username' => env('CALL_TURN_USERNAME'),
        'turn_credential' => env('CALL_TURN_CREDENTIAL'),
    ],

    'livekit' => [
        'url' => env('LIVEKIT_URL'),
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
        // Participant minutes of the plan, and the share used before new
        // calls switch to the next provider
        'monthly_minutes' => (int) env('LIVEKIT_MONTHLY_MINUTES', 5000),
        'usage_limit' => (float) env('LIVEKIT_USAGE_LIMIT', 0.9),
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
