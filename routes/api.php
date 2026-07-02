<?php

use Fahipay\Gateway\Http\Controllers\Api\PaymentController;
use Fahipay\Gateway\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->group(function () {
    // Operational endpoints: create a payment and read its status.
    Route::post('/', [PaymentController::class, 'store']);
    Route::get('/{transactionId}', [PaymentController::class, 'show']);
    Route::get('/{transactionId}/verify', [PaymentController::class, 'verify']);

    // Administrative endpoints: list every payment, mutate or delete records.
    // These are destructive / data-exposing, so they are OFF by default and
    // require their own (authenticated) middleware to be enabled explicitly.
    if (config('fahipay.api.admin_enabled', false)) {
        Route::middleware(config('fahipay.api.admin_middleware', []))->group(function () {
            Route::get('/', [PaymentController::class, 'index']);
            Route::patch('/{transactionId}', [PaymentController::class, 'update']);
            Route::delete('/{transactionId}', [PaymentController::class, 'destroy']);
        });
    }
});

Route::post('/webhook', [WebhookController::class, 'handle'])
    ->name('fahipay.api.webhook');
