<?php

use Fahipay\Gateway\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('callback')->group(function () {
    Route::get('/success', [WebhookController::class, 'success'])->name('fahipay.success');
    Route::get('/cancel', [WebhookController::class, 'cancel'])->name('fahipay.cancel');
    Route::get('/error', [WebhookController::class, 'error'])->name('fahipay.error');
    Route::get('/', [WebhookController::class, 'callback'])->name('fahipay.callback');
});
