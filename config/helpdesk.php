<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Timezone used for SLA calculations
    |--------------------------------------------------------------------------
    |
    | All SLA math is performed in this timezone regardless of the user's
    | locale. Leave as null to fall back to the app timezone.
    |
    */

    'timezone' => env('HELPDESK_TIMEZONE', null),

    /*
    |--------------------------------------------------------------------------
    | Default business hours
    |--------------------------------------------------------------------------
    |
    | Used when a department has no entries in hd_business_hours. Keys are
    | ISO weekdays (1=Mon..7=Sun). Each entry has start and end times as
    | H:i strings. Multiple ranges per day are allowed (e.g. lunch break).
    |
    */

    'business_hours' => [
        'default' => [
            1 => [['08:00', '12:00'], ['13:00', '18:00']],
            2 => [['08:00', '12:00'], ['13:00', '18:00']],
            3 => [['08:00', '12:00'], ['13:00', '18:00']],
            4 => [['08:00', '12:00'], ['13:00', '18:00']],
            5 => [['08:00', '12:00'], ['13:00', '18:00']],
            6 => [],
            7 => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SLA calculation mode
    |--------------------------------------------------------------------------
    |
    | 'business'  — Honor hd_business_hours + hd_holidays. SLA minutes are
    |               only consumed during business windows.
    | 'calendar'  — Legacy behavior: plain wall-clock hours. Kept as a
    |               fallback; not recommended for production.
    |
    */

    'sla_mode' => env('HELPDESK_SLA_MODE', 'business'),

    /*
    |--------------------------------------------------------------------------
    | AI classifier (Phase 4 — intentionally unused for now)
    |--------------------------------------------------------------------------
    */

    'ai' => [
        'classifier' => env('HELPDESK_AI_CLASSIFIER', 'null'),
        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
        ],
    ],

];
