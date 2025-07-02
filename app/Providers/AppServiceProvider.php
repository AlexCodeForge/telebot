<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;
use Illuminate\Pagination\Paginator;
use Laravel\Cashier\Events\WebhookReceived;
use App\Listeners\HandleSuccessfulPayment;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Use Bootstrap 5 pagination views
        Paginator::defaultView('vendor.pagination.bootstrap-5');
        Paginator::defaultSimpleView('vendor.pagination.simple-bootstrap-5');

        // Register Cashier webhook event listener
        Event::listen(
            WebhookReceived::class,
            HandleSuccessfulPayment::class
        );

        // Dynamically set Stripe configuration from database settings
        if (!app()->runningInConsole()) {
            try {
                $stripeKey = \App\Models\Setting::get('stripe_key');
                $stripeSecret = \App\Models\Setting::get('stripe_secret');
                $stripeWebhookSecret = \App\Models\Setting::get('stripe_webhook_secret');

                if ($stripeKey) {
                    config(['cashier.key' => $stripeKey]);
                }

                if ($stripeSecret) {
                    config(['cashier.secret' => $stripeSecret]);
                }

                if ($stripeWebhookSecret) {
                    config(['cashier.webhook.secret' => $stripeWebhookSecret]);
                }
            } catch (\Exception $e) {
                // Ignore errors during early application boot or missing database
                logger()->warning('Failed to load Stripe config from database: ' . $e->getMessage());
            }
        }
    }
}
