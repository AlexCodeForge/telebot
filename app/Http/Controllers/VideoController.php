<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Exception;
use Illuminate\Support\Facades\Http;

class VideoController extends Controller
{
    /**
     * Display a listing of videos for customers.
     */
    public function index()
    {
        $videos = Video::where('price', '>', 0)->get();
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
        $videos = Video::orderBy('created_at', 'desc')->get();
        return view('admin.videos.manage', compact('videos'));
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
            Telegram::sendVideo([
                'chat_id' => $syncUserTelegramId,
                'video' => $video->telegram_file_id,
                'caption' => "📹 **{$video->title}**\n💰 Price: \${$video->price}\n\n" . ($video->description ?? ''),
                'parse_mode' => 'Markdown'
            ]);

            return response()->json([
                'success' => true,
                'message' => "Video sent to {$syncUserName} successfully!"
            ]);
        } catch (Exception $e) {
            Log::error('Failed to send video to sync user: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => 'Failed to send video: ' . $e->getMessage()
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
     * Admin: Deactivate webhook to allow getUpdates.
     */
    public function deactivateWebhook()
    {
        try {
            $result = Telegram::removeWebhook();
            Log::info('Webhook deactivated', ['result' => $result]);
            return response()->json([
                'success' => true,
                'message' => 'Webhook deactivated successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to deactivate webhook: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Admin: Reactivate webhook.
     */
    public function reactivateWebhook()
    {
        try {
            $webhookUrl = config('telegram.bots.mybot.webhook_url');
            $result = Telegram::setWebhook(['url' => $webhookUrl]);
            Log::info('Webhook reactivated', ['webhook_url' => $webhookUrl, 'result' => $result]);
            return response()->json([
                'success' => true,
                'message' => 'Webhook reactivated successfully'
            ]);
        } catch (Exception $e) {
            Log::error('Failed to reactivate webhook: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get webhook status
     */
    public function webhookStatus()
    {
        try {
            $botToken = config('telegram.bot_token');
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
            $botToken = config('telegram.bot_token');
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
                    $syncUserMessages = array_filter($updates, function($update) use ($syncUserTelegramId) {
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
            Log::info('Webhook received', ['data' => $request->all()]);

            $data = $request->all();

            // Check if this is a message with video
            if (!isset($data['message'])) {
                Log::info('No message in webhook data');
                return response()->json(['status' => 'no_message']);
            }

            $message = $data['message'];
            $fromUser = $message['from'] ?? null;

            if (!$fromUser || !isset($fromUser['id'])) {
                Log::info('No from user in message');
                return response()->json(['status' => 'no_user']);
            }

            $fromUserId = $fromUser['id'];
            $syncUserTelegramId = Setting::get('sync_user_telegram_id');

            // Check if we have a configured sync user
            if (!$syncUserTelegramId) {
                Log::warning('No sync user configured, ignoring webhook');
                return response()->json(['status' => 'no_sync_user']);
            }

            // Check bot restriction setting
            $restrictToSyncUser = Setting::get('restrict_to_sync_user', false);

            if ($restrictToSyncUser && $fromUserId != $syncUserTelegramId) {
                Log::info('Message from non-sync user while restriction is enabled', [
                    'from_user_id' => $fromUserId,
                    'sync_user_id' => $syncUserTelegramId
                ]);

                // Send a polite rejection message
                $this->sendTelegramMessage($fromUserId,
                    "Sorry, this bot is currently restricted to authorized users only.");

                return response()->json(['status' => 'user_restricted']);
            }

            // Only process videos from sync user
            if ($fromUserId != $syncUserTelegramId) {
                Log::info('Message from non-sync user, ignoring', [
                    'from_user_id' => $fromUserId,
                    'sync_user_id' => $syncUserTelegramId
                ]);

                // Send an informative message to non-sync users
                $syncUserName = Setting::get('sync_user_name', 'authorized user');
                $this->sendTelegramMessage($fromUserId,
                    "Hello! This bot only processes videos from {$syncUserName}. Your message has been ignored.");

                return response()->json(['status' => 'not_sync_user']);
            }

            // Check if message has video
            $video = null;
            $fileId = null;
            $filename = null;

            if (isset($message['video'])) {
                $video = $message['video'];
                $fileId = $video['file_id'];
                $filename = $video['file_name'] ?? 'video_' . time();
            } elseif (isset($message['document']) &&
                      isset($message['document']['mime_type']) &&
                      str_starts_with($message['document']['mime_type'], 'video/')) {
                $video = $message['document'];
                $fileId = $video['file_id'];
                $filename = $video['file_name'] ?? 'video_' . time();
            }

            if (!$video || !$fileId) {
                Log::info('No video found in message from sync user');
                return response()->json(['status' => 'no_video']);
            }

            // Extract video information
            $caption = $message['caption'] ?? 'Captured Video ' . now()->format('M j, Y H:i');
            $duration = $video['duration'] ?? 0;
            $width = $video['width'] ?? 0;
            $height = $video['height'] ?? 0;
            $fileSize = $video['file_size'] ?? 0;

            // Create video record
            $videoRecord = Video::create([
                'title' => $caption,
                'description' => "Auto-captured from Telegram",
                'price' => 4.99, // Default price
                'telegram_file_id' => $fileId,
                'filename' => $filename,
                'duration' => $duration,
                'width' => $width,
                'height' => $height,
                'file_size' => $fileSize,
                'mime_type' => $video['mime_type'] ?? 'video/mp4'
            ]);

            Log::info('Video captured successfully', [
                'video_id' => $videoRecord->id,
                'file_id' => $fileId,
                'title' => $caption
            ]);

            // Send confirmation to user
            $this->sendTelegramMessage($fromUserId,
                "✅ Video captured successfully!\n\nTitle: {$caption}\nVideo ID: {$videoRecord->id}");

            return response()->json(['status' => 'success', 'video_id' => $videoRecord->id]);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Send a message to Telegram user
     */
    private function sendTelegramMessage($chatId, $text)
    {
        try {
            $botToken = config('telegram.bot_token');
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
