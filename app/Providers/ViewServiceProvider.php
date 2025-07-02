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
        // Share bot information with all views
        View::composer('*', function ($view) {
            $botService = app(TelegramBotService::class);
            $view->with('bot', $botService->getBotDisplayInfo());
        });
    }
}
