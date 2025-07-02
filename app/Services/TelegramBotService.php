<?php

namespace App\Services;

use App\Models\TelegramBot;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    /**
     * Get bot info for display in views
     */
    public function getBotDisplayInfo(): array
    {
        $bot = TelegramBot::getActiveBot();

        if (!$bot) {
            // No bot configured - return fallback data that will trigger admin login
            return [
                'username' => '@videotestpowerbot', // Fallback
                'username_clean' => 'videotestpowerbot',
                'url' => 'https://t.me/videotestpowerbot',
                'first_name' => 'Bot',
                'description' => null,
                'is_configured' => false,
            ];
        }

        return [
            'username' => $bot->getUsernameWithAt(),
            'username_clean' => $bot->username,
            'url' => $bot->getBotUrl(),
            'first_name' => $bot->first_name,
            'description' => $bot->description,
            'is_configured' => true,
        ];
    }

    /**
     * Get bot URL with start parameter
     */
    public function getBotUrl(?string $startParam = null): string
    {
        $bot = TelegramBot::getActiveBot();

        if (!$bot) {
            $url = "https://t.me/videotestpowerbot"; // Fallback
        } else {
            $url = $bot->getBotUrl();
        }

        if ($startParam) {
            $url .= "?start={$startParam}";
        }

        return $url;
    }

    /**
     * Check if bot is configured
     */
    public function isConfigured(): bool
    {
        return TelegramBot::isConfigured();
    }

    /**
     * Setup bot from token
     */
    public function setupBotFromToken(string $token): TelegramBot
    {
        return TelegramBot::createFromToken($token);
    }
}
