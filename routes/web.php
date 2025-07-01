<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\ProfileController;

// Customer-facing routes
Route::get('/', [VideoController::class, 'index'])->name('videos.index');
Route::get('/videos/{video}', [VideoController::class, 'show'])->name('videos.show');

// Payment routes
Route::get('/payment/{video}', [PaymentController::class, 'form'])->name('payment.form');
Route::post('/payment/{video}', [PaymentController::class, 'process'])->name('payment.process');
Route::get('/payment/success/{video}', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/cancel/{video}', [PaymentController::class, 'cancel'])->name('payment.cancel');

// Authentication routes
Route::get('/dashboard', function () {
    return redirect()->route('admin.videos.manage');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Admin routes - Protected with authentication
Route::prefix('admin')->name('admin.')->middleware(['auth', 'verified'])->group(function () {
    Route::get('/videos', [VideoController::class, 'capturedVideos'])->name('videos.manage');
    Route::put('/videos/{video}', [VideoController::class, 'update'])->name('videos.update');
    Route::delete('/videos/{video}', [VideoController::class, 'destroy'])->name('videos.destroy');
    Route::post('/videos/{video}/test', [VideoController::class, 'testVideo'])->name('videos.test');
    Route::post('/videos/set-sync-user', [VideoController::class, 'setSyncUser'])->name('videos.set-sync-user');
    Route::post('/videos/remove-sync-user', [VideoController::class, 'removeSyncUser'])->name('videos.remove-sync-user');
    Route::post('/videos/deactivate-webhook', [VideoController::class, 'deactivateWebhook'])->name('videos.deactivate-webhook');
    Route::post('/videos/reactivate-webhook', [VideoController::class, 'reactivateWebhook'])->name('videos.reactivate-webhook');
    Route::get('/videos/webhook-status', [VideoController::class, 'getWebhookStatus'])->name('videos.webhook-status');
    Route::get('/videos/test-connection', [VideoController::class, 'testTelegramConnection'])->name('videos.test-connection');
    Route::post('/videos/manual-import', [VideoController::class, 'manualImportVideo'])->name('videos.manual-import');
    Route::post('/videos/clear-all', [VideoController::class, 'clearAllVideos'])->name('videos.clear-all');
});

// Telegram webhooksy
Route::post('/telegram/webhook', [TelegramController::class, 'webhook']);

// Bot emulator for local testing
Route::get('/telegram/bot-emulator', [TelegramController::class, 'botEmulator']);
Route::post('/telegram/bot-emulator', [TelegramController::class, 'handleBotEmulator']);
Route::get('/bot-test', [TelegramController::class, 'botEmulator']); // Alias

// System status
Route::get('/system-status', [TelegramController::class, 'systemStatus']);

require __DIR__ . '/auth.php';
