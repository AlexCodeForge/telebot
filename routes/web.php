<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\VideoController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\TelegramController;
use App\Http\Controllers\ProfileController;

// Customer-facing routes
Route::get('/', [VideoController::class, 'index'])->name('videos.index');
Route::get('/videos/{video}', [VideoController::class, 'show'])->name('videos.show');

// Payment routes
Route::get('/payment/{video}/form', [PaymentController::class, 'form'])->name('payment.form');
Route::post('/payment/{video}/process', [PaymentController::class, 'process'])->name('payment.process');
Route::get('/payment/{video}/success', [PaymentController::class, 'success'])->name('payment.success');
Route::get('/payment/{video}/cancel', [PaymentController::class, 'cancel'])->name('payment.cancel');
Route::get('/purchase/{uuid}', [PaymentController::class, 'viewPurchase'])->name('purchase.view');
Route::post('/purchase/{uuid}/update-username', [PaymentController::class, 'updateTelegramUsername'])->name('purchase.update-username');

// Authentication routes
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('admin.videos.manage');
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

    // Token management
    Route::post('/admin/tokens/save-all', [VideoController::class, 'saveAllTokens'])->name('admin.tokens.save-all');

    // Testing and manual import
    Route::get('/admin/videos/test-connection', [VideoController::class, 'testConnection'])->name('admin.videos.test-connection');
    Route::post('/admin/videos/manual-import', [VideoController::class, 'manualImport'])->name('admin.videos.manual-import');

    // Purchase management routes
    Route::get('/admin/purchases', [\App\Http\Controllers\Admin\PurchaseController::class, 'index'])->name('admin.purchases.index');
    Route::get('/admin/purchases/{purchase}', [\App\Http\Controllers\Admin\PurchaseController::class, 'show'])->name('admin.purchases.show');
    Route::post('/admin/purchases/{purchase}/verify', [\App\Http\Controllers\Admin\PurchaseController::class, 'verify'])->name('admin.purchases.verify');
    Route::post('/admin/purchases/{purchase}/mark-delivered', [\App\Http\Controllers\Admin\PurchaseController::class, 'markDelivered'])->name('admin.purchases.mark-delivered');
    Route::post('/admin/purchases/{purchase}/retry-delivery', [\App\Http\Controllers\Admin\PurchaseController::class, 'retryDelivery'])->name('admin.purchases.retry-delivery');
    Route::post('/admin/purchases/{purchase}/update-notes', [\App\Http\Controllers\Admin\PurchaseController::class, 'updateNotes'])->name('admin.purchases.update-notes');
    Route::post('/admin/purchases/{purchase}/update-username', [\App\Http\Controllers\Admin\PurchaseController::class, 'updateTelegramUsername'])->name('admin.purchases.update-username');
    Route::post('/admin/purchases/fix-stuck-deliveries', [\App\Http\Controllers\Admin\PurchaseController::class, 'fixStuckDeliveries'])->name('admin.purchases.fix-stuck-deliveries');
});

// Telegram webhook (must be accessible without auth)
Route::post('/telegram/webhook', [VideoController::class, 'webhook'])->name('telegram.webhook');

// Bot emulator for local testing
Route::get('/telegram/bot-emulator', [TelegramController::class, 'botEmulator']);
Route::post('/telegram/bot-emulator', [TelegramController::class, 'handleBotEmulator']);
Route::get('/bot-test', [TelegramController::class, 'botEmulator']); // Alias

// System status
Route::get('/system-status', [TelegramController::class, 'systemStatus']);

// One-time migration and setup route (REMOVE AFTER FIRST USE)
Route::get('/run-migrations-setup-once', function () {
    try {
        // Check if admin user already exists (better check than table existence)
        try {
            if (\App\Models\User::where('email', 'admin@telebot.local')->exists()) {
                return response()->json([
                    'status' => 'already_completed',
                    'message' => 'Database setup completed. Admin user exists. Remove this route for security.',
                    'login_url' => url('/login'),
                    'credentials' => [
                        'email' => 'admin@telebot.local',
                        'password' => 'admin123456'
                    ]
                ], 403);
            }
        } catch (\Exception $checkError) {
            // If checking fails, probably no tables exist yet - continue with setup
        }

        // Method 1: Try migrate:fresh (complete reset) - Works best with Supabase
        try {
            Artisan::call('migrate:fresh', ['--force' => true]);
            $migrationOutput = Artisan::output();
            $migrationMethod = 'migrate:fresh';
        } catch (\Exception $freshError) {
            // Method 2: If fresh fails, try regular migrate
            try {
                Artisan::call('migrate', ['--force' => true]);
                $migrationOutput = Artisan::output();
                $migrationMethod = 'migrate';
            } catch (\Exception $regularError) {
                // Method 3: For Supabase - simple schema reset
                try {
                    // Supabase-compatible schema reset (no Neon-specific commands)
                    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
                    DB::statement('CREATE SCHEMA public');
                    DB::statement('GRANT USAGE ON SCHEMA public TO public');
                    DB::statement('GRANT CREATE ON SCHEMA public TO public');

                    Artisan::call('migrate', ['--force' => true]);
                    $migrationOutput = Artisan::output();
                    $migrationMethod = 'supabase schema reset + migrate';
                } catch (\Exception $resetError) {
                    throw new \Exception("All migration methods failed. Last error: " . $resetError->getMessage());
                }
            }
        }

        // Create admin user
        $adminUser = \App\Models\User::create([
            'name' => 'Admin User',
            'email' => 'admin@telebot.local',
            'password' => Hash::make('admin123456'),
            'email_verified_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Database setup completed successfully!',
            'method_used' => $migrationMethod,
            'admin_credentials' => [
                'email' => 'admin@telebot.local',
                'password' => 'admin123456',
                'login_url' => url('/login')
            ],
            'migration_output' => $migrationOutput,
            'next_steps' => [
                '1. Visit ' . url('/login') . ' and login with above credentials',
                '2. Change the admin password immediately in profile settings',
                '3. Remove this route from routes/web.php for security',
                '4. Push the updated code to GitHub'
            ],
            'security_note' => 'IMPORTANT: Remove this endpoint after successful setup!'
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Setup failed: ' . $e->getMessage(),
            'solutions' => [
                '1. Go to your Supabase dashboard → SQL Editor → Run: DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;',
                '2. Or check your Vercel environment variables are using Supabase Transaction Pooler',
                '3. Then try this endpoint again'
            ],
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ],
            'contact' => 'If this persists, verify your Supabase connection details'
        ], 500);
    }
});

// API routes
Route::post('/api/create-payment-intent', [PaymentController::class, 'createPaymentIntent'])->name('api.create-payment-intent');

require __DIR__ . '/auth.php';
