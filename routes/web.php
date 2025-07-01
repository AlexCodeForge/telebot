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
Route::get('/payment/form', [PaymentController::class, 'showForm'])->name('payment.form');
Route::post('/payment/process', [PaymentController::class, 'processPayment'])->name('payment.process');
Route::get('/payment/success', [PaymentController::class, 'paymentSuccess'])->name('payment.success');
Route::get('/payment/cancel', [PaymentController::class, 'paymentCancel'])->name('payment.cancel');

// Authentication routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Admin video management routes
    Route::get('/admin/videos', [VideoController::class, 'manage'])->name('admin.videos.manage');
    Route::put('/admin/videos/{video}', [VideoController::class, 'update'])->name('admin.videos.update');
    Route::delete('/admin/videos/{video}', [VideoController::class, 'destroy'])->name('admin.videos.destroy');
    Route::post('/admin/videos/{video}/test', [VideoController::class, 'testVideo'])->name('admin.videos.test');

    // Webhook management
    Route::post('/admin/videos/deactivate-webhook', [VideoController::class, 'deactivateWebhook'])->name('admin.videos.deactivate-webhook');
    Route::post('/admin/videos/reactivate-webhook', [VideoController::class, 'reactivateWebhook'])->name('admin.videos.reactivate-webhook');
    Route::get('/admin/videos/webhook-status', [VideoController::class, 'webhookStatus'])->name('admin.videos.webhook-status');

    // Sync user management
    Route::post('/admin/videos/set-sync-user', [VideoController::class, 'setSyncUser'])->name('admin.videos.set-sync-user');
    Route::post('/admin/videos/remove-sync-user', [VideoController::class, 'removeSyncUser'])->name('admin.videos.remove-sync-user');

    // Bot settings
    Route::post('/admin/videos/toggle-bot-restriction', [VideoController::class, 'toggleBotRestriction'])->name('admin.videos.toggle-bot-restriction');

    // Testing and manual import
    Route::get('/admin/videos/test-connection', [VideoController::class, 'testConnection'])->name('admin.videos.test-connection');
    Route::post('/admin/videos/manual-import', [VideoController::class, 'manualImport'])->name('admin.videos.manual-import');
});

// Telegram webhook (must be accessible without auth)
Route::post('/telegram/webhook', [VideoController::class, 'webhook'])->name('telegram.webhook');

// Bot emulator for local testing
Route::get('/telegram/bot-emulator', [TelegramController::class, 'botEmulator']);
Route::post('/telegram/bot-emulator', [TelegramController::class, 'handleBotEmulator']);
Route::get('/bot-test', [TelegramController::class, 'botEmulator']); // Alias

// System status
Route::get('/system-status', [TelegramController::class, 'systemStatus']);

require __DIR__ . '/auth.php';
