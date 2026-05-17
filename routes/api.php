<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Http\Controllers\Api\ChatProcessController;

Route::prefix('v1')->group(function () {
    Route::post('/chat/process', [ChatProcessController::class, 'process']);
});

Route::get('/user', function (Request $request) {
...
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/webhook/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);
