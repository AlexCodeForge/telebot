<?php

namespace App\Http\Controllers;

use App\Models\Video;
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

        return view('admin.videos.manage', compact('videos', 'stats'));
    }

    /**
     * Admin: Update video (mainly for pricing)
     */
    public function update(Request $request, Video $video)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'price' => 'required|numeric|min:0|max:999999.99',
        ]);

        $video->update($validated);

        return back()->with('success', 'Video updated successfully!');
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
}
