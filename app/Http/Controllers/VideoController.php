<?php

namespace App\Http\Controllers;

use App\Models\Video;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Laravel\Facades\Telegram;
use Exception;

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
     * Admin: Display captured videos that need pricing/management
     */
    public function capturedVideos()
    {
        $query = Video::whereNotNull('telegram_file_id');

        // Search functionality
        if (request('search')) {
            $search = request('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if (request('status') === 'ready') {
            $query->where('price', '>', 0);
        } elseif (request('status') === 'pending') {
            $query->where('price', '=', 0);
        }

        $videos = $query->latest()->paginate(20)->withQueryString();

        // Statistics
        $stats = [
            'total' => Video::whereNotNull('telegram_file_id')->count(),
            'ready' => Video::whereNotNull('telegram_file_id')->where('price', '>', 0)->count(),
            'pending' => Video::whereNotNull('telegram_file_id')->where('price', '=', 0)->count(),
        ];

        // Get current sync user setting
        $syncUserId = Setting::get('sync_user_id');
        $syncTelegramId = Setting::get('sync_telegram_id');
        $syncUser = null;
        $syncDisplayInfo = null;

        if ($syncUserId) {
            $syncUser = User::find($syncUserId);
            if ($syncUser) {
                $syncDisplayInfo = [
                    'name' => $syncUser->name,
                    'telegram_id' => $syncUser->telegram_user_id,
                    'type' => 'user'
                ];
            }
        } elseif ($syncTelegramId) {
            $syncDisplayInfo = [
                'name' => "Telegram User",
                'telegram_id' => $syncTelegramId,
                'type' => 'telegram_id'
            ];
        }

        // Get sync method preference (default to getUpdates)
        $syncMethod = Setting::get('sync_method', 'getupdates');

        return view('admin.videos.manage', compact('videos', 'stats', 'syncUser', 'syncDisplayInfo', 'syncMethod'));
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
     * Admin: Handle bulk actions (pricing, status)
     */
    public function bulkAction(Request $request)
    {
        $action = $request->input('action');
        $videoIds = $request->input('video_ids', []);

        if (empty($videoIds)) {
            return back()->with('error', 'No videos selected.');
        }

        switch ($action) {
            case 'set_price':
                $price = $request->input('bulk_price');
                if (!is_numeric($price) || $price < 0) {
                    return back()->with('error', 'Invalid price.');
                }

                $count = Video::whereIn('id', $videoIds)->update(['price' => $price]);
                return back()->with('success', "Price updated for {$count} video(s).");

            case 'delete':
                $count = Video::whereIn('id', $videoIds)->count();
                Video::whereIn('id', $videoIds)->delete();
                return back()->with('success', "Deleted {$count} video(s).");

            case 'make_free':
                $count = Video::whereIn('id', $videoIds)->update(['price' => 0]);
                return back()->with('success', "Made {$count} video(s) free.");

            default:
                return back()->with('error', 'Invalid action.');
        }
    }

    /**
     * Admin: Set sync user by Telegram ID
     */
    public function setSyncUser(Request $request)
    {
        $validated = $request->validate([
            'telegram_id' => 'required|numeric'
        ]);

        $telegramId = $validated['telegram_id'];

        // Try to find an existing user with this Telegram ID
        $user = User::where('telegram_user_id', $telegramId)->first();

        if ($user) {
            // User exists, store their user ID
            Setting::set('sync_user_id', $user->id, 'integer');
            return back()->with('success', "Sync user set to {$user->name} (Telegram ID: {$telegramId})");
        } else {
            // User doesn't exist, store the Telegram ID directly
            Setting::set('sync_telegram_id', $telegramId, 'integer');
            Setting::set('sync_user_id', null, 'integer'); // Clear the user_id
            return back()->with('success', "Sync Telegram ID set to {$telegramId}. User will be created when they interact with the bot.");
        }
    }

    /**
     * Admin: Set sync method preference
     */
    public function setSyncMethod(Request $request)
    {
        $validated = $request->validate([
            'sync_method' => 'required|in:getupdates,webhook'
        ]);

        $method = $validated['sync_method'];
        Setting::set('sync_method', $method, 'string');

        $message = $method === 'getupdates'
            ? 'Sync method set to getUpdates (polling). This works in all environments but only syncs recent messages.'
            : 'Sync method set to Webhook-based. This requires videos to be sent directly to the bot for automatic capture.';

        return back()->with('success', $message);
    }

    /**
     * Admin: Sync videos using the configured method
     */
    public function syncVideos()
    {
        $syncMethod = Setting::get('sync_method', 'getupdates');

        if ($syncMethod === 'webhook') {
            return $this->syncVideosFromWebhook();
        } else {
            return $this->syncVideosFromGetUpdates();
        }
    }

    /**
     * Sync videos using getUpdates method (default)
     */
    private function syncVideosFromGetUpdates()
    {
        $syncUserId = Setting::get('sync_user_id');
        $syncTelegramId = Setting::get('sync_telegram_id');

        $targetTelegramId = null;
        $syncUser = null;

        if ($syncUserId) {
            $syncUser = User::find($syncUserId);
            if ($syncUser && $syncUser->telegram_user_id) {
                $targetTelegramId = $syncUser->telegram_user_id;
            }
        } elseif ($syncTelegramId) {
            $targetTelegramId = $syncTelegramId;
        }

        if (!$targetTelegramId) {
            return back()->with('error', 'No sync Telegram ID configured. Please set a sync user first.');
        }

        try {
            // Temporarily remove webhook to use getUpdates
            $webhookUrl = null;
            try {
                $webhookInfo = Telegram::getWebhookInfo();
                $webhookUrl = $webhookInfo['url'] ?? null;

                if ($webhookUrl) {
                    Telegram::deleteWebhook();
                    Log::info('Temporarily removed webhook for sync: ' . $webhookUrl);
                }
            } catch (Exception $e) {
                Log::warning('Could not manage webhook: ' . $e->getMessage());
            }

            // Get updates using polling
            $updates = Telegram::getUpdates([
                'limit' => 100,
                'allowed_updates' => ['message']
            ]);

            $syncedCount = 0;
            $skippedCount = 0;

            if (!empty($updates['result'])) {
                foreach ($updates['result'] as $update) {
                    if (
                        isset($update['message']['video']) &&
                        isset($update['message']['from']['id']) &&
                        $update['message']['from']['id'] == $targetTelegramId
                    ) {

                        $videoData = $update['message']['video'];
                        $fileId = $videoData['file_id'];

                        // Check if video already exists
                        $existingVideo = Video::where('telegram_file_id', $fileId)->first();

                        if ($existingVideo) {
                            $skippedCount++;
                            continue;
                        }

                        // Create new video entry
                        Video::create([
                            'title' => 'Video #' . time() . '_' . $syncedCount,
                            'description' => $update['message']['caption'] ?? '',
                            'price' => 0, // Default to 0, admin will set price later
                            'telegram_file_id' => $fileId,
                            'file_size' => $videoData['file_size'] ?? null,
                            'duration' => $videoData['duration'] ?? null,
                            'width' => $videoData['width'] ?? null,
                            'height' => $videoData['height'] ?? null,
                            'file_unique_id' => $videoData['file_unique_id'] ?? null,
                            'video_type' => 'video',
                            'telegram_group_chat_id' => $update['message']['chat']['id'] ?? null,
                            'telegram_message_id' => $update['message']['message_id'] ?? null,
                            'telegram_message_data' => $update['message'] ?? null,
                            // Extract thumbnail data if available
                            'thumbnail_file_id' => $videoData['thumbnail']['file_id'] ?? null,
                            'thumbnail_width' => $videoData['thumbnail']['width'] ?? null,
                            'thumbnail_height' => $videoData['thumbnail']['height'] ?? null,
                        ]);

                        $syncedCount++;
                    }
                }
            }

            // Restore webhook if it was active
            if ($webhookUrl) {
                try {
                    Telegram::setWebhook(['url' => $webhookUrl]);
                    Log::info('Restored webhook: ' . $webhookUrl);
                } catch (Exception $e) {
                    Log::error('Failed to restore webhook: ' . $e->getMessage());
                }
            }

            $message = "Sync completed using getUpdates method! ";
            if ($syncedCount > 0) {
                $message .= "{$syncedCount} new videos added";
            } else {
                $message .= "No new videos found";
            }

            if ($skippedCount > 0) {
                $message .= ", {$skippedCount} videos skipped (already exist)";
            }

            // Additional note about limitations
            if ($syncedCount === 0 && $skippedCount === 0) {
                $message .= ". Note: This method only finds recent messages. For better results, consider switching to webhook method or have videos sent directly to the bot.";
            }

            return back()->with('success', $message);
        } catch (Exception $e) {
            // Make sure to restore webhook even if sync fails
            if (isset($webhookUrl) && $webhookUrl) {
                try {
                    Telegram::setWebhook(['url' => $webhookUrl]);
                } catch (Exception $webhookError) {
                    Log::error('Failed to restore webhook after sync error: ' . $webhookError->getMessage());
                }
            }

            Log::error('Video sync failed: ' . $e->getMessage());
            return back()->with('error', 'Failed to sync videos: ' . $e->getMessage());
        }
    }

    /**
     * Sync videos from webhook-captured data
     */
    private function syncVideosFromWebhook()
    {
        $syncUserId = Setting::get('sync_user_id');
        $syncTelegramId = Setting::get('sync_telegram_id');

        $targetTelegramId = null;

        if ($syncUserId) {
            $syncUser = User::find($syncUserId);
            if ($syncUser && $syncUser->telegram_user_id) {
                $targetTelegramId = $syncUser->telegram_user_id;
            }
        } elseif ($syncTelegramId) {
            $targetTelegramId = $syncTelegramId;
        }

        if (!$targetTelegramId) {
            return back()->with('error', 'No sync Telegram ID configured. Please set a sync user first.');
        }

        // In webhook mode, videos are automatically captured when sent to the bot
        // This method just shows status or can trigger a manual check
        $recentVideos = Video::where('created_at', '>=', now()->subDays(7))
            ->whereJsonContains('telegram_message_data->from->id', (int)$targetTelegramId)
            ->count();

        $totalVideos = Video::whereJsonContains('telegram_message_data->from->id', (int)$targetTelegramId)->count();

        $message = "Webhook sync mode is active. Found {$totalVideos} total videos from this user";
        if ($recentVideos > 0) {
            $message .= " ({$recentVideos} added in the last 7 days)";
        }
        $message .= ". Videos are automatically captured when sent to the bot.";

        return back()->with('success', $message);
    }

    /**
     * Admin: Send video to sync user
     */
    public function sendToSyncUser(Video $video)
    {
        $syncUserId = Setting::get('sync_user_id');
        $syncTelegramId = Setting::get('sync_telegram_id');

        $targetTelegramId = null;
        $displayName = 'sync user';

        if ($syncUserId) {
            $syncUser = User::find($syncUserId);
            if ($syncUser && $syncUser->telegram_user_id) {
                $targetTelegramId = $syncUser->telegram_user_id;
                $displayName = $syncUser->name;
            }
        } elseif ($syncTelegramId) {
            $targetTelegramId = $syncTelegramId;
            $displayName = "Telegram user {$syncTelegramId}";
        }

        if (!$targetTelegramId) {
            return back()->with('error', 'No sync user configured. Please set a sync user first.');
        }

        if (!$video->telegram_file_id) {
            return back()->with('error', 'No Telegram file ID available for this video.');
        }

        try {
            Telegram::sendVideo([
                'chat_id' => $targetTelegramId,
                'video' => $video->telegram_file_id,
                'caption' => "📹 **{$video->title}**\n" .
                    ($video->description ? "{$video->description}\n" : "") .
                    "💰 Price: \${$video->price}",
                'parse_mode' => 'Markdown'
            ]);

            return back()->with('success', "Video sent to {$displayName} successfully!");
        } catch (Exception $e) {
            Log::error('Failed to send video to sync user: ' . $e->getMessage());
            return back()->with('error', 'Failed to send video: ' . $e->getMessage());
        }
    }

    /**
     * Admin: Get video thumbnail
     */
    public function getVideoThumbnail(Video $video)
    {
        if (!$video->telegram_file_id) {
            return response()->json(['error' => 'No Telegram file ID available'], 404);
        }

        try {
            // Get file info from Telegram
            $fileInfo = Telegram::getFile(['file_id' => $video->telegram_file_id]);

            if (isset($fileInfo['file_path'])) {
                $botToken = config('telegram.bots.mybot.token');
                $thumbnailUrl = "https://api.telegram.org/file/bot{$botToken}/{$fileInfo['file_path']}";

                return response()->json([
                    'success' => true,
                    'thumbnail_url' => $thumbnailUrl,
                    'file_path' => $fileInfo['file_path']
                ]);
            }

            return response()->json(['error' => 'File path not available'], 404);
        } catch (Exception $e) {
            Log::error('Failed to get video thumbnail: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to get thumbnail'], 500);
        }
    }

    /**
     * Admin: Generate thumbnail for a video
     */
    public function generateThumbnail(Video $video)
    {
        if (!$video->telegram_file_id) {
            return back()->with('error', 'No Telegram file ID available for this video.');
        }

        try {
            $thumbnailUrl = $video->getThumbnailUrl();
            if ($thumbnailUrl) {
                return back()->with('success', 'Thumbnail generated successfully!');
            } else {
                return back()->with('warning', 'No thumbnail available for this video type.');
            }
        } catch (Exception $e) {
            Log::error('Failed to generate thumbnail: ' . $e->getMessage());
            return back()->with('error', 'Failed to generate thumbnail: ' . $e->getMessage());
        }
    }
}
