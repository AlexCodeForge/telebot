<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramBot extends Model
{
    protected $fillable = [
        'token',
        'bot_id',
        'username',
        'first_name',
        'description',
        'is_active',
        'fetched_at'
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
        'is_active' => 'boolean'
    ];

    /**
     * Get the active bot instance
     */
    public static function getActiveBot()
    {
        return static::where('is_active', true)->first();
    }

    /**
     * Create or update bot from token
     */
    public static function createFromToken(string $token)
    {
        try {
            // Fetch bot info from Telegram API
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getMe");

            if (!$response->successful()) {
                throw new \Exception('Failed to fetch bot info from Telegram API');
            }

            $botData = $response->json();

            if (!$botData['ok']) {
                throw new \Exception('Telegram API returned error: ' . ($botData['description'] ?? 'Unknown error'));
            }

            $botInfo = $botData['result'];

            // Deactivate any existing bots
            static::query()->update(['is_active' => false]);

            // Create or update bot
            return static::updateOrCreate(
                ['bot_id' => $botInfo['id']],
                [
                    'token' => $token,
                    'username' => $botInfo['username'],
                    'first_name' => $botInfo['first_name'],
                    'description' => $botInfo['description'] ?? null,
                    'is_active' => true,
                    'fetched_at' => now()
                ]
            );
        } catch (\Exception $e) {
            Log::error('Failed to create bot from token', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get bot username with @
     */
    public function getUsernameWithAt(): string
    {
        return '@' . $this->username;
    }

    /**
     * Get bot URL for deep linking
     */
    public function getBotUrl(?string $startParam = null): string
    {
        $url = "https://t.me/{$this->username}";

        if ($startParam) {
            $url .= "?start={$startParam}";
        }

        return $url;
    }

    /**
     * Check if bot is configured and active
     */
    public static function isConfigured(): bool
    {
        return static::where('is_active', true)->exists();
    }
}
