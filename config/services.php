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

    /*
    |--------------------------------------------------------------------------
    | Postmark Inbound (helpdesk email-to-ticket)
    |--------------------------------------------------------------------------
    |
    | Webhook endpoint is POST /api/webhooks/helpdesk/email/{tenant}. Postmark
    | doesn't HMAC-sign inbound payloads, so we authenticate via a shared
    | token set here. The token can be sent as HTTP Basic Auth username or
    | the x-mercury-webhook-token header — Postmark's dashboard supports both.
    |
    */
    'postmark_inbound' => [
        'webhook_token' => env('POSTMARK_INBOUND_WEBHOOK_TOKEN'),
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

    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://sandbox.asaas.com/api/v3'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Evolution API (WhatsApp)
    |--------------------------------------------------------------------------
    |
    | Evolution runs in a Docker container. When the Laravel app is on the
    | host, `base_url` should be http://localhost:8085 (or whatever port is
    | exposed). When both run in the same docker network, use the service
    | name (e.g. http://evolution-api:8080). Never hardcode — always env.
    |
    | `webhook_token` is the shared secret Evolution sends in the
    | `x-mercury-webhook-token` header so our public webhook can verify the
    | caller.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | TaneIA (AI assistant microservice)
    |--------------------------------------------------------------------------
    |
    | The Python microservice that backs the TaneIA assistant. The Laravel
    | app only proxies prompts to this endpoint; all LLM logic lives there.
    |
    */
    'taneia' => [
        'base_url' => env('TANEIA_BASE_URL', 'http://localhost:8001'),
        'chat_path' => env('TANEIA_CHAT_PATH', '/api/taneia/chat'),
        'upload_path' => env('TANEIA_UPLOAD_PATH', '/api/taneia/upload'),
        'timeout' => (int) env('TANEIA_TIMEOUT', 60),
        'upload_timeout' => (int) env('TANEIA_UPLOAD_TIMEOUT', 120),
    ],

    'evolution' => [
        'base_url' => env('EVOLUTION_API_URL', 'http://localhost:8085'),
        'api_key' => env('EVOLUTION_API_KEY'),
        'instance' => env('EVOLUTION_INSTANCE', 'mercury-dp'),
        'webhook_token' => env('EVOLUTION_WEBHOOK_TOKEN'),
        // Public URL of the Laravel app AS SEEN BY the Evolution container.
        // On Windows/Mac with app on host and Evolution in Docker, this is
        // typically http://host.docker.internal:8000. When both are in the
        // same docker network, use the service name (e.g. http://mercury-app).
        'webhook_public_url' => env('HELPDESK_WEBHOOK_PUBLIC_URL', 'http://host.docker.internal:8000'),
        // When true, skip outbound calls — useful in tests/CI.
        'fake' => env('EVOLUTION_FAKE', false),
    ],

];
