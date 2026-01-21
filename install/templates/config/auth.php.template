<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Authentication Configuration File
|--------------------------------------------------------------------------
| This file contains authentication-related settings for the application,
| including default guard, password reset options, and user providers.
*/

return [
    'user_model' => null, // Your custom user model if any
    'table' => 'users',
    'primary_key' => 'id',
    'email_column' => 'email',
    'password_column' => 'password',
    'remember_token_column' => null,
    'last_login_column' => 'last_login_at',

    /*
    |--------------------------------------------------------------------------
    | Password Hashing
    |--------------------------------------------------------------------------
    */
    'password_algo' => PASSWORD_BCRYPT,
    'password_cost' => 12,

    /*
    |--------------------------------------------------------------------------
    | Session Configuration
    |--------------------------------------------------------------------------
    */
    'session_key' => 'auth_user_id',
    'remember_token_name' => 'remember_token',
    'remember_days' => 30,

    /*
    |--------------------------------------------------------------------------
    | OAuth Providers
    |--------------------------------------------------------------------------
    | 
    | Configure your OAuth providers here. You'll need to create apps
    | with each provider and get your client credentials.
    |
    */
    'oauth' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            // Get credentials from: https://console.cloud.google.com
        ],

        'facebook' => [
            'client_id' => env('FACEBOOK_CLIENT_ID', ''),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET', ''),
            // Get credentials from: https://developers.facebook.com
        ],

        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID', ''),
            'client_secret' => env('GITHUB_CLIENT_SECRET', ''),
            // Get credentials from: https://github.com/settings/developers
        ],

        'discord' => [
            'client_id' => env('DISCORD_CLIENT_ID', ''),
            'client_secret' => env('DISCORD_CLIENT_SECRET', ''),
            // Get credentials from: https://discord.com/developers/applications
        ],
    ],

    'oauth_table' => 'oauth_accounts',
    'remember_tokens_table' => 'remember_tokens',

    'use_timestamps' => true,
    'created_at_column' => 'created_at',
    'updated_at_column' => 'updated_at',
    'email_verification' => [
        'enabled' => false,
        'token_length' => 6,
        'expiry_hours' => 24,
        'send_welcome_email' => true,
    ],
];
