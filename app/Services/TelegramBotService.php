<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Telegram\Bot\Laravel\Facades\Telegram;
use Illuminate\Support\Facades\Log;

class TelegramBotService
{
    /**
     * Get bot information from Telegram API
     */
    public function getBotInfo(): ?array
    {
        try {
            // Cache bot info for 1 hour to avoid repeated API calls
            return Cache::remember('telegram_bot_info', 3600, function () {
                $response = Telegram::getMe();
                return $response->toArray();
            });
        } catch (\Exception $e) {
            Log::error('Failed to get bot info from Telegram API', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get bot username (with @)
     */
    public function getBotUsername(): string
    {
        $botInfo = $this->getBotInfo();

        if ($botInfo && isset($botInfo['username'])) {
            return '@' . $botInfo['username'];
        }

        // Fallback to environment variable or default
        $fallbackToken = config('telegram.bot_token', env('TELEGRAM_BOT_TOKEN'));
        if ($fallbackToken) {
            // Try to extract from token (not reliable but better than hardcoded)
            Log::warning('Using fallback method for bot username - API call failed');
        }

        // Ultimate fallback - but this should be replaced once API works
        return '@videotestpowerbot';
    }

    /**
     * Get bot username without @
     */
    public function getBotUsernameClean(): string
    {
        return ltrim($this->getBotUsername(), '@');
    }

    /**
     * Get bot deep link URL
     */
    public function getBotUrl(?string $startParam = null): string
    {
        $username = $this->getBotUsernameClean();
        $url = "https://t.me/{$username}";

        if ($startParam) {
            $url .= "?start={$startParam}";
        }

        return $url;
    }

    /**
     * Get bot info for display in views
     */
    public function getBotDisplayInfo(): array
    {
        $botInfo = $this->getBotInfo();

        return [
            'username' => $this->getBotUsername(),
            'username_clean' => $this->getBotUsernameClean(),
            'url' => $this->getBotUrl(),
            'first_name' => $botInfo['first_name'] ?? 'Bot',
            'description' => $botInfo['description'] ?? null,
        ];
    }
}
