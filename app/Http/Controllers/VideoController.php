<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Exception;

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
    public function capturedVideos()
    {
        $videos = Video::latest()->get();
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

        return back()->with('success', 'Video updated successfully!');
    }

    /**
     * Admin: Delete a video.
     */
    public function destroy(Video $video)
    {
        $video->delete();
        return back()->with('success', 'Video deleted successfully!');
    }

    /**
     * Admin: Test video by sending to sync user.
     */
    public function testVideo(Video $video)
    {
        $syncUserTelegramId = Setting::get('sync_user_telegram_id');
        $syncUserName = Setting::get('sync_user_name');

        if (!$syncUserTelegramId) {
            return back()->with('error', 'No sync user configured.');
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
     * Admin: Set sync user for testing.
     */
    public function setSyncUser(Request $request)
    {
        $request->validate([
            'telegram_id' => 'required|string',
            'name' => 'required|string|max:255',
        ]);

        Setting::set('sync_user_telegram_id', $request->telegram_id);
        Setting::set('sync_user_name', $request->name);

        return back()->with('success', 'Sync user configured successfully!');
    }

    /**
     * Admin: Remove sync user.
     */
    public function removeSyncUser()
    {
        Setting::forget('sync_user_telegram_id');
        Setting::forget('sync_user_name');

        return back()->with('success', 'Sync user removed successfully!');
    }

    /**
     * Admin: Deactivate webhook to allow getUpdates.
     */
    public function deactivateWebhook()
    {
        try {
            $result = Telegram::removeWebhook();
            Log::info('Webhook deactivated', ['result' => $result]);
            return response()->json(['success' => true, 'message' => 'Webhook deactivated successfully']);
        } catch (Exception $e) {
            Log::error('Failed to deactivate webhook: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
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
            return response()->json(['success' => true, 'message' => 'Webhook reactivated successfully']);
        } catch (Exception $e) {
            Log::error('Failed to reactivate webhook: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Admin: Get webhook status.
     */
    public function getWebhookStatus()
    {
        try {
            $webhookInfo = Telegram::getWebhookInfo();
            return response()->json(['success' => true, 'webhook_info' => $webhookInfo]);
        } catch (Exception $e) {
            Log::error('Failed to get webhook status: ' . $e->getMessage());
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Admin: Test Telegram API connection and get file IDs from conversation.
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

            $response = [
                'bot_info' => $botInfo,
                'webhook_info' => $webhookInfo,
                'webhook_active' => !empty($webhookInfo['url']),
                'can_use_getupdates' => empty($webhookInfo['url']),
            ];

            // Only try getUpdates if webhook is not active
            if (empty($webhookInfo['url'])) {
                $updates = Telegram::getUpdates(['limit' => 20]);
                Log::info('GetUpdates test', ['updates' => $updates]);

                // Process and analyze messages for file IDs
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
                            $analysis['from_id'] = $message['from']['id'] ?? 'unknown';
                            $analysis['from_username'] = $message['from']['username'] ?? 'no username';
                            $analysis['from_first_name'] = $message['from']['first_name'] ?? 'no name';
                            $analysis['has_video'] = isset($message['video']);
                            $analysis['has_document'] = isset($message['document']);
                            $analysis['text'] = $message['text'] ?? null;
                            $analysis['caption'] = $message['caption'] ?? null;
                            $analysis['date'] = date('Y-m-d H:i:s', $message['date'] ?? 0);

                            if (isset($message['video'])) {
                                $analysis['video_file_id'] = $message['video']['file_id'] ?? 'unknown';
                                $analysis['video_duration'] = $message['video']['duration'] ?? 'unknown';
                                $analysis['video_file_size'] = $message['video']['file_size'] ?? 'unknown';
                            }

                            if (isset($message['document']) && str_contains($message['document']['mime_type'] ?? '', 'video')) {
                                $analysis['document_file_id'] = $message['document']['file_id'] ?? 'unknown';
                                $analysis['document_mime_type'] = $message['document']['mime_type'] ?? 'unknown';
                            }
                        }

                        $messageAnalysis[] = $analysis;
                    }
                }

                $response['recent_updates'] = $updates;
                $response['message_analysis'] = $messageAnalysis;
                $response['total_messages_found'] = count($messageAnalysis);
                $response['video_messages_found'] = count(array_filter($messageAnalysis, function ($msg) {
                    return $msg['has_video'] ?? false;
                }));
            } else {
                $response['message'] = 'Webhook is active - cannot use getUpdates. Deactivate webhook first to see conversation history.';
            }

            return response()->json(['success' => true, 'data' => $response]);
        } catch (Exception $e) {
            Log::error('Telegram connection test failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Manual import using file ID from conversation.
     */
    public function manualImportVideo(Request $request)
    {
        $fileId = $request->input('file_id');
        $title = $request->input('title', 'Manual Import Video');
        $price = $request->input('price', 4.99);

        if (!$fileId) {
            return response()->json(['success' => false, 'error' => 'File ID is required']);
        }

        try {
            // Check if video already exists
            $existingVideo = Video::where('telegram_file_id', $fileId)->first();

            if ($existingVideo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Video already exists',
                    'existing_video' => $existingVideo
                ]);
            }

            // Create new video
            $newVideo = Video::create([
                'title' => $title,
                'description' => 'Manually imported video',
                'price' => $price,
                'telegram_file_id' => $fileId,
            ]);

            Log::info('Manual video import successful', [
                'video_id' => $newVideo->id,
                'file_id' => $fileId,
                'title' => $title
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Video imported successfully!',
                'video' => $newVideo
            ]);
        } catch (\Exception $e) {
            Log::error('Manual import failed', ['error' => $e->getMessage(), 'file_id' => $fileId]);
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Admin: Clear all videos from database.
     */
    public function clearAllVideos()
    {
        try {
            $count = Video::count();
            Video::truncate();

            Log::info('All videos cleared from database', ['videos_deleted' => $count]);

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$count} videos from database."
            ]);
        } catch (Exception $e) {
            Log::error('Failed to clear videos', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
