<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Exception;
use Illuminate\Support\Facades\Http;

class VideoController extends Controller
{
    /**
     * Display a listing of available videos for customers
     */
    public function index()
    {
        // Only show videos that have telegram_file_id and price > 0 (are ready for sale)
        $videos = Video::whereNotNull('telegram_file_id')
            ->where('price', '>', 0)
            ->latest()
            ->paginate(12);

        return view('videos.index', compact('videos'));
    }

    /**
     * Display the specified video for customers
     */
    public function show(Video $video)
    {
        // Ensure video is available for customers
        if (!$video->telegram_file_id || $video->price == 0) {
            abort(404, 'Video not available');
        }

        return view('videos.show', compact('video'));
    }

    /**
     * Admin: Display captured videos for management
     */
    public function capturedVideos()
    {
        $videos = Video::latest()->paginate(20);
        $syncUserTelegramId = Setting::get('sync_user_telegram_id');
        $syncUserName = Setting::get('sync_user_name');

        // Calculate stats
        $stats = [
            'total' => Video::count(),
            'ready' => Video::where('price', '>', 0)->count(),
            'pending' => Video::where('price', '=', 0)->count(),
        ];

        return view('admin.videos.manage', compact('videos', 'syncUserTelegramId', 'syncUserName', 'stats'));
    }

