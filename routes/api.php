<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// WhatsApp webhook routes
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook']);
Route::get('/whatsapp/webhook', [WhatsAppController::class, 'verifyWebhook']);

// Discord webhook routes
Route::post('/discord/webhook', [DiscordController::class, 'webhook']);