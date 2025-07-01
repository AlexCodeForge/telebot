<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Exception;

class VideoController extends Controller
{
    /**
     * Display a listing of videos for customers.
     */
    public function index()
    {
        $videos = Video::orderBy('created_at', 'desc')->get();
        return view('videos.index', compact('videos'));
    }

    /**
     * Show the video detail page.
     */
    public function show(Video $video)
    {
        return view('videos.show', compact('video'));
    }

    /**
     * Admin: Display captured videos for management.
     */
    public function manage()
    {
        try {
            $videos = Video::orderBy('created_at', 'desc')->get();

            // Get webhook status
            $isWebhookActive = false;
            $webhookUrl = '';

            // Get tokens from settings
            $telegramToken = Setting::get('telegram_bot_token');
            $stripeKey = Setting::get('stripe_key');
            $stripeSecret = Setting::get('stripe_secret');
            $stripeWebhookSecret = Setting::get('stripe_webhook_secret');

            try {
                $botToken = $telegramToken ?: config('telegram.bots.mybot.token');
                if ($botToken && $botToken !== 'YOUR-BOT-TOKEN') {
                    $response = Http::timeout(10)->get("https://api.telegram.org/bot{$botToken}/getWebhookInfo");
                    if ($response->successful()) {
                        $webhookInfo = $response->json();
                        $isWebhookActive = !empty($webhookInfo['result']['url']);
                        $webhookUrl = $webhookInfo['result']['url'] ?? '';
                    }
                }
            } catch (Exception $e) {
                Log::warning('Failed to get webhook status: ' . $e->getMessage());
            }

            return view('admin.videos.manage', compact(
                'videos',
                'isWebhookActive',
                'webhookUrl',
                'telegramToken',
                'stripeKey',
                'stripeSecret',
                'stripeWebhookSecret'
            ));
        } catch (Exception $e) {
            Log::error('Error loading admin videos: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to load videos: ' . $e->getMessage());
        }
    }

