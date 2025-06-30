<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\User;
use App\Models\Purchase;
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
     * Show payment form for a video
     */
    public function form(Video $video)
    {
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

        Stripe::setApiKey(config('services.stripe.secret'));

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
                'success_url' => route('payment.success', $video->id),
                'cancel_url' => route('payment.cancel', $video->id),
                'metadata' => [
                    'video_id' => $video->id,
                    'telegram_username' => $request->telegram_username,
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
    public function success(Video $video)
    {
        return view('payment.success', compact('video'));
    }

    /**
     * Handle cancelled payment
     */
    public function cancel(Video $video)
    {
        return view('payment.cancel', compact('video'));
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
}