    /**
     * Admin: Update video (for modal editing)
     */
    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'price' => 'required|numeric|min:0|max:999999.99',
        ]);

        $video->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Video updated successfully!',
            'video' => $video->fresh()
        ]);
    }

    /**
     * Admin: Remove video
     */
    public function destroy(Video $video)
    {
        $video->delete();
        return back()->with('success', 'Video deleted successfully!');
    }

    /**
     * Admin: Test video delivery via Telegram
     */
    public function testVideo(Video $video)
    {
        if (!$video->telegram_file_id) {
            return back()->with('error', 'No Telegram file ID available.');
        }

        try {
            $testChatId = 5928450281; // Your chat ID for testing

            Telegram::sendVideo([
                'chat_id' => $testChatId,
                'video' => $video->telegram_file_id,
                'caption' => "🧪 **Test: {$video->title}**\n💰 \${$video->price}",
                'parse_mode' => 'Markdown'
            ]);

            return back()->with('success', 'Test video sent!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to send test video.');
        }
    }

    /**
     * Admin: Set sync user by Telegram user ID
     */
    public function setSyncUser(Request $request)
    {
        $request->validate([
            'telegram_user_id' => 'required|string',
            'name' => 'nullable|string|max:255'
        ]);

        $telegramUserId = $request->telegram_user_id;
        $name = $request->name ?: "User {$telegramUserId}";

        // Store both the telegram user ID and optional name
        Setting::set('sync_user_telegram_id', $telegramUserId, 'string');
        Setting::set('sync_user_name', $name, 'string');

        return back()->with('success', "Sync user set to: {$name} (ID: {$telegramUserId})");
    }

    /**
     * Admin: Remove sync user
     */
    public function removeSyncUser()
    {
        Setting::set('sync_user_telegram_id', null, 'string');
        Setting::set('sync_user_name', null, 'string');

        return back()->with('success', 'Sync user removed successfully.');
    }

    /**
     * Admin: Deactivate webhook
     */
    public function deactivateWebhook()
    {
        try {
            $result = Telegram::deleteWebhook();
            Log::info('Webhook deactivated', ['result' => $result]);
            return back()->with('success', 'Webhook deactivated successfully. You can now use sync and getUpdates.');
        } catch (Exception $e) {
            Log::error('Failed to deactivate webhook: ' . $e->getMessage());
            return back()->with('error', 'Failed to deactivate webhook: ' . $e->getMessage());
        }
    }

    /**
     * Admin: Reactivate webhook
     */
    public function reactivateWebhook()
    {
        try {
            $webhookUrl = url('/telegram/webhook');
            $result = Telegram::setWebhook(['url' => $webhookUrl]);
            Log::info('Webhook reactivated', ['result' => $result, 'url' => $webhookUrl]);
            return back()->with('success', "Webhook reactivated successfully at: {$webhookUrl}");
        } catch (Exception $e) {
            Log::error('Failed to reactivate webhook: ' . $e->getMessage());
            return back()->with('error', 'Failed to reactivate webhook: ' . $e->getMessage());
        }
    }

    /**
     * Admin: Get webhook status
     */
    public function getWebhookStatus()
    {
        try {
            $result = Telegram::getWebhookInfo();
            return response()->json([
                'success' => true,
                'webhook_info' => $result
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get webhook status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin: Sync videos from the configured user's chat history
     */
    public function syncVideos()
    {
        $syncUserTelegramId = Setting::get('sync_user_telegram_id');
        $syncUserName = Setting::get('sync_user_name');

        if (!$syncUserTelegramId) {
            return back()->with('error', 'No sync user configured. Please set a sync user first.');
        }

        try {
            // First check webhook status
            $webhookInfo = Telegram::getWebhookInfo();
            Log::info('Webhook info check', ['webhook_info' => $webhookInfo]);

            if (!empty($webhookInfo['url'])) {
                return back()->with('error', 'Webhook is active! Please deactivate the webhook first to use sync functionality. Active webhook: ' . $webhookInfo['url']);
            }

            // Get updates from Telegram
            $updates = Telegram::getUpdates(['limit' => 100]);
            Log::info('GetUpdates response', ['response' => $updates]);

            if (!is_array($updates) || !isset($updates['ok']) || !$updates['ok']) {
                return back()->with('error', 'Failed to get updates from Telegram API. Response: ' . json_encode($updates));
            }

            if (!isset($updates['result']) || !is_array($updates['result'])) {
                return back()->with('error', 'Invalid response from Telegram API. No result array found.');
            }

            Log::info('Sync attempt', [
                'sync_user_id' => $syncUserTelegramId,
                'sync_user_name' => $syncUserName,
                'updates_count' => count($updates['result'])
            ]);

            $newVideos = 0;
            $existingVideos = 0;
            $totalMessages = 0;
            $debugInfo = [];

            if (empty($updates['result'])) {
                return back()->with('error', 'No recent updates found from Telegram. This could mean: 1) Webhook is still active, 2) No recent messages, or 3) All updates have been consumed. Try deactivating webhook first.');
            }

            foreach ($updates['result'] as $update) {
                $totalMessages++;

                // Debug info for each message
                $messageInfo = [
                    'update_id' => $update['update_id'] ?? 'unknown',
                    'has_message' => isset($update['message']),
                    'has_video' => isset($update['message']['video']),
                    'from_id' => $update['message']['from']['id'] ?? 'unknown',
                    'expected_id' => $syncUserTelegramId
                ];
                $debugInfo[] = $messageInfo;

                if (
                    isset($update['message']['video']) &&
                    isset($update['message']['from']['id']) &&
                    $update['message']['from']['id'] == $syncUserTelegramId
                ) {

                    $videoData = $update['message']['video'];
                    $fileId = $videoData['file_id'];

                    Log::info('Found video from sync user', [
                        'file_id' => $fileId,
                        'duration' => $videoData['duration'] ?? 'unknown',
                        'file_size' => $videoData['file_size'] ?? 'unknown'
                    ]);

                    // Check if video already exists
                    $existingVideo = Video::where('telegram_file_id', $fileId)->first();

                    if (!$existingVideo) {
                        // Create new video entry
                        $video = Video::create([
                            'title' => 'Video ' . date('Y-m-d H:i:s'),
                            'description' => $update['message']['caption'] ?? 'Synced from user',
                            'price' => 0, // Default price, can be edited later
                            'telegram_file_id' => $fileId,
                            'file_unique_id' => $videoData['file_unique_id'] ?? null,
                            'file_size' => $videoData['file_size'] ?? null,
                            'duration' => $videoData['duration'] ?? null,
                            'width' => $videoData['width'] ?? null,
                            'height' => $videoData['height'] ?? null,
                            'telegram_message_data' => $update['message']
                        ]);

                        $newVideos++;
                        Log::info('Created new video', ['video_id' => $video->id, 'title' => $video->title]);
                    } else {
                        $existingVideos++;
                        Log::info('Video already exists', ['existing_video_id' => $existingVideo->id]);
                    }
                }
            }

            Log::info('Sync completed', [
                'total_messages' => $totalMessages,
                'new_videos' => $newVideos,
                'existing_videos' => $existingVideos,
                'debug_info' => $debugInfo
            ]);

            if ($newVideos === 0 && $existingVideos === 0) {
                $message = "Sync completed but no videos found from user {$syncUserName} (ID: {$syncUserTelegramId}). ";
                $message .= "Checked {$totalMessages} recent messages. ";
                $message .= "Make sure the user ID is correct and the user has sent videos recently to the bot.";

                return back()->with('warning', $message);
            }

            $message = "Sync completed! Found {$newVideos} new videos";
            if ($existingVideos > 0) {
                $message .= " and {$existingVideos} existing videos";
            }
            $message .= " from {$syncUserName}. Processed {$totalMessages} total messages.";

            return back()->with('success', $message);
        } catch (Exception $e) {
            Log::error('Failed to sync videos: ' . $e->getMessage(), [
                'exception' => $e->getTraceAsString(),
                'sync_user_id' => $syncUserTelegramId ?? 'not set'
            ]);
            return back()->with('error', 'Failed to sync videos: ' . $e->getMessage());
        }
    }

    /**
     * Admin: Send video to sync user
     */
    public function sendToSyncUser(Video $video)
    {
        $syncUserTelegramId = Setting::get('sync_user_telegram_id');
        $syncUserName = Setting::get('sync_user_name');

        if (!$syncUserTelegramId) {
            return back()->with('error', 'No sync user configured. Please set a sync user first.');
        }

        if (!$video->telegram_file_id) {
            return back()->with('error', 'No Telegram file ID available for this video.');
        }

        try {
            Telegram::sendVideo([
                'chat_id' => $syncUserTelegramId,
                'video' => $video->telegram_file_id,
                'caption' => "📹 **{$video->title}**\n💰 Price: \${$video->price}\n\n" . ($video->description ?? ''),
                'parse_mode' => 'Markdown'
            ]);

            return back()->with('success', "Video sent to {$syncUserName} successfully!");
        } catch (Exception $e) {
            Log::error('Failed to send video to sync user: ' . $e->getMessage());
            return back()->with('error', 'Failed to send video: ' . $e->getMessage());
        }
    }

    /**
     * Admin: Get video thumbnail from Telegram
     */
    public function getVideoThumbnail(Video $video)
    {
        if (!$video->telegram_file_id) {
            return response()->json(['error' => 'No Telegram file ID available'], 400);
        }

        try {
            // First, let's try to get the video message data to see if it has a thumbnail
            if ($video->telegram_message_data && isset($video->telegram_message_data['video']['thumb'])) {
                $thumbData = $video->telegram_message_data['video']['thumb'];
                $thumbFileId = $thumbData['file_id'];

                // Get the thumbnail file path
                $fileInfo = Telegram::getFile(['file_id' => $thumbFileId]);

                if (isset($fileInfo['file_path'])) {
                    $thumbnailUrl = "https://api.telegram.org/file/bot" . config('telegram.bots.mybot.token') . "/" . $fileInfo['file_path'];

                    // Update video with thumbnail URL
                    $video->update(['thumbnail_url' => $thumbnailUrl]);

                    return response()->json([
                        'success' => true,
                        'thumbnail_url' => $thumbnailUrl
                    ]);
                }
            }

            // If no thumbnail in stored data, try to get fresh video info
            $updates = Telegram::getUpdates(['limit' => 100]);

            foreach ($updates['result'] as $update) {
                if (
                    isset($update['message']['video']) &&
                    $update['message']['video']['file_id'] === $video->telegram_file_id
                ) {

                    $videoData = $update['message']['video'];

                    // Update the stored message data
                    $video->update(['telegram_message_data' => $update['message']]);

                    if (isset($videoData['thumb'])) {
                        $thumbData = $videoData['thumb'];
                        $thumbFileId = $thumbData['file_id'];

                        $fileInfo = Telegram::getFile(['file_id' => $thumbFileId]);

                        if (isset($fileInfo['file_path'])) {
                            $thumbnailUrl = "https://api.telegram.org/file/bot" . config('telegram.bots.mybot.token') . "/" . $fileInfo['file_path'];

                            $video->update(['thumbnail_url' => $thumbnailUrl]);

                            return response()->json([
                                'success' => true,
                                'thumbnail_url' => $thumbnailUrl
                            ]);
                        }
                    }
                    break;
                }
            }

            return response()->json(['error' => 'No thumbnail available for this video'], 400);
        } catch (Exception $e) {
            Log::error('Failed to get video thumbnail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get thumbnail: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Admin: Test Telegram API connection and debug sync issues
     */
    public function testTelegramConnection()
    {
        try {
            // Test basic bot info
            $botInfo = Telegram::getMe();
            Log::info('Bot info test', ['bot_info' => $botInfo]);

            // Test webhook status
            $webhookInfo = Telegram::getWebhookInfo();
            Log::info('Webhook status test', ['webhook_info' => $webhookInfo]);

            // Test getUpdates - get more messages for debugging
            $updates = Telegram::getUpdates(['limit' => 20]);
            Log::info('GetUpdates test', ['updates' => $updates]);

            // Process and analyze all messages
            $messageAnalysis = [];
            if (isset($updates['result']) && is_array($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    $analysis = [
                        'update_id' => $update['update_id'] ?? 'unknown',
                        'message_exists' => isset($update['message']),
                    ];

                    if (isset($update['message'])) {
                        $message = $update['message'];
                        $analysis['message_id'] = $message['message_id'] ?? 'unknown';
                        $analysis['chat_id'] = $message['chat']['id'] ?? 'unknown';
                        $analysis['chat_type'] = $message['chat']['type'] ?? 'unknown';
                        $analysis['from_id'] = $message['from']['id'] ?? 'unknown';
                        $analysis['from_username'] = $message['from']['username'] ?? 'no username';
                        $analysis['from_first_name'] = $message['from']['first_name'] ?? 'no name';
                        $analysis['has_video'] = isset($message['video']);
                        $analysis['has_photo'] = isset($message['photo']);
                        $analysis['has_document'] = isset($message['document']);
                        $analysis['text'] = $message['text'] ?? null;
                        $analysis['caption'] = $message['caption'] ?? null;
                        $analysis['date'] = date('Y-m-d H:i:s', $message['date'] ?? 0);

                        if (isset($message['video'])) {
                            $analysis['video_file_id'] = $message['video']['file_id'] ?? 'unknown';
                            $analysis['video_duration'] = $message['video']['duration'] ?? 'unknown';
                            $analysis['video_file_size'] = $message['video']['file_size'] ?? 'unknown';
                        }
                    }

                    $messageAnalysis[] = $analysis;
                }
            }

            $response = [
                'bot_info' => $botInfo,
                'webhook_info' => $webhookInfo,
                'recent_updates' => $updates,
                'webhook_active' => !empty($webhookInfo['url']),
                'can_use_getupdates' => empty($webhookInfo['url']),
                'message_analysis' => $messageAnalysis,
                'total_messages_found' => count($messageAnalysis),
                'video_messages_found' => count(array_filter($messageAnalysis, function ($msg) {
                    return $msg['has_video'] ?? false;
                })),
                'debug_updates_structure' => [
                    'updates_isset' => isset($updates['result']),
                    'updates_is_array' => is_array($updates['result'] ?? null),
                    'updates_count' => count($updates['result'] ?? []),
                    'first_update_keys' => isset($updates['result'][0]) ? array_keys($updates['result'][0]) : []
                ]
            ];

            return response()->json(['success' => true, 'data' => $response]);
        } catch (Exception $e) {
            Log::error('Telegram connection test failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Admin: Reset getUpdates offset to try to get older messages
     */
    public function resetUpdatesOffset()
    {
        try {
            // Get the highest update_id we've seen
            $updates = Telegram::getUpdates(['limit' => 1, 'offset' => -1]);

            if (isset($updates['result']) && !empty($updates['result'])) {
                $lastUpdateId = $updates['result'][0]['update_id'];

                // Try to reset by getting updates with a much lower offset
                $resetUpdates = Telegram::getUpdates(['limit' => 100, 'offset' => max(0, $lastUpdateId - 200)]);

                Log::info('Reset updates offset', [
                    'last_update_id' => $lastUpdateId,
                    'reset_offset' => max(0, $lastUpdateId - 200),
                    'reset_result_count' => count($resetUpdates['result'] ?? [])
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Attempted to reset updates offset',
                    'last_update_id' => $lastUpdateId,
                    'reset_updates_count' => count($resetUpdates['result'] ?? [])
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'No updates found to reset from'
                ]);
            }
        } catch (Exception $e) {
            Log::error('Failed to reset updates offset: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Admin: Import videos directly using known file IDs from recent messages
     * Enhanced for large numbers of videos with batch processing
     */
    public function importKnownVideos()
    {
        $syncUserTelegramId = Setting::get('sync_user_telegram_id');

        if (!$syncUserTelegramId) {
            return response()->json([
                'success' => false,
                'error' => 'No sync user configured'
            ]);
        }

        try {
            $importedVideos = [];
            $existingVideos = [];
            $allMessagesFound = [];
            $totalProcessed = 0;
            $offset = 0;
            $batchSize = 100; // Telegram's max limit
            $maxIterations = 10; // Safety limit to prevent infinite loops
            $iterations = 0;

            Log::info('Starting batch video import', [
                'sync_user_id' => $syncUserTelegramId,
                'batch_size' => $batchSize,
                'max_iterations' => $maxIterations
            ]);

            do {
                $iterations++;
                Log::info("Processing batch {$iterations}", ['offset' => $offset]);

                // Get updates with offset to get different batches
                $updates = Telegram::getUpdates([
                    'limit' => $batchSize,
                    'offset' => $offset
                ]);

                $batchCount = 0;
                $lastUpdateId = null;

                // The SDK returns an array directly, not wrapped in 'result'
                if (is_array($updates) && count($updates) > 0) {
                    foreach ($updates as $update) {
                        // Access the actual data from the SDK object
                        $updateData = $update->toArray();
                        $lastUpdateId = $updateData['update_id'];
                        $batchCount++;

                        if (isset($updateData['message'])) {
                            $message = $updateData['message'];
                            $fromId = $message['from']['id'] ?? 'unknown';

                            // Log each message for debugging (only in first iteration to avoid spam)
                            if ($iterations === 1) {
                                $allMessagesFound[] = [
                                    'from_id' => $fromId,
                                    'sync_user_id' => $syncUserTelegramId,
                                    'id_match' => ($fromId == $syncUserTelegramId),
                                    'has_video' => isset($message['video']),
                                    'video_file_id' => $message['video']['file_id'] ?? null
                                ];
                            }

                            // Check if this message is from sync user AND has video
                            if (
                                isset($message['video']) &&
                                $fromId == $syncUserTelegramId
                            ) {
                                $video = $message['video'];
                                $fileId = $video['file_id'];

                                // Check if video already exists
                                $existingVideo = Video::where('telegram_file_id', $fileId)->first();

                                if (!$existingVideo) {
                                    // Import this video automatically
                                    $newVideo = Video::create([
                                        'title' => $message['caption'] ?? ('Video ' . date('Y-m-d H:i:s', $message['date'])),
                                        'description' => $message['caption'] ?? 'Imported from Telegram',
                                        'price' => 4.99, // Default price
                                        'telegram_file_id' => $fileId,
                                        'file_unique_id' => $video['file_unique_id'] ?? null,
                                        'file_size' => $video['file_size'] ?? null,
                                        'duration' => $video['duration'] ?? null,
                                        'width' => $video['width'] ?? null,
                                        'height' => $video['height'] ?? null,
                                        'telegram_message_data' => $message
                                    ]);

                                    Log::info('Imported new video', [
                                        'batch' => $iterations,
                                        'video_id' => $newVideo->id,
                                        'title' => $newVideo->title,
                                        'file_id' => $fileId
                                    ]);

                                    $importedVideos[] = [
                                        'id' => $newVideo->id,
                                        'title' => $newVideo->title,
                                        'file_id' => $fileId,
                                        'duration' => $video['duration'] ?? 0,
                                        'file_size_mb' => round(($video['file_size'] ?? 0) / 1024 / 1024, 2)
                                    ];
                                } else {
                                    $existingVideos[] = [
                                        'id' => $existingVideo->id,
                                        'title' => $existingVideo->title,
                                        'file_id' => $fileId
                                    ];
                                }
                            }
                        }
                    }

                    // Set offset for next batch (must be higher than the highest update_id we've seen)
                    if ($lastUpdateId !== null) {
                        $offset = $lastUpdateId + 1;
                    }

                    $totalProcessed += $batchCount;

                    Log::info("Batch {$iterations} complete", [
                        'batch_count' => $batchCount,
                        'total_processed' => $totalProcessed,
                        'last_update_id' => $lastUpdateId,
                        'next_offset' => $offset,
                        'imported_this_batch' => count($importedVideos),
                        'existing_this_batch' => count($existingVideos)
                    ]);
                } else {
                    Log::info("No more updates found in batch {$iterations}");
                    break; // No more updates
                }

                // Safety check to prevent infinite loops
                if ($iterations >= $maxIterations) {
                    Log::warning("Reached maximum iterations limit", ['max_iterations' => $maxIterations]);
                    break;
                }

                // Small delay between batches to be respectful to Telegram API
                usleep(100000); // 0.1 second

            } while ($batchCount > 0);

            $totalNew = count($importedVideos);
            $totalExisting = count($existingVideos);

            Log::info('Import completed', [
                'total_batches' => $iterations,
                'total_messages_processed' => $totalProcessed,
                'total_new' => $totalNew,
                'total_existing' => $totalExisting
            ]);

            if ($totalNew === 0 && $totalExisting === 0) {
                return response()->json([
                    'success' => false,
                    'error' => 'No videos found from sync user in recent updates',
                    'debug' => [
                        'sync_user_id' => $syncUserTelegramId,
                        'total_batches' => $iterations,
                        'total_messages_processed' => $totalProcessed,
                        'messages_analyzed' => $allMessagesFound
                    ]
                ]);
            }

            $message = "Import completed! ";
            if ($totalNew > 0) {
                $message .= "Imported {$totalNew} new videos. ";
            }
            if ($totalExisting > 0) {
                $message .= "Found {$totalExisting} videos that already exist. ";
            }
            $message .= "Processed {$totalProcessed} total messages in {$iterations} batches.";

            return response()->json([
                'success' => true,
                'message' => $message,
                'imported_videos' => $importedVideos,
                'existing_videos' => $existingVideos,
                'total_new' => $totalNew,
                'total_existing' => $totalExisting,
                'total_batches' => $iterations,
                'total_messages_processed' => $totalProcessed
            ]);
        } catch (\Exception $e) {
            Log::error('Import videos error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'error' => 'Error importing videos: ' . $e->getMessage()
            ]);
        }
    }

    // Remove the old complex conversation history method
    public function viewConversationHistory()
    {
        return response()->json([
            'success' => false,
            'error' => 'This method has been replaced with direct import'
        ]);
    }
}
