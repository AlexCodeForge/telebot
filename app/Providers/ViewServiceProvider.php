<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\TelegramBotService;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Only share bot information with views during web requests
        // Skip during console commands (like migrations) to prevent bootstrap issues
        if (!app()->runningInConsole()) {
            View::composer('*', function ($view) {
                try {
                    $botService = app(TelegramBotService::class);
                    $view->with('bot', $botService->getBotDisplayInfo());
                } catch (\Exception $e) {
                    // Fallback data if service fails
                    $view->with('bot', [
                        'username' => '@videotestpowerbot',
                        'username_clean' => 'videotestpowerbot',
                        'url' => 'https://t.me/videotestpowerbot',
                        'first_name' => 'Bot',
                        'description' => null,
                        'is_configured' => false,
                    ]);
                }
            });
        }
    }
}
