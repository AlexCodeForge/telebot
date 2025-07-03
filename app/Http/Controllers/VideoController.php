<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\Setting;
use App\Models\TelegramBot;
use App\Services\TelegramBotService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Exception;
use VercelBlobPhp\Client as BlobClient;
use VercelBlobPhp\CommonCreateBlobOptions;

class VideoController extends Controller
{
    /**
     * Display a listing of videos for customers.
     */
    public function index()
    {
        $videos = Video::orderBy('created_at', 'desc')->paginate(12);
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
        // Simple admin check
        if ($redirect = $this->requireAdmin()) {
            return $redirect;
        }

        try {
            $videos = Video::orderBy('created_at', 'desc')->paginate(15);

            // Get webhook status
            $isWebhookActive = false;
            $webhookUrl = '';

            // Get tokens from settings
            $telegramToken = Setting::get('telegram_bot_token');
            $stripeKey = Setting::get('stripe_key');
            $stripeSecret = Setting::get('stripe_secret');
            $stripeWebhookSecret = Setting::get('stripe_webhook_secret');
            $vercelBlobToken = Setting::get('vercel_blob_token');

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
                'stripeWebhookSecret',
                'vercelBlobToken'
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
        // Log incoming request for debugging
        Log::info('Video update request', [
            'video_id' => $video->id,
            'request_data' => $request->all()
        ]);

        try {
            $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric|min:0',
                'thumbnail_url' => 'nullable|url',
                'blur_intensity' => 'integer|min:1|max:20',
                'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validation failed', ['errors' => $e->errors()]);
            return response()->json([
                'success' => false,
                'message' => 'Validation failed: ' . implode(', ', array_map(function ($errors) {
                    return implode(', ', $errors);
                }, $e->errors()))
            ]);
        }

        $updateData = $request->only(['title', 'description', 'price', 'thumbnail_url', 'blur_intensity']);

        // Handle boolean fields explicitly (checkboxes send no value when unchecked)
        $updateData['show_blurred_thumbnail'] = $request->has('show_blurred_thumbnail') && $request->input('show_blurred_thumbnail') == '1' ? 1 : 0;
        $updateData['allow_preview'] = $request->has('allow_preview') && $request->input('allow_preview') == '1' ? 1 : 0;

        // Handle thumbnail upload
        if ($request->hasFile('thumbnail') && $request->file('thumbnail')->isValid()) {
            try {
                $thumbnailFile = $request->file('thumbnail');
                $extension = $thumbnailFile->getClientOriginalExtension();

                if (empty($extension)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Could not determine file extension.'
                    ]);
                }

                $thumbnailName = "thumbnails/" . time() . '_' . $video->id . '.' . $extension;

                Log::info('Processing thumbnail upload', [
                    'original_name' => $thumbnailFile->getClientOriginalName(),
                    'extension' => $extension,
                    'size' => $thumbnailFile->getSize(),
                    'mime_type' => $thumbnailFile->getMimeType(),
                    'new_name' => $thumbnailName
                ]);

                // Delete old thumbnail from Vercel Blob if exists
                if ($video->thumbnail_path && $video->thumbnail_blob_url) {
                    try {
                        $blobToken = Setting::get('vercel_blob_token') ?: env('test_READ_WRITE_TOKEN');
                        $blobClient = new BlobClient($blobToken);
                        // Extract blob name from URL for deletion
                        $pathToDelete = str_replace('thumbnails/', '', $video->thumbnail_path);
                        $blobClient->del([$pathToDelete]);
                        Log::info('Old thumbnail deleted from Vercel Blob', ['path' => $pathToDelete]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to delete old thumbnail from Vercel Blob', ['error' => $e->getMessage()]);
                    }
                }

                // Check if Vercel Blob token is available (settings first, then environment)
                $blobToken = Setting::get('vercel_blob_token') ?: env('test_READ_WRITE_TOKEN');
                if (empty($blobToken)) {
                    throw new \Exception('Vercel Blob token not configured in admin settings');
                }

                // Store new thumbnail to Vercel Blob directly (read from stream)
                $blobClient = new BlobClient($blobToken);

                // Read file content directly from upload stream to avoid filesystem issues
                $thumbnailContent = $thumbnailFile->get();

                if (empty($thumbnailContent)) {
                    throw new \Exception('Failed to read thumbnail file content');
                }

                $options = new CommonCreateBlobOptions(
                    addRandomSuffix: false,
                    contentType: $thumbnailFile->getMimeType() ?: 'image/jpeg',
                );

                Log::info('Attempting to upload to Vercel Blob', [
                    'filename' => $thumbnailName,
                    'size' => strlen($thumbnailContent),
                    'content_type' => $thumbnailFile->getMimeType(),
                    'token_exists' => !empty($blobToken)
                ]);

                $result = $blobClient->put($thumbnailName, $thumbnailContent, $options);

                if (!$result || !isset($result->url)) {
                    throw new \Exception('Vercel Blob upload failed - no URL returned');
                }

                $publicUrl = $result->url;

                $updateData['thumbnail_path'] = $thumbnailName;
                $updateData['thumbnail_blob_url'] = $publicUrl;
                // Clear thumbnail_url if uploading a file
                $updateData['thumbnail_url'] = null;

                Log::info('Thumbnail uploaded successfully to Vercel Blob', [
                    'stored_as' => $thumbnailName,
                    'url' => $publicUrl
                ]);

            } catch (\Exception $e) {
                Log::error('Thumbnail upload error', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'Error uploading thumbnail: ' . $e->getMessage()
                ]);
            }
        }

        Log::info('Updating video with data', [
            'video_id' => $video->id,
            'update_data' => $updateData,
            'original_show_blurred' => $video->show_blurred_thumbnail,
            'new_show_blurred' => $updateData['show_blurred_thumbnail'] ?? 'not set'
        ]);

        $video->update($updateData);

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
        // Delete thumbnail from Vercel Blob if exists
        if ($video->thumbnail_blob_url) {
            try {
                $blobToken = Setting::get('vercel_blob_token') ?: env('test_READ_WRITE_TOKEN');
                $blobClient = new BlobClient($blobToken);
                // Extract blob name from path for deletion
                $pathToDelete = str_replace('thumbnails/', '', $video->thumbnail_path);
                $blobClient->del([$pathToDelete]);
                Log::info('Thumbnail deleted from Vercel Blob', ['video_id' => $video->id, 'path' => $pathToDelete]);
            } catch (\Exception $e) {
                Log::warning('Failed to delete thumbnail from Vercel Blob', ['error' => $e->getMessage()]);
            }
        }

        // Delete local thumbnail if exists (fallback for old videos)
        if ($video->thumbnail_path && !$video->thumbnail_blob_url) {
            $thumbnailPath = storage_path('app/public/thumbnails/' . $video->thumbnail_path);
            if (file_exists($thumbnailPath)) {
                unlink($thumbnailPath);
                Log::info('Local thumbnail deleted with video', ['video_id' => $video->id, 'path' => $thumbnailPath]);
            }
        }

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
                        try {
                            // Create/update bot in database with fetched info from Telegram API
                            $botService = new TelegramBotService();
                            $bot = $botService->setupBotFromToken($token);

                            // Also save to settings for backward compatibility
                            Setting::set('telegram_bot_token', $token);
                            $savedTokens[] = "Telegram Bot Token (@{$bot->username})";
                        } catch (\Exception $e) {
                            $errors[] = 'Failed to setup bot: ' . $e->getMessage();
                        }
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

            // Validate and save Vercel Blob token
            if (isset($tokens['vercel_blob_token'])) {
                $blobToken = trim($tokens['vercel_blob_token']);
                if (!empty($blobToken)) {
                    if (!str_starts_with($blobToken, 'vercel_blob_rw_')) {
                        $errors[] = 'Invalid Vercel Blob token format. Should start with vercel_blob_rw_';
                    } else {
                        // Test the token by trying to list blobs
                        try {
                            $testClient = new BlobClient($blobToken);
                            $testClient->list(); // This will throw if token is invalid
                            Setting::set('vercel_blob_token', $blobToken);
                            $savedTokens[] = 'Vercel Blob Storage Token';
                        } catch (\Exception $e) {
                            $errors[] = 'Vercel Blob token is invalid or doesn\'t have proper permissions: ' . $e->getMessage();
                        }
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

            // Check if we have a message
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
            $username = $fromUser['username'] ?? null;
            $firstName = $fromUser['first_name'] ?? 'User';
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';

            Log::info('Processing webhook message', [
                'from_user_id' => $fromUserId,
                'username' => $username,
                'chat_id' => $chatId,
                'text' => $text,
                'first_name' => $firstName
            ]);

            $syncUserTelegramId = Setting::get('sync_user_telegram_id');

            Log::info('Sync user check', [
                'sync_user_telegram_id' => $syncUserTelegramId,
                'is_sync_user' => ($fromUserId == $syncUserTelegramId)
            ]);

            // **SYNC USER FLOW** - Handle video uploads from admin
            if ($fromUserId == $syncUserTelegramId) {
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

                if ($fileId) {
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
                            "🆔 Video ID: {$videoRecord->id}"
                    );

                    Log::info("Video auto-captured from sync user: {$fromUserId}", [
                        'video_id' => $videoRecord->id,
                        'file_id' => $fileId
                    ]);

                    return response()->json(['ok' => true]);
                }

                // Sync user basic commands
                if (strtolower($text) === '/start') {
                    $this->sendTelegramMessage(
                        $fromUserId,
                        "👋 Hello Admin! I'm ready to capture videos.\n\n" .
                            "🎥 Send me videos and I'll add them to your store!\n" .
                            "💡 Type /help for more information."
                    );
                    return response()->json(['ok' => true]);
                } elseif (strtolower($text) === '/help') {
                    $this->handleCustomerHelpCommand($chatId);
                    return response()->json(['ok' => true]);
                }
            }

            // **CUSTOMER FLOW** - Handle purchase verification and delivery
            else {
                Log::info('Processing customer message', [
                    'from_user_id' => $fromUserId,
                    'username' => $username,
                    'text' => $text,
                    'is_command' => str_starts_with($text, '/')
                ]);

                // Handle customer commands
                if (str_starts_with($text, '/')) {
                    $this->handleCustomerCommand($text, $chatId, $fromUserId, $username, $firstName);
                } elseif (isset($message['video'])) {
                    // Non-sync user sent video
                    $this->sendTelegramMessage(
                        $fromUserId,
                        "Thanks for the video! However, only admin videos are captured automatically. You can use customer commands like /start, /mypurchases, or /getvideo <id> for your purchases! 😊"
                    );
                } else {
                    // Non-command text from customer
                    $this->sendTelegramMessage(
                        $fromUserId,
                        "Hello! I'm the video store bot. Use /start to verify purchases, /help for commands, or /mypurchases to see your videos. 🎬"
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
     * Handle customer commands
     */
    private function handleCustomerCommand($text, $chatId, $telegramUserId, $username, $firstName)
    {
        $parts = explode(' ', trim($text));
        $command = $parts[0];
        $args = array_slice($parts, 1);

        Log::info('Customer command executed', [
            'command' => $command,
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'chat_id' => $chatId,
        ]);

        switch ($command) {
            case '/start':
                // Check if /start has parameters (like getvideo_5)
                if (count($args) > 0) {
                    $startParam = $args[0];

                    // Handle getvideo_X parameter
                    if (strpos($startParam, 'getvideo_') === 0) {
                        $videoId = str_replace('getvideo_', '', $startParam);
                        if (is_numeric($videoId)) {
                            Log::info('Start command with getvideo parameter', [
                                'video_id' => $videoId,
                                'telegram_user_id' => $telegramUserId,
                                'username' => $username
                            ]);
                            $this->handleCustomerGetVideoCommand($chatId, $telegramUserId, $username, $videoId);
                            return;
                        }
                    }
                }

                // Regular /start command
                $this->handleCustomerStartCommand($chatId, $telegramUserId, $username, $firstName);
                break;

            case '/help':
                $this->handleCustomerHelpCommand($chatId);
                break;

            case '/mypurchases':
                $this->handleCustomerMyPurchasesCommand($chatId, $telegramUserId, $username);
                break;

            case '/getvideo':
                if (count($args) > 0) {
                    $this->handleCustomerGetVideoCommand($chatId, $telegramUserId, $username, $args[0]);
                } else {
                    $this->sendTelegramMessage($chatId, "❌ Please provide a video ID. Usage: /getvideo <id>\n\nUse /mypurchases to see your available videos.");
                }
                break;

            default:
                $this->sendTelegramMessage($chatId, "❓ Unknown command. Type /help to see available commands.");
        }
    }

    /**
     * Handle customer /start command
     */
    private function handleCustomerStartCommand($chatId, $telegramUserId, $username, $firstName)
    {
        // Update/create user info
        $this->updateUserInfo($telegramUserId, $username, $firstName);

        Log::info('Customer /start command', [
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'chat_id' => $chatId
        ]);

        // Find ALL purchases for this user (by username OR telegram_user_id) that need processing
        $userPurchases = collect();

        if ($username) {
            // Get purchases by username
            $purchasesByUsername = \App\Models\Purchase::where('telegram_username', $username)
                ->where('purchase_status', 'completed')
                ->with('video')
                ->get();
            $userPurchases = $userPurchases->merge($purchasesByUsername);
        }

        // Get purchases by telegram_user_id (if they exist)
        $purchasesByTelegramId = \App\Models\Purchase::where('telegram_user_id', $telegramUserId)
            ->where('purchase_status', 'completed')
            ->with('video')
            ->get();
        $userPurchases = $userPurchases->merge($purchasesByTelegramId);

        // Remove duplicates by purchase ID
        $userPurchases = $userPurchases->unique('id');

        Log::info('Found user purchases', [
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'total_purchases' => $userPurchases->count(),
            'purchase_ids' => $userPurchases->pluck('id')->toArray()
        ]);

        if ($userPurchases->isEmpty()) {
            $message = "👋 *Welcome to Video Store Bot!*\n\n";
            $message .= "❌ No purchases found for your account.\n\n";
            $message .= "🛒 *To purchase videos:*\n";
            $message .= "1. Visit our website\n";
            if ($username) {
                $message .= "2. Purchase with username: @{$username}\n";
            }
            $message .= "3. Return here and use /start to access your videos\n\n";
            $message .= "💡 Type /help for more commands.";

            $this->sendTelegramMessage($chatId, $message);
            return;
        }

        // Process and deliver all user's videos
        $deliveredCount = 0;
        $alreadyDeliveredCount = 0;
        $deliveredVideos = collect();

        foreach ($userPurchases as $purchase) {
            try {
                // Link telegram_user_id if not already linked
                if (!$purchase->telegram_user_id) {
                    $purchase->update([
                        'telegram_user_id' => $telegramUserId,
                        'verification_status' => 'verified'
                    ]);
                    Log::info('Linked telegram_user_id to purchase', [
                        'purchase_id' => $purchase->id,
                        'telegram_user_id' => $telegramUserId
                    ]);
                }

                // Deliver video if not already delivered
                if ($purchase->delivery_status !== 'delivered') {
                    $this->deliverVideoToCustomer($chatId, $purchase);
                    $deliveredCount++;
                    $deliveredVideos->push($purchase);
                } else {
                    $alreadyDeliveredCount++;
                    $deliveredVideos->push($purchase);
                }
            } catch (\Exception $e) {
                Log::error('Error processing purchase in /start', [
                    'purchase_id' => $purchase->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        // Send summary message
        if ($deliveredCount > 0) {
            $message = "🎉 *Welcome to Video Store Bot!*\n\n";
            $message .= "✅ Found and delivered {$deliveredCount} video(s)!\n\n";

            foreach ($deliveredVideos as $purchase) {
                $status = $purchase->delivery_status === 'delivered' ? '✅' : '🆕';
                $message .= "📹 *{$purchase->video->title}* {$status}\n";
                $message .= "🆔 Video ID: {$purchase->video_id} | Price: {$purchase->formatted_amount}\n\n";
            }
        } else {
            $message = "👋 *Welcome back to Video Store Bot!*\n\n";
            $message .= "📋 You have {$alreadyDeliveredCount} video(s) in your library.\n\n";

            foreach ($deliveredVideos as $purchase) {
                $message .= "📹 *{$purchase->video->title}* ✅\n";
                $message .= "🆔 Video ID: {$purchase->video_id} | Price: {$purchase->formatted_amount}\n\n";
            }
        }

        $message .= "🤖 *Available Commands:*\n";
        $message .= "/mypurchases - See ALL your videos\n";
        $message .= "/getvideo <id> - Get any video instantly\n";
        $message .= "/help - Get help\n\n";
        $message .= "💡 Save this chat - you can download your videos anytime!";

        $this->sendTelegramMessage($chatId, $message);
    }

    /**
     * Handle customer /help command
     */
    private function handleCustomerHelpCommand($chatId)
    {
        $message = "🤖 *Video Store Bot Help*\n\n";
        $message .= "*Available Commands:*\n";
        $message .= "/start - Verify purchases & get videos\n";
        $message .= "/mypurchases - Show ALL purchased videos\n";
        $message .= "/getvideo <id> - Download specific video\n";
        $message .= "/help - Show this help\n\n";
        $message .= "*🎁 Free Videos:*\n";
        $message .= "Some videos are FREE! Use `/getvideo <ID>` with any free video ID to download instantly - no purchase required!\n\n";
        $message .= "*How to Purchase:*\n";
        $message .= "1. Visit our website video store\n";
        $message .= "2. Choose a video & enter your Telegram username\n";
        $message .= "3. Complete payment\n";
        $message .= "4. Return here and type /start to verify\n\n";
        $message .= "*How to Download:*\n";
        $message .= "1. Use /mypurchases to see your videos\n";
        $message .= "2. Use `/getvideo <ID>` to download\n";
        $message .= "3. You have unlimited access!\n\n";
        $message .= "*Need Support?*\n";
        $message .= "Contact us if you have issues with purchases or delivery.";

        $this->sendTelegramMessage($chatId, $message);
    }

    /**
     * Handle customer /mypurchases command
     */
    private function handleCustomerMyPurchasesCommand($chatId, $telegramUserId, $username)
    {
        if (!$username) {
            $this->sendTelegramMessage($chatId, "❌ You need a Telegram username to use this bot. Please set one in your Telegram settings.");
            return;
        }

        // Find purchases by EITHER telegram_user_id OR username
        $userPurchases = \App\Models\Purchase::where('purchase_status', 'completed')
            ->where(function ($query) use ($telegramUserId, $username) {
                $query->where('telegram_user_id', $telegramUserId);
                if ($username) {
                    $query->orWhere('telegram_username', $username);
                }
            })
            ->with('video')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($userPurchases->isEmpty()) {
            $message = "📋 *Your Purchased Videos*\n\n";
            $message .= "❌ No purchases found.\n\n";
            $message .= "🛒 *To purchase videos:*\n";
            $message .= "1. Visit our website\n";
            $message .= "2. Purchase with username: @{$username}\n";
            $message .= "3. Return here and use /start to verify\n\n";
            $message .= "💡 Make sure you use the exact username: *{$username}*";
        } else {
            $message = "📋 *Your Purchased Videos* ({$userPurchases->count()})\n\n";

            foreach ($userPurchases as $purchase) {
                $video = $purchase->video;
                $deliveryStatus = $purchase->delivery_status === 'delivered' ? '✅' : '⏳';

                $message .= "🎬 *{$video->title}*\n";
                $message .= "💰 {$purchase->formatted_amount} {$deliveryStatus}\n";
                $message .= "🆔 Video ID: *{$video->id}*\n";
                $message .= "📅 Purchased: {$purchase->created_at->format('M d, Y')}\n";
                $message .= "🔗 Use: `/getvideo {$video->id}`\n\n";
            }

            $message .= "💡 *Tip:* Use `/getvideo <ID>` to download any video instantly!";
        }

        $this->sendTelegramMessage($chatId, $message);
    }

    /**
     * Handle customer /getvideo command
     */
    private function handleCustomerGetVideoCommand($chatId, $telegramUserId, $username, $videoId)
    {
        Log::info('Customer getvideo command', [
            'chat_id' => $chatId,
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'video_id' => $videoId
        ]);

        if (!$username) {
            $this->sendTelegramMessage($chatId, "❌ You need a Telegram username to use this bot. Please set one in Telegram settings.");
            return;
        }

                        // First check if video exists and is free
        $video = \App\Models\Video::find($videoId);

        if (!$video) {
            $this->sendTelegramMessage($chatId, "❌ *Video Not Found*\n\nVideo #{$videoId} doesn't exist.\n\nUse /mypurchases to see available videos.");
            return;
        }

        // If video is FREE, allow anyone to download it
        if ($video->isFree()) {
            Log::info('Free video access granted', [
                'telegram_user_id' => $telegramUserId,
                'username' => $username,
                'video_id' => $videoId,
                'video_title' => $video->title
            ]);

            // Create a fake purchase object for delivery
            $freeAccess = new \stdClass();
            $freeAccess->video = $video;
            $freeAccess->id = 'free-access-' . $videoId;
            $freeAccess->video_id = $videoId;
            $freeAccess->formatted_amount = 'FREE';

            $this->deliverFreeVideoToCustomer($chatId, $freeAccess);
            return;
        }

        // For paid videos, find purchase by EITHER telegram_user_id OR username
        $purchase = \App\Models\Purchase::where('purchase_status', 'completed')
            ->where('video_id', $videoId)
            ->where(function ($query) use ($telegramUserId, $username) {
                $query->where('telegram_user_id', $telegramUserId);
                if ($username) {
                    $query->orWhere('telegram_username', $username);
                }
            })
            ->with('video')
            ->first();

        Log::info('Purchase lookup result', [
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'video_id' => $videoId,
            'video_price' => $video->price,
            'found_purchase' => $purchase ? true : false,
            'purchase_id' => $purchase ? $purchase->id : null,
            'purchase_verification' => $purchase ? $purchase->verification_status : null,
            'purchase_delivery' => $purchase ? $purchase->delivery_status : null
        ]);

        if (!$purchase) {
            $this->sendTelegramMessage($chatId, "❌ *Access Denied*\n\nYou haven't purchased video #{$videoId} ({$video->formatted_price}).\n\nUse /mypurchases to see available videos or /start to check for new purchases.");
            return;
        }

        // Auto-link telegram_user_id if not already linked
        if (!$purchase->telegram_user_id) {
            $purchase->update([
                'telegram_user_id' => $telegramUserId,
                'verification_status' => 'verified'
            ]);
            Log::info('Auto-linked telegram_user_id during getvideo', [
                'purchase_id' => $purchase->id,
                'telegram_user_id' => $telegramUserId
            ]);
        }

        // Deliver the video
        $this->deliverVideoToCustomer($chatId, $purchase);
    }

    /**
     * Deliver free video to customer
     */
    private function deliverFreeVideoToCustomer($chatId, $freeAccess)
    {
        $video = $freeAccess->video;

        try {
            if ($video->telegram_file_id) {
                // Send video using file_id
                $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
                $url = "https://api.telegram.org/bot{$botToken}/sendVideo";

                $data = [
                    'chat_id' => $chatId,
                    'video' => $video->telegram_file_id,
                    'caption' => "🎬 *{$video->title}* (FREE)\n\n" .
                        "✅ Enjoy this free video!\n" .
                        "🔗 Visit our store for more amazing content!",
                    'parse_mode' => 'Markdown'
                ];

                $response = Http::timeout(30)->post($url, $data);

                if ($response->successful()) {
                    Log::info('Free video delivered successfully', [
                        'video_id' => $video->id,
                        'chat_id' => $chatId,
                        'video_title' => $video->title
                    ]);
                } else {
                    Log::error('Failed to deliver free video', [
                        'video_id' => $video->id,
                        'chat_id' => $chatId,
                        'response' => $response->body()
                    ]);

                    $this->sendTelegramMessage($chatId, "❌ *Delivery Error*\n\nSorry, there was an issue delivering the free video. Please try again or contact support.");
                }
            } else {
                $this->sendTelegramMessage($chatId, "❌ *Video Unavailable*\n\nThis free video is not ready for delivery yet. Please try again later.");
            }
        } catch (\Exception $e) {
            Log::error('Exception during free video delivery', [
                'video_id' => $video->id,
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);

            $this->sendTelegramMessage($chatId, "❌ *Delivery Error*\n\nSorry, there was an issue delivering the free video. Please try again or contact support.");
        }
    }

    /**
     * Deliver video to customer
     */
    private function deliverVideoToCustomer($chatId, $purchase)
    {
        $video = $purchase->video;

        try {
            if ($video->telegram_file_id) {
                // Send video using file_id
                $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');
                $url = "https://api.telegram.org/bot{$botToken}/sendVideo";

                $response = Http::post($url, [
                    'chat_id' => $chatId,
                    'video' => $video->telegram_file_id,
                    'caption' => "🎬 *{$video->title}*\n\n" .
                        "📝 {$video->description}\n\n" .
                        "✅ Delivered successfully!\n" .
                        "💡 Use /getvideo {$video->id} anytime for unlimited access.",
                    'parse_mode' => 'Markdown'
                ]);

                if ($response->successful()) {
                    $purchase->markAsDelivered();

                    Log::info('Video delivered to customer', [
                        'purchase_id' => $purchase->id,
                        'video_id' => $video->id,
                        'telegram_user_id' => $purchase->telegram_user_id
                    ]);
                } else {
                    Log::error('Failed to send video to customer', [
                        'purchase_id' => $purchase->id,
                        'response' => $response->body()
                    ]);

                    $this->sendTelegramMessage($chatId, "❌ Failed to deliver video. Our team has been notified. Please try again in a few minutes.");
                }
            } else {
                Log::error('Video has no telegram_file_id', ['video_id' => $video->id]);
                $this->sendTelegramMessage($chatId, "❌ Video file not available. Our team has been notified.");
            }
        } catch (\Exception $e) {
            Log::error('Video delivery error', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage()
            ]);

            $this->sendTelegramMessage($chatId, "❌ Delivery error occurred. Please try again or contact support.");
        }
    }

    /**
     * Update user info for customers
     */
    private function updateUserInfo($telegramUserId, $username, $firstName)
    {
        if (!$telegramUserId) return;

        $user = \App\Models\User::where('telegram_user_id', $telegramUserId)->first();

        if ($user) {
            // Update existing user
            $updates = [];
            if ($username && $user->telegram_username !== $username) {
                $updates['telegram_username'] = $username;
            }
            if ($firstName && $user->name !== $firstName) {
                $updates['name'] = $firstName;
            }

            if (!empty($updates)) {
                $user->update($updates);
            }
        } else {
            // Create user only if they have purchases
            $hasPurchases = \App\Models\Purchase::where('telegram_username', $username)
                ->where('purchase_status', 'completed')
                ->exists();

            if ($hasPurchases) {
                \App\Models\User::create([
                    'name' => $firstName,
                    'telegram_user_id' => $telegramUserId,
                    'telegram_username' => $username,
                    'email' => $username . '@telegram.bot',
                    'password' => bcrypt('telegram_user_' . $telegramUserId),
                ]);
            }
        }
    }

    /**
     * Send a message to Telegram user
     */
    private function sendTelegramMessage($chatId, $text)
    {
        try {
            $botToken = Setting::get('telegram_bot_token') ?: config('telegram.bots.mybot.token');

            if (!$botToken || $botToken === 'YOUR-BOT-TOKEN') {
                Log::error('Invalid or missing bot token');
                return false;
            }

            $url = "https://api.telegram.org/bot{$botToken}/sendMessage";

            $data = [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'Markdown'
            ];

            $response = Http::timeout(30)->post($url, $data);

            if ($response->successful()) {
                Log::info('Telegram message sent successfully', [
                    'chat_id' => $chatId,
                    'message_preview' => substr($text, 0, 100)
                ]);
                return $response->json();
            } else {
                Log::error('Failed to send Telegram message', [
                    'chat_id' => $chatId,
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'message_preview' => substr($text, 0, 100)
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Telegram message sending failed', [
                'chat_id' => $chatId,
                'error' => $e->getMessage(),
                'message_preview' => substr($text, 0, 100)
            ]);
            return false;
        }
    }

    // clearAllVideos method removed - database management section removed
}
