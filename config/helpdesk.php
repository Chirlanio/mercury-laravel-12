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
    | AI classifier (Phase 4)
    |--------------------------------------------------------------------------
    |
    | 'null'  → NullClassifier, inert, default
    | 'groq'  → GroqClassifier, Llama 3.3 70b via Groq's OpenAI-compatible API
    |
    | The classifier only runs when the ticket's department has
    | ai_classification_enabled = true. Failures (missing key, rate limit,
    | bad response) never block ticket creation — they return empty.
    |
    */

    'ai' => [
        'classifier' => env('HELPDESK_AI_CLASSIFIER', 'null'),

        // Threshold below which the dashboard does NOT surface an AI
        // suggestion to the technician. The raw confidence is always
        // persisted for analysis regardless.
        'apply_threshold' => (float) env('HELPDESK_AI_APPLY_THRESHOLD', 0.7),

        'groq' => [
            'api_key' => env('GROQ_API_KEY'),
            'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'base_url' => env('GROQ_BASE_URL', 'https://api.groq.com/openai/v1'),
            'rate_limit_per_minute' => (int) env('GROQ_RATE_LIMIT_PER_MINUTE', 25),
        ],

        // Default prompt used when a department's ai_classification_prompt
        // is null. Available placeholders:
        //   {{department_name}}, {{categories_list}}, {{employee_block}},
        //   {{title}}, {{description}}
        'default_prompt' => null,
    ],

];
