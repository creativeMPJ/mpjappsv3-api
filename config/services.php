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

    // Token statis untuk integrasi admin event (api-event/v1).
    // Kosongkan kalau tidak dipakai — akses tetap bisa lewat JWT admin.
    'event_api_token' => env('EVENT_API_TOKEN', ''),

    /*
     * Konsumen API integrasi server-to-server (prefix /api/external).
     *
     * EXTERNAL_API_TOKENS diisi berpasangan "nama:token", dipisah koma:
     *   EXTERNAL_API_TOKENS="mpj-fest:abc123,aplikasi-lain:def456"
     *
     * Satu konsumen satu token supaya bisa dicabut sendiri-sendiri. Kalau
     * kosong, seluruh endpoint /api/external ditutup, bukan dibuka bebas.
     */
    'external_api' => [
        'tokens' => collect(explode(',', (string) env('EXTERNAL_API_TOKENS', '')))
            ->map(fn ($pair) => trim($pair))
            ->filter()
            ->mapWithKeys(function ($pair) {
                [$name, $token] = array_pad(explode(':', $pair, 2), 2, null);
                $name  = trim((string) $name);
                $token = trim((string) $token);

                return $name !== '' && $token !== '' ? [$name => $token] : [];
            })
            ->all(),
    ],

];
