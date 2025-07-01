<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Telegram\Bot\Laravel\Facades\Telegram;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\Purchase;
use App\Models\Video;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class TelegramController extends Controller
{
    /**
     * Handle incoming webhook from Telegram
     */
    public function webhook(Request $request)
    {
        $update = Telegram::commandsHandler(true);

        // Handle regular messages (not commands)
        if (isset($update['message'])) {
            $message = $update['message'];
            $chatId = $message['chat']['id'];
            $text = $message['text'] ?? '';

            // Extract user information
            $telegramUserId = $message['from']['id'];
            $username = $message['from']['username'] ?? null;
            $firstName = $message['from']['first_name'] ?? 'User';

            // Update user info
            $this->updateUserInfo($telegramUserId, $username, $firstName);

            // Handle video messages - automatically capture them
            if (isset($message['video'])) {
                $this->handleVideoMessage($message, $chatId, $telegramUserId, $username, $firstName);
                return response('OK', 200);
            }

            // Handle commands
            if (str_starts_with($text, '/')) {
                $this->handleCommand($text, $chatId, $telegramUserId, $username, $firstName);
            }
        }

        return response('OK', 200);
    }

    /**
     * Handle video messages sent to the bot
     */
    private function handleVideoMessage($message, $chatId, $telegramUserId, $username, $firstName)
    {
        try {
            $video = $message['video'];
            $fileId = $video['file_id'];
            $fileName = $video['file_name'] ?? 'Unknown Video';
            $duration = $video['duration'] ?? 0;
            $fileSize = $video['file_size'] ?? 0;

            // Get caption if provided
            $caption = $message['caption'] ?? '';

            // Check if this video already exists
            $existingVideo = Video::where('telegram_file_id', $fileId)->first();

            if ($existingVideo) {
                $this->sendMessage($chatId, "✅ This video is already in the system!\n\n📹 *{$existingVideo->title}*\n💰 Price: \$" . number_format($existingVideo->price, 2) . "\n🆔 Video ID: {$existingVideo->id}", 'Markdown');
                return;
            }

            // Create new video entry
            $videoData = [
                'title' => $caption ?: $fileName,
                'description' => "Video uploaded via Telegram bot\nDuration: " . gmdate("H:i:s", $duration) . "\nFile size: " . $this->formatFileSize($fileSize) . "\nUploaded by: {$firstName}" . ($username ? " (@{$username})" : ""),
                'price' => 0.00, // Default price - admin will set later
                'telegram_file_id' => $fileId,
            ];

            $newVideo = Video::create($videoData);

            // Send confirmation to the user
            $message = "🎬 *Video Captured Successfully!*\n\n";
            $message .= "📹 *Title:* {$newVideo->title}\n";
            $message .= "🆔 *Video ID:* {$newVideo->id}\n";
            $message .= "⏱️ *Duration:* " . gmdate("H:i:s", $duration) . "\n";
            $message .= "📦 *Size:* " . $this->formatFileSize($fileSize) . "\n";
            $message .= "💰 *Price:* Free (Admin will set pricing)\n\n";
            $message .= "✅ The video has been added to the system and is ready for admin review!";

            $this->sendMessage($chatId, $message, 'Markdown');

            // Log the new video capture
            Log::info('New video captured via Telegram bot', [
                'video_id' => $newVideo->id,
                'telegram_file_id' => $fileId,
                'uploaded_by' => $username ?? $telegramUserId,
                'file_name' => $fileName,
                'duration' => $duration,
                'file_size' => $fileSize
            ]);
        } catch (Exception $e) {
            Log::error('Error handling video message', [
                'error' => $e->getMessage(),
                'message' => $message
            ]);

            $this->sendMessage($chatId, "❌ Sorry, there was an error processing your video. Please try again later.");
        }
    }

    /**
     * Format file size for display
     */
    private function formatFileSize($bytes)
    {
        if ($bytes == 0) return '0 B';

        $k = 1024;
        $sizes = ['B', 'KB', 'MB', 'GB'];
        $i = floor(log($bytes) / log($k));

        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }

    /**
     * Handle incoming messages
     */
    private function handleMessage(array $message)
    {
        // Log all messages for debugging
        Log::info('Telegram message received', $message);

        // Handle commands
        if (isset($message['text']) && strpos($message['text'], '/') === 0) {
            $this->handleCommand($message['text'], $message['chat']['id'], $message['from']['id'], $message['from']['username'] ?? null, $message['from']['first_name'] ?? 'User');
        }
    }

    /**
     * Handle bot commands with rate limiting
     */
    private function handleCommand($text, $chatId, $telegramUserId, $username, $firstName)
    {
        $parts = explode(' ', trim($text));
        $command = $parts[0];
        $args = array_slice($parts, 1);

        // Rate limiting: Check if user is sending too many commands
        $cacheKey = "telegram_command_rate_{$telegramUserId}";
        $commandCount = Cache::get($cacheKey, 0);

        if ($commandCount >= 10) { // Max 10 commands per minute
            $this->sendMessage($chatId, "⚠️ Too many commands. Please wait a moment before trying again.");

            Log::warning('Rate limit exceeded for Telegram user', [
                'telegram_user_id' => $telegramUserId,
                'username' => $username,
                'command' => $command,
                'chat_id' => $chatId,
            ]);
            return;
        }

        // Increment command counter
        Cache::put($cacheKey, $commandCount + 1, 60); // 1 minute TTL

        // Log all commands for security monitoring
        Log::info('Telegram command executed', [
            'command' => $command,
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'chat_id' => $chatId,
            'args_count' => count($args),
        ]);

        switch ($command) {
            case '/start':
                $this->handleStartCommand($chatId, $telegramUserId, $username, $firstName);
                break;

            case '/help':
                $this->handleHelpCommand($chatId);
                break;

            case '/mypurchases':
                $this->handleMyPurchasesCommand($chatId, $telegramUserId, $username);
                break;

            case '/getvideo':
                if (count($args) > 0) {
                    $this->handleGetVideoCommand($chatId, $telegramUserId, $username, $args[0]);
                } else {
                    $this->sendMessage($chatId, "❌ Please provide a video ID. Usage: /getvideo <id>\n\nUse /mypurchases to see your available videos.");
                }
                break;

            default:
                $this->sendMessage($chatId, "❓ Unknown command. Type /help to see available commands.");
        }
    }

    /**
     * SECURITY: Update user info to link Telegram User ID with username
     */
    private function updateUserInfo($telegramUserId, $username, $firstName)
    {
        if (!$telegramUserId) return;

        $user = User::where('telegram_user_id', $telegramUserId)->first();

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
        }
        // Don't create user here - only when they have purchases to link
    }

    /**
     * Handle /start command - SECURE: Only handles most recent pending purchase
     */
    private function handleStartCommand($chatId, $telegramUserId, $username, $firstName)
    {
        // Update user info first
        $this->updateUserInfo($telegramUserId, $username, $firstName);

        // Security: Only check for the MOST RECENT pending purchase
        $latestPendingPurchase = null;
        if ($username) {
            $latestPendingPurchase = Purchase::where('telegram_username', $username)
                ->where('verification_status', 'pending')
                ->where('purchase_status', 'completed')
                ->with('video')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        if ($latestPendingPurchase) {
            // Auto-verify and link the most recent purchase to telegram user ID
            try {
                $latestPendingPurchase->verifyTelegramUser($telegramUserId);

                // Immediately deliver the video
                $this->deliverVideoToUser($chatId, $latestPendingPurchase);

                Log::info('Latest purchase automatically verified via /start', [
                    'purchase_id' => $latestPendingPurchase->id,
                    'purchase_uuid' => $latestPendingPurchase->purchase_uuid,
                    'telegram_user_id' => $telegramUserId,
                    'telegram_username' => $username,
                ]);

                $message = "🎉 *Welcome to Video Store Bot!*\n\n";
                $message .= "✅ Your latest purchase has been verified and delivered!\n\n";
                $message .= "📹 *{$latestPendingPurchase->video->title}* (ID: {$latestPendingPurchase->video_id})\n";
                $message .= "💰 {$latestPendingPurchase->formatted_amount} - ✅ Delivered!\n\n";
                $message .= "🤖 *Available Commands:*\n";
                $message .= "/mypurchases - See all your videos\n";
                $message .= "/getvideo <id> - Get a specific video\n";
                $message .= "/help - Show this help\n\n";
                $message .= "💡 You now have unlimited access to your verified videos!\n\n";
                $message .= "⚠️ *Note:* If you have other pending purchases, each one will be verified when you make the purchase.";

                $this->sendMessage($chatId, $message, 'Markdown');
            } catch (\Exception $e) {
                Log::error('Failed to verify latest purchase via /start', [
                    'purchase_id' => $latestPendingPurchase->id,
                    'telegram_user_id' => $telegramUserId,
                    'telegram_username' => $username,
                    'error' => $e->getMessage(),
                ]);

                $message = "❌ *Verification Failed*\n\n";
                $message .= "There was an issue verifying your latest purchase. Please contact support.\n\n";
                $message .= "Purchase ID: {$latestPendingPurchase->purchase_uuid}";

                $this->sendMessage($chatId, $message, 'Markdown');
            }
        } else {
            // Check if user already has verified purchases
            $existingPurchases = Purchase::where('telegram_user_id', $telegramUserId)
                ->where('verification_status', 'verified')
                ->count();

            $message = "👋 Welcome to *Video Store Bot*, {$firstName}!\n\n";

            if ($existingPurchases > 0) {
                $message .= "🎬 You have {$existingPurchases} verified video(s) in your library!\n\n";
                $message .= "🤖 *Available Commands:*\n";
                $message .= "/mypurchases - See all your videos\n";
                $message .= "/getvideo <id> - Get a specific video\n";
                $message .= "/help - Show this help";
            } else {
                $message .= "🛒 *How to get videos:*\n";
                $message .= "1. Visit our store online\n";
                $message .= "2. Choose a video and enter your Telegram username: @{$username}\n";
                $message .= "3. Complete payment with Stripe\n";
                $message .= "4. Come back here and type /start to verify\n\n";
                $message .= "💡 Make sure to use the exact username: @{$username}";
            }

            $this->sendMessage($chatId, $message, 'Markdown');
        }
    }

    /**
     * Handle /help command
     */
    private function handleHelpCommand($chatId)
    {
        $message = "🤖 *Video Store Bot Help*\n\n";
        $message .= "*Available Commands:*\n";
        $message .= "/start - Welcome message and activate purchases\n";
        $message .= "/mypurchases - List all your purchased videos\n";
        $message .= "/getvideo <id> - Download a specific video by ID\n";
        $message .= "/help - Show this help message\n\n";
        $message .= "*How to Purchase:*\n";
        $message .= "1. Visit our online store\n";
        $message .= "2. Choose a video and enter your Telegram username\n";
        $message .= "3. Complete payment with Stripe\n";
        $message .= "4. Return here and type /start to activate\n\n";
        $message .= "*Need Support?*\n";
        $message .= "Contact us if you have any issues with your purchases or video delivery.";

        $this->sendMessage($chatId, $message, 'Markdown');
    }

    /**
     * Handle /getvideo command - SECURE VERSION WITH UUID
     */
    private function handleGetVideoCommand($chatId, $telegramUserId, $username, $videoId)
    {
        if (!$username) {
            $this->sendMessage($chatId, "❌ You need a Telegram username to use this bot. Please set one in your Telegram settings and try again.");
            return;
        }

        // Find the verified purchase
        $purchase = Purchase::where('telegram_user_id', $telegramUserId)
            ->where('video_id', $videoId)
            ->where('verification_status', 'verified')
            ->where('purchase_status', 'completed')
            ->with('video')
            ->first();

        if (!$purchase) {
            // Check if there's a pending purchase that needs verification
            $pendingPurchase = Purchase::where('telegram_username', $username)
                ->where('video_id', $videoId)
                ->where('verification_status', 'pending')
                ->where('purchase_status', 'completed')
                ->first();

            if ($pendingPurchase) {
                $this->sendMessage($chatId, "⏳ *Purchase Pending Verification*\n\nVideo #{$videoId} requires verification first.\n\nUse /start to verify your purchases and get access!", 'Markdown');
            } else {
                $this->sendMessage($chatId, "❌ *Access Denied*\n\nYou haven't purchased video #{$videoId} or it's not available.\n\nUse /mypurchases to see your available videos.", 'Markdown');
            }

            // Log access attempt
            Log::warning('Video access attempt - purchase not verified', [
                'telegram_user_id' => $telegramUserId,
                'username' => $username,
                'requested_video_id' => $videoId,
                'chat_id' => $chatId,
                'has_pending_purchase' => isset($pendingPurchase),
            ]);
            return;
        }

        // Deliver the video
        $this->deliverVideoToUser($chatId, $purchase);
    }

    /**
     * Handle /mypurchases command - SECURE VERSION: Limited results for security
     */
    private function handleMyPurchasesCommand($chatId, $telegramUserId, $username)
    {
        if (!$username) {
            $this->sendMessage($chatId, "❌ You need a Telegram username to use this bot. Please set one in your Telegram settings and try again.");
            return;
        }

        // Find verified purchases for this user (limit to most recent 10 for security)
        $purchases = Purchase::where('telegram_user_id', $telegramUserId)
            ->where('verification_status', 'verified')
            ->where('purchase_status', 'completed')
            ->with('video')
            ->orderBy('created_at', 'desc')
            ->limit(10) // Security: Limit to 10 most recent
            ->get();

        $totalPurchases = Purchase::where('telegram_user_id', $telegramUserId)
            ->where('verification_status', 'verified')
            ->where('purchase_status', 'completed')
            ->count();

        if ($purchases->isEmpty()) {
            // Check for pending purchases (only most recent one)
            $latestPendingPurchase = Purchase::where('telegram_username', $username)
                ->where('verification_status', 'pending')
                ->where('purchase_status', 'completed')
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestPendingPurchase) {
                $message = "⏳ *Pending Verification*\n\n";
                $message .= "I found your latest purchase waiting for verification.\n\n";
                $message .= "Use /start to verify and activate your purchase!";
            } else {
                $message = "📭 *No purchases found*\n\n";
                $message .= "You haven't purchased any videos yet.\n";
                $message .= "Visit our store to browse available videos!";
            }

            $this->sendMessage($chatId, $message, 'Markdown');
            return;
        }

        $message = "🎬 *Your Video Library*\n\n";
        $message .= "Showing " . $purchases->count() . " of {$totalPurchases} verified video(s):\n\n";

        foreach ($purchases as $purchase) {
            $video = $purchase->video;
            $deliveryStatus = $purchase->delivered_at ?
                "✅ Last delivered: " . $purchase->delivered_at->format('M j, Y') :
                "🆕 Ready to deliver";

            $message .= "📹 *{$video->title}*\n";
            $message .= "💰 {$purchase->formatted_amount}\n";
            $message .= "🆔 ID: {$video->id}\n";
            $message .= "📊 {$deliveryStatus}\n";
            $message .= "📥 Use: /getvideo {$video->id}\n\n";
        }

        if ($totalPurchases > 10) {
            $message .= "⚠️ *Note:* Only showing your 10 most recent videos.\n";
        }

        $message .= "💡 *Tip:* You have unlimited access to all your videos!";

        $this->sendMessage($chatId, $message, 'Markdown');

        // Log the command for security monitoring
        Log::info('User viewed their purchases', [
            'telegram_user_id' => $telegramUserId,
            'username' => $username,
            'total_purchases' => $totalPurchases,
            'shown_purchases' => $purchases->count(),
        ]);
    }

    private function deliverVideoToUser($chatId, $purchase)
    {
        $video = $purchase->video;

        // Try to deliver the video
        $delivered = false;
        $errorMessage = '';

        try {
            if ($video->telegram_file_id) {
                // Send video using file_id
                Telegram::sendVideo([
                    'chat_id' => $chatId,
                    'video' => $video->telegram_file_id,
                    'caption' => "🎬 *{$video->title}*\n\n" .
                        "📝 {$video->description}\n\n" .
                        "✅ Delivered successfully!\n" .
                        "💡 Use /getvideo {$video->id} anytime for unlimited access.",
                    'parse_mode' => 'Markdown'
                ]);
                $delivered = true;
            } else {
                $errorMessage = 'Video file not available for delivery';
            }
        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            Log::error('Video delivery failed', [
                'video_id' => $video->id,
                'purchase_id' => $purchase->id,
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
        }

        if ($delivered) {
            // Mark as delivered
            $purchase->markAsDelivered([
                'telegram_delivery' => true,
                'delivered_to_chat_id' => $chatId,
                'delivery_timestamp' => now()->toISOString(),
            ]);

            Log::info('Video delivered successfully', [
                'video_id' => $video->id,
                'purchase_id' => $purchase->id,
                'chat_id' => $chatId,
                'telegram_username' => $purchase->telegram_username
            ]);
        } else {
            $this->sendMessage($chatId, "❌ *Delivery Failed*\n\nSorry, there was an issue delivering your video. Please try again later or contact support.\n\nError: {$errorMessage}", 'Markdown');
        }
    }

    private function verifyUser($chatId, $telegramUserId, $username)
    {
        if (!$username) {
            $this->sendMessage($chatId, "❌ *Account Setup Required*\n\nPlease set a Telegram username in your Telegram settings to use this bot.", 'Markdown');
            return false;
        }

        // Check if user exists and is properly linked
        $user = User::where('telegram_user_id', $telegramUserId)
            ->where('telegram_username', $username)
            ->first();

        if (!$user) {
            // Check if there are purchases with this username that need linking
            $pendingPurchases = Purchase::where('telegram_username', $username)->exists();

            if ($pendingPurchases) {
                $this->sendMessage($chatId, "🔗 *Account Linking Required*\n\nI found purchases for @{$username} but your account isn't linked yet.\n\nPlease type /start to link your account and activate your purchases.", 'Markdown');
            } else {
                $this->sendMessage($chatId, "❌ *No Purchases Found*\n\nNo purchases found for @{$username}.\n\nVisit our store to purchase videos, then return here and type /start.", 'Markdown');
            }
            return false;
        }

        return true;
    }

    private function getOrCreateUserFromPurchases($telegramUserId, $username, $firstName, $purchases)
    {
        // Try to find existing user
        $user = User::where('telegram_user_id', $telegramUserId)->first();

        if (!$user && $username) {
            $user = User::where('telegram_username', $username)->first();
        }

        if (!$user) {
            // Create new user
            $user = User::create([
                'name' => $firstName,
                'telegram_user_id' => $telegramUserId,
                'telegram_username' => $username,
                'email' => $username . '@telegram.bot', // Placeholder email
                'password' => bcrypt('telegram_user_' . $telegramUserId), // Default password
            ]);
        } else {
            // Update existing user with new information
            $user->update([
                'telegram_user_id' => $telegramUserId,
                'telegram_username' => $username,
            ]);
        }

        return $user;
    }

    private function sendMessage($chatId, $text, $parseMode = null)
    {
        try {
            $params = [
                'chat_id' => $chatId,
                'text' => $text
            ];

            if ($parseMode) {
                $params['parse_mode'] = $parseMode;
            }

            Telegram::sendMessage($params);
        } catch (\Exception $e) {
            Log::error('Failed to send Telegram message', [
                'chat_id' => $chatId,
                'error' => $e->getMessage()
            ]);
        }
    }

    // Bot emulator for local testing
    public function botEmulator()
    {
        return view('bot-emulator');
    }

    public function handleBotEmulator(Request $request)
    {
        try {
            $command = $request->input('command');
            $telegramUserId = 5928450281; // Your Telegram user ID for testing
            $username = 'Salesmanp2p'; // Your username for testing
            $firstName = 'Sales';
            $chatId = $telegramUserId; // Use same ID for chat in emulator

            // Validate command input
            if (empty($command)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Command is required'
                ], 400);
            }

            // Update user info
            $this->updateUserInfo($telegramUserId, $username, $firstName);

            // Handle the command
            ob_start();
            $this->handleCommand($command, $chatId, $telegramUserId, $username, $firstName);
            $output = ob_get_clean();

            return response()->json([
                'success' => true,
                'command' => $command,
                'message' => 'Command processed - check Telegram for the response message',
                'debug_info' => [
                    'telegram_user_id' => $telegramUserId,
                    'username' => $username,
                    'chat_id' => $chatId,
                    'output' => $output
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Bot emulator error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'command' => $request->input('command')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Internal server error: ' . $e->getMessage(),
                'debug_trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    public function systemStatus()
    {
        $video = Video::find(11);
        $purchases = Purchase::latest()->take(3)->get();
        $users = User::whereNotNull('telegram_username')->latest()->take(3)->get();

        return response()->json([
            'test_video' => $video ? [
                'id' => $video->id,
                'title' => $video->title,
                'has_file_id' => !empty($video->telegram_file_id),
                'price' => $video->price
            ] : null,
            'recent_purchases' => $purchases->count(),
            'telegram_users' => $users->count(),
            'system_ready' => $video && !empty($video->telegram_file_id)
        ]);
    }
}
