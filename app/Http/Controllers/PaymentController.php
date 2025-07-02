<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\User;
use App\Models\Purchase;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Cashier;
use Exception;
use Stripe\Stripe;
use Stripe\Checkout\Session;

class PaymentController extends Controller
{
    /**
     * Get Stripe secret key from settings or config
     */
    private function getStripeSecretKey()
    {
        return Setting::get('stripe_secret') ?: config('cashier.secret');
    }

    /**
     * Get Stripe publishable key from settings or config
     */
    private function getStripePublishableKey()
    {
        return Setting::get('stripe_key') ?: config('cashier.key');
    }

    /**
     * Show payment form for a video
     */
    public function form(Video $video)
    {
        // Check if Stripe keys are configured
        $stripeKey = $this->getStripePublishableKey();
        $stripeSecret = $this->getStripeSecretKey();

        if (!$stripeKey || !$stripeSecret) {
            return back()->withErrors(['payment' => 'Payment system not configured. Please contact the administrator.']);
        }

        return view('payment.form', compact('video'));
    }

    /**
     * Create a checkout session for video purchase
     */
    public function process(Request $request, Video $video)
    {
        $request->validate([
            'telegram_username' => 'required|string|max:255',
        ]);

        $telegramUsername = trim($request->telegram_username, '@');

        // Check if user already purchased this video
        $existingPurchase = Purchase::where('telegram_username', $telegramUsername)
            ->where('video_id', $video->id)
            ->where('purchase_status', 'completed')
            ->first();

        if ($existingPurchase) {
            return back()->withErrors([
                'payment' => 'You have already purchased this video! Check your Telegram bot for access or view your purchase details.'
            ])->withInput();
        }

        $stripeSecret = $this->getStripeSecretKey();
        if (!$stripeSecret) {
            return back()->withErrors(['payment' => 'Payment system not configured.']);
        }

        Stripe::setApiKey($stripeSecret);

        try {
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => $video->title,
                                'description' => $video->description,
                            ],
                            'unit_amount' => $video->price * 100, // Convert to cents
                        ],
                        'quantity' => 1,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => route('payment.success', ['video' => $video->id]) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.cancel', ['video' => $video->id]) . '?session_id={CHECKOUT_SESSION_ID}',
                'metadata' => [
                    'video_id' => $video->id,
                    'telegram_username' => $telegramUsername,
                ],
            ]);

            return redirect($session->url);
        } catch (\Exception $e) {
            return back()->withErrors(['payment' => 'Payment processing failed: ' . $e->getMessage()]);
        }
    }

    /**
     * Handle successful payment
     */
    public function success(Request $request, Video $video)
    {
        $sessionId = $request->query('session_id');

        if (!$sessionId) {
            abort(404, 'Invalid payment session');
        }

        try {
            $stripeSecret = $this->getStripeSecretKey();
            Stripe::setApiKey($stripeSecret);

            // Retrieve the session from Stripe
            $session = Session::retrieve($sessionId);

            if ($session->payment_status !== 'paid') {
                abort(404, 'Payment not completed');
            }

            // Check if purchase record already exists
            $existingPurchase = Purchase::where('stripe_session_id', $sessionId)->first();

            if (!$existingPurchase) {
                // Create purchase record
                $purchase = $this->createPurchaseRecord($session, $video);
            } else {
                $purchase = $existingPurchase;
            }

            // Redirect to secure purchase page using UUID
            return redirect()->route('purchase.view', $purchase->purchase_uuid);
        } catch (\Exception $e) {
            Log::error('Payment success handling failed', [
                'session_id' => $sessionId,
                'video_id' => $video->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('payment.cancel', $video)
                ->withErrors(['payment' => 'Payment verification failed. Please contact support.']);
        }
    }

    /**
     * Handle cancelled payment
     */
    public function cancel(Video $video)
    {
        return view('payment.cancel', compact('video'));
    }

    /**
     * Show purchase details (secure with UUID)
     */
    public function viewPurchase(string $uuid)
    {
        $purchase = Purchase::findByUuid($uuid);

        if (!$purchase) {
            abort(404, 'Purchase not found');
        }

        return view('payment.purchase', compact('purchase'));
    }

    /**
     * Update the telegram username for a purchase
     */
    public function updateTelegramUsername(Request $request, $uuid)
    {
        $request->validate([
            'telegram_username' => 'required|string|max:255|regex:/^[a-zA-Z0-9_]+$/',
        ]);

        $purchase = Purchase::where('purchase_uuid', $uuid)->firstOrFail();

        // Clean the username (remove @ if present)
        $username = ltrim($request->telegram_username, '@');

        try {
            $purchase->update([
                'telegram_username' => $username,
            ]);

            Log::info('Customer updated telegram username', [
                'purchase_uuid' => $purchase->purchase_uuid,
                'old_username' => $purchase->getOriginal('telegram_username'),
                'new_username' => $username,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'username' => $username,
                'message' => 'Telegram username updated successfully!'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update telegram username', [
                'purchase_uuid' => $purchase->purchase_uuid,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update username. Please try again.'
            ]);
        }
    }

    /**
     * Get or create user by Telegram username
     */
    private function getOrCreateUser($telegramUsername)
    {
        // Clean username (remove @ if present)
        $username = ltrim($telegramUsername, '@');

        // Generate email from username
        $email = $username . '@telegram.local';

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $username,
                'telegram_username' => $username,
                'telegram_user_id' => null, // Will be set when user interacts with bot
                'password' => bcrypt(str()->random(32)), // Random password
            ]
        );
    }

    /**
     * Create purchase record from Stripe session
     */
    private function createPurchaseRecord($session, $video)
    {
        try {
            // Get user
            $telegramUsername = $session->metadata->telegram_username ?? null;
            $userId = $session->metadata->user_id ?? null;

            $user = null;
            if ($userId) {
                $user = User::find($userId);
            } elseif ($telegramUsername) {
                $user = $this->getOrCreateUser($telegramUsername);
            }

            // Create purchase record
            $purchase = Purchase::create([
                'stripe_session_id' => $session->id,
                'stripe_payment_intent_id' => $session->payment_intent,
                'stripe_customer_id' => $session->customer,
                'video_id' => $video->id,
                'user_id' => $user?->id,
                'amount' => $session->amount_total / 100, // Convert from cents
                'currency' => $session->currency,
                'customer_email' => $session->customer_details->email ?? $user?->email,
                'telegram_username' => $telegramUsername,
                'purchase_status' => 'completed',
                'delivery_status' => 'pending',
                'delivery_attempts' => 0,
                'stripe_metadata' => $session->metadata->toArray(),
            ]);

            Log::info('Purchase record created successfully', [
                'purchase_id' => $purchase->id,
                'session_id' => $session->id,
                'video_id' => $video->id,
                'telegram_username' => $telegramUsername,
            ]);

            return $purchase;
        } catch (\Exception $e) {
            Log::error('Failed to create purchase record', [
                'error' => $e->getMessage(),
                'session_id' => $session->id,
                'video_id' => $video->id,
            ]);

            throw $e;
        }
    }

    /**
     * Redirect to Stripe billing portal
     */
    public function billingPortal(Request $request)
    {
        return $request->user()->redirectToBillingPortal(
            route('videos.index')
        );
    }

    public function createPaymentIntent(Request $request)
    {
        $request->validate([
            'video_id' => 'required|exists:videos,id',
            'telegram_username' => 'required|string|max:255',
        ]);

        try {
            $video = Video::findOrFail($request->video_id);
            $telegramUsername = trim($request->telegram_username, '@');

            // Check if user already purchased this video
            $existingPurchase = Purchase::where('telegram_username', $telegramUsername)
                ->where('video_id', $video->id)
                ->where('purchase_status', 'completed')
                ->first();

            if ($existingPurchase) {
                return response()->json([
                    'error' => 'You have already purchased this video! Check your Telegram bot for access.',
                    'existing_purchase' => [
                        'purchase_date' => $existingPurchase->created_at->format('M d, Y'),
                        'verification_status' => $existingPurchase->verification_status,
                        'delivery_status' => $existingPurchase->delivery_status,
                        'purchase_uuid' => $existingPurchase->purchase_uuid,
                    ]
                ], 400);
            }

            $user = Auth::user();

            // Set Stripe API key
            $stripeSecret = $this->getStripeSecretKey();
            if (!$stripeSecret) {
                throw new \Exception('Stripe not configured');
            }

            \Stripe\Stripe::setApiKey($stripeSecret);

            // Create Stripe checkout session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => $video->title,
                            'description' => $video->description ?? 'Premium video content',
                        ],
                        'unit_amount' => $video->price * 100, // Convert to cents
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => route('payment.success', ['video' => $video->id]) . '?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('payment.cancel', ['video' => $video->id]),
                'metadata' => [
                    'video_id' => $video->id,
                    'telegram_username' => $telegramUsername,
                    'user_id' => $user?->id,
                ],
                'customer_email' => $user?->email,
            ]);

            Log::info('Stripe session created successfully', [
                'session_id' => $session->id,
                'video_id' => $video->id,
                'telegram_username' => $telegramUsername,
            ]);

            return response()->json([
                'session_id' => $session->id,
                'session_url' => $session->url,
            ]);

        } catch (\Exception $e) {
            Log::error('Payment intent creation failed', [
                'error' => $e->getMessage(),
                'video_id' => $request->video_id,
                'telegram_username' => $request->telegram_username,
            ]);

            return response()->json([
                'error' => 'Payment setup failed. Please try again.',
            ], 500);
        }
    }
}