    /**
     * Admin: Update video details.
     */
    public function update(Request $request, Video $video)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
        ]);

        $video->update($request->only(['title', 'description', 'price']));

        return response()->json([
            'success' => true,
            'message' => 'Video updated successfully!'
        ]);
    }

    /**
     * Admin: Delete a video.
     */
    public function destroy(Video $video)
    {
        $video->delete();
        return response()->json([
            'success' => true,
            'message' => 'Video deleted successfully!'
        ]);
    }

    /**
     * Admin: Test video by sending to sync user.
     */
    public function testVideo(Video $video)
    {
        $syncUserTelegramId = Setting::get('sync_user_telegram_id');
        $syncUserName = Setting::get('sync_user_name');

        if (!$syncUserTelegramId) {
            return response()->json([
                'success' => false,
                'error' => 'No sync user configured.'
            ]);
        }

        try {
            $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
            if (!$botToken || $botToken === 'YOUR-BOT-TOKEN') {
                return response()->json([
                    'success' => false,
                    'error' => 'Bot token not configured'
                ]);
            }

            $response = Http::post("https://api.telegram.org/bot{$botToken}/sendVideo", [
                'chat_id' => $syncUserTelegramId,
                'video' => $video->telegram_file_id,
                'caption' => "🧪 Test Video\n\n📹 {$video->title}\n💰 Price: $" . number_format($video->price, 2) . "\n🆔 ID: {$video->id}"
            ]);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Test video sent successfully'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send video'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Test video error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to send test video'
            ]);
        }
    }

    /**
     * Admin: Set sync user for testing.
     */
    public function setSyncUser(Request $request)
    {
        try {
            $telegramId = $request->input('telegram_id');
            $name = $request->input('name');

            if (empty($telegramId) || empty($name)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Both Telegram ID and name are required'
                ]);
            }

            Setting::set('sync_user_telegram_id', $telegramId);
            Setting::set('sync_user_name', $name);

            return response()->json([
                'success' => true,
                'message' => 'Sync user configured successfully!'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to set sync user', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Admin: Remove sync user.
     */
    public function removeSyncUser()
    {
        try {
            Setting::where('key', 'sync_user_telegram_id')->delete();
            Setting::where('key', 'sync_user_name')->delete();
            Setting::where('key', 'restrict_to_sync_user')->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sync user removed successfully!'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to remove sync user', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Toggle bot restriction to sync user only
     */
    public function toggleBotRestriction(Request $request)
    {
        try {
            $restrict = $request->input('restrict_to_sync_user', false);

            Setting::set('restrict_to_sync_user', $restrict);

            $message = $restrict
                ? 'Bot is now restricted to sync user only'
                : 'Bot restriction removed - anyone can message the bot';

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            Log::error('Toggle bot restriction failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to update bot restriction: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Save all API tokens at once
     */
    public function saveAllTokens(Request $request)
    {
        try {
            $tokens = $request->all();
            $savedTokens = [];
            $errors = [];

            // Validate and save Telegram token
            if (isset($tokens['telegram_token'])) {
                $token = trim($tokens['telegram_token']);
                if (!empty($token)) {
                    // Basic validation - Telegram bot tokens should follow pattern: digits:letters/digits
                    if (!preg_match('/^\d+:[A-Za-z0-9_-]+$/', $token)) {
                        $errors[] = 'Invalid Telegram token format. Should be: 123456789:ABCdefGHIjklMNOpqrsTUVwxyz';
                    } else {
                        Setting::set('telegram_bot_token', $token);
                        $savedTokens[] = 'Telegram Bot Token';
                    }
                }
            }

            // Validate and save Stripe publishable key
            if (isset($tokens['stripe_key'])) {
                $key = trim($tokens['stripe_key']);
                if (!empty($key)) {
                    if (!str_starts_with($key, 'pk_')) {
                        $errors[] = 'Invalid Stripe publishable key format. Should start with pk_';
                    } else {
                        Setting::set('stripe_key', $key);
                        $savedTokens[] = 'Stripe Publishable Key';
                    }
                }
            }

            // Validate and save Stripe secret key
            if (isset($tokens['stripe_secret'])) {
                $secret = trim($tokens['stripe_secret']);
                if (!empty($secret)) {
                    if (!str_starts_with($secret, 'sk_')) {
                        $errors[] = 'Invalid Stripe secret key format. Should start with sk_';
                    } else {
                        Setting::set('stripe_secret', $secret);
                        $savedTokens[] = 'Stripe Secret Key';
                    }
                }
            }

            // Validate and save Stripe webhook secret
            if (isset($tokens['stripe_webhook_secret'])) {
                $webhookSecret = trim($tokens['stripe_webhook_secret']);
                if (!empty($webhookSecret)) {
                    if (!str_starts_with($webhookSecret, 'whsec_')) {
                        $errors[] = 'Invalid Stripe webhook secret format. Should start with whsec_';
                    } else {
                        Setting::set('stripe_webhook_secret', $webhookSecret);
                        $savedTokens[] = 'Stripe Webhook Secret';
                    }
                }
            }

            if (!empty($errors)) {
                return response()->json([
                    'success' => false,
                    'error' => implode('. ', $errors)
                ]);
            }

            if (empty($savedTokens)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No valid tokens provided to save'
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Successfully saved: ' . implode(', ', $savedTokens)
            ]);
        } catch (Exception $e) {
            Log::error('Failed to save tokens', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Failed to save tokens: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Admin: Deactivate webhook to allow getUpdates.
     */
    // Deactivate webhook
    public function deactivateWebhook()
    {
        try {
            $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
            if (!$botToken || $botToken === 'YOUR-BOT-TOKEN') {
                return response()->json([
                    'success' => false,
                    'error' => 'Bot token not configured'
                ]);
            }

            $response = Http::post("https://api.telegram.org/bot{$botToken}/deleteWebhook");

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok']) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Webhook deactivated successfully'
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => $data['description'] ?? 'Failed to delete webhook'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to communicate with Telegram API'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deactivating webhook: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Network error occurred'
            ]);
        }
    }

    // Reactivate webhook
    public function reactivateWebhook()
    {
        try {
            $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
            if (!$botToken || $botToken === 'YOUR-BOT-TOKEN') {
                return response()->json([
                    'success' => false,
                    'error' => 'Bot token not configured'
                ]);
            }

            // First, delete any existing webhook
            Http::post("https://api.telegram.org/bot{$botToken}/deleteWebhook");

            // Set new webhook
            $webhookUrl = url('/telegram/webhook');
            $response = Http::post("https://api.telegram.org/bot{$botToken}/setWebhook", [
                'url' => $webhookUrl
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if ($data['ok']) {
                    return response()->json([
                        'success' => true,
                        'message' => 'Webhook activated successfully'
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'error' => $data['description'] ?? 'Failed to set webhook'
                    ]);
                }
            }

            return response()->json([
                'success' => false,
                'error' => 'Failed to communicate with Telegram API'
            ]);
        } catch (\Exception $e) {
            Log::error('Error reactivating webhook: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Network error occurred'
            ]);
        }
    }

    /**
     * Get webhook status
     */
    public function webhookStatus()
    {
        try {
            $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
            $url = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";

            $response = Http::get($url);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'webhook_info' => $response->json()['result']
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to get webhook info'
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test Telegram connection
     */
    public function testConnection()
    {
        try {
            $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
            $syncUserTelegramId = Setting::get('sync_user_telegram_id');

            if (!$syncUserTelegramId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No sync user configured. Please set a sync user first.'
                ]);
            }

            // Get bot info
            $botInfoUrl = "https://api.telegram.org/bot{$botToken}/getMe";
            $botInfoResponse = Http::get($botInfoUrl);

            if (!$botInfoResponse->successful()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to connect to bot: ' . $botInfoResponse->body()
                ]);
            }

            $botInfo = $botInfoResponse->json()['result'];

            // Check webhook status
            $webhookUrl = "https://api.telegram.org/bot{$botToken}/getWebhookInfo";
            $webhookResponse = Http::get($webhookUrl);
            $webhookInfo = $webhookResponse->json()['result'];
            $webhookActive = !empty($webhookInfo['url']);

            $responseData = [
                'success' => true,
                'data' => [
                    'bot_info' => $botInfo,
                    'webhook_active' => $webhookActive,
                    'can_use_getupdates' => !$webhookActive
                ]
            ];

            // Only try getUpdates if webhook is not active
            if (!$webhookActive) {
                $updatesUrl = "https://api.telegram.org/bot{$botToken}/getUpdates?limit=50";
                $updatesResponse = Http::get($updatesUrl);

                if ($updatesResponse->successful()) {
                    $updates = $updatesResponse->json()['result'];

                    // Filter and analyze messages from sync user
                    $syncUserMessages = array_filter($updates, function ($update) use ($syncUserTelegramId) {
                        return isset($update['message']['from']['id']) &&
                            $update['message']['from']['id'] == $syncUserTelegramId;
                    });

                    $messageAnalysis = [];
                    $videoMessagesFound = 0;

                    foreach ($syncUserMessages as $update) {
                        $message = $update['message'];
                        $hasVideo = isset($message['video']);
                        $hasVideoDocument = isset($message['document']) &&
                            isset($message['document']['mime_type']) &&
                            str_starts_with($message['document']['mime_type'], 'video/');

                        $videoFileId = null;
                        $documentFileId = null;

                        if ($hasVideo) {
                            $videoFileId = $message['video']['file_id'];
                            $videoMessagesFound++;
                        } elseif ($hasVideoDocument) {
                            $documentFileId = $message['document']['file_id'];
                            $videoMessagesFound++;
                        }

                        $messageAnalysis[] = [
                            'message_id' => $message['message_id'],
                            'from_id' => $message['from']['id'],
                            'from_first_name' => $message['from']['first_name'] ?? '',
                            'from_username' => $message['from']['username'] ?? '',
                            'date' => date('Y-m-d H:i:s', $message['date']),
                            'text' => $message['text'] ?? '',
                            'caption' => $message['caption'] ?? '',
                            'has_video' => $hasVideo || $hasVideoDocument,
                            'video_file_id' => $videoFileId,
                            'document_file_id' => $documentFileId
                        ];
                    }

                    $responseData['data']['message_analysis'] = $messageAnalysis;
                    $responseData['data']['total_messages_found'] = count($syncUserMessages);
                    $responseData['data']['video_messages_found'] = $videoMessagesFound;

                    if (count($syncUserMessages) === 0) {
                        $responseData['data']['message'] = 'No messages found from the configured sync user. Make sure the sync user has sent messages to the bot.';
                    }
                } else {
                    $responseData['data']['message'] = 'Could not retrieve conversation history: ' . $updatesResponse->body();
                }
            } else {
                $responseData['data']['message'] = 'Webhook is active - cannot retrieve conversation history using getUpdates method.';
            }

            return response()->json($responseData);
        } catch (\Exception $e) {
            Log::error('Test connection failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Test failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Manual import video
     */
    public function manualImport(Request $request)
    {
        try {
            $fileId = $request->input('file_id');
            $title = $request->input('title', 'Imported Video');
            $price = $request->input('price', 4.99);

            if (empty($fileId)) {
                return response()->json([
                    'success' => false,
                    'error' => 'File ID is required'
                ]);
            }

            // Check if sync user is configured
            $syncUserTelegramId = Setting::get('sync_user_telegram_id');
            if (!$syncUserTelegramId) {
                return response()->json([
                    'success' => false,
                    'error' => 'No sync user configured. Please set a sync user first.'
                ]);
            }

            // Check if video already exists
            $existingVideo = Video::where('telegram_file_id', $fileId)->first();
            if ($existingVideo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Video with this file ID already exists'
                ]);
            }

            // Create video record
            $video = Video::create([
                'title' => $title,
                'description' => 'Manually imported video',
                'price' => $price,
                'telegram_file_id' => $fileId,
                'filename' => 'imported_' . time() . '.mp4'
            ]);

            Log::info('Video manually imported', [
                'video_id' => $video->id,
                'file_id' => $fileId,
                'title' => $title
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Video imported successfully!',
                'video_id' => $video->id
            ]);
        } catch (\Exception $e) {
            Log::error('Manual import failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Import failed: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request)
    {
        try {
            $update = $request->all();
            Log::info('Webhook received:', $update);

            // Check if we have a message with video
            if (!isset($update['message'])) {
                Log::info('No message in update');
                return response()->json(['ok' => true]);
            }

            $message = $update['message'];
            $fromUser = $message['from'] ?? null;

            if (!$fromUser) {
                Log::info('No from user in message');
                return response()->json(['ok' => true]);
            }

            $fromUserId = $fromUser['id'];
            $syncUserTelegramId = Setting::get('sync_user_telegram_id');

            // Get video from message
            $video = null;
            $fileId = null;

            if (isset($message['video'])) {
                $video = $message['video'];
                $fileId = $video['file_id'];
            } elseif (
                isset($message['document']) &&
                isset($message['document']['mime_type']) &&
                strpos($message['document']['mime_type'], 'video/') === 0
            ) {
                $video = $message['document'];
                $fileId = $video['file_id'];
            }

            // If this user sent a video but is not the sync user, respond but don't capture
            if ($fileId && $fromUserId != $syncUserTelegramId) {
                $this->sendTelegramMessage(
                    $fromUserId,
                    "Thanks for the video! However, only the configured sync user's videos are automatically captured by this bot. You can still use other bot features normally! 😊"
                );

                Log::info("Video ignored from non-sync user: {$fromUserId} (sync user: {$syncUserTelegramId})");
                return response()->json(['ok' => true]);
            }

            // If this user sent a video and IS the sync user, capture it
            if ($fileId && $fromUserId == $syncUserTelegramId) {
                $caption = $message['caption'] ?? 'Auto-captured Video';
                $defaultPrice = 4.99;

                $videoRecord = Video::create([
                    'title' => $caption,
                    'description' => "Auto-captured from Telegram",
                    'telegram_file_id' => $fileId,
                    'price' => $defaultPrice,
                ]);

                $this->sendTelegramMessage(
                    $fromUserId,
                    "✅ Video captured successfully!\n\n" .
                        "📹 Title: {$caption}\n" .
                        "💰 Price: $" . number_format($defaultPrice, 2) . "\n" .
                        "🆔 File ID: {$fileId}"
                );

                Log::info("Video auto-captured from sync user: {$fromUserId}", [
                    'video_id' => $videoRecord->id,
                    'file_id' => $fileId
                ]);

                return response()->json(['ok' => true]);
            }

            // For any other messages (not videos), respond normally
            if (isset($message['text'])) {
                $text = $message['text'];

                // Handle basic commands
                if (strtolower($text) === '/start') {
                    $this->sendTelegramMessage(
                        $fromUserId,
                        "👋 Hello! I'm the video capture bot.\n\n" .
                            "🎥 Send me videos and I'll help you manage them!\n" .
                            "💡 Type /help for more information."
                    );
                } elseif (strtolower($text) === '/help') {
                    $this->sendTelegramMessage(
                        $fromUserId,
                        "🤖 Bot Commands:\n\n" .
                            "/start - Start the bot\n" .
                            "/help - Show this help message\n\n" .
                            "📹 Just send me a video and I'll capture it automatically!"
                    );
                } else {
                    $this->sendTelegramMessage(
                        $fromUserId,
                        "Thanks for your message! Send me a video to get started. 🎥"
                    );
                }
            }

            return response()->json(['ok' => true]);
        } catch (\Exception $e) {
            Log::error('Webhook error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['ok' => true]);
        }
    }

    /**
     * Send a message to Telegram user
     */
    private function sendTelegramMessage($chatId, $text)
    {
        try {
            $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $data = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML'
            ];

            $response = Http::post($url, $data);

            if ($response->successful()) {
                Log::info('Telegram message sent successfully', ['chat_id' => $chatId]);
                return $response->json();
            } else {
                Log::error('Failed to send Telegram message', [
                    'chat_id' => $chatId,
                    'response' => $response->body()
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram message sending failed: ' . $e->getMessage());
            return false;
        }
    }

    // clearAllVideos method removed - database management section removed
}
