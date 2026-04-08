<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| External integration API endpoints. These are tenant-scoped and
| authenticated via API tokens stored in tenant_integrations.
|
*/

use App\Http\Controllers\Api\AsaasWebhookController;
use App\Http\Controllers\Api\IntegrationWebhookController;
use App\Http\Controllers\Api\IntegrationApiController;
use Illuminate\Support\Facades\Route;

// Asaas payment webhook (no CSRF, no auth — verified by asaas-access-token header)
Route::post('/asaas/webhook', [AsaasWebhookController::class, 'handle'])
    ->middleware('throttle:webhooks')
    ->name('api.asaas.webhook');

Route::prefix('v1')->group(function () {
    // Webhook receiver - external systems push data to Mercury
    Route::post('/webhooks/{integration}', [IntegrationWebhookController::class, 'receive'])
        ->middleware('throttle:webhooks')
        ->name('api.webhooks.receive');

    // API endpoints for external integrations (authenticated via API key)
    Route::middleware(['integration.auth', 'throttle:api'])->group(function () {
        Route::get('/integrations/{integration}/status', [IntegrationApiController::class, 'status'])
            ->name('api.integrations.status');
        Route::post('/integrations/{integration}/sync', [IntegrationApiController::class, 'triggerSync'])
            ->name('api.integrations.sync');
        Route::get('/integrations/{integration}/data/{resource}', [IntegrationApiController::class, 'getData'])
            ->name('api.integrations.data');
        Route::post('/integrations/{integration}/data/{resource}', [IntegrationApiController::class, 'pushData'])
            ->name('api.integrations.push');
    });
});
