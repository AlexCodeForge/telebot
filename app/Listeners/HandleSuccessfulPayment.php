<?php

namespace App\Listeners;

use App\Models\Purchase;
use App\Models\Video;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Events\WebhookReceived;
use Laravel\Cashier\Cashier;
use Telegram\Bot\Laravel\Facades\Telegram;

class HandleSuccessfulPayment
{
    /**
     * Handle the webhook received event
     */
    public function handle(WebhookReceived $event): void
    {
        if ($event->payload['type'] === 'checkout.session.completed') {
            $session = $event->payload['data']['object'];

            Log::info('Processing successful payment', [
                'session_id' => $session['id'],
                'amount' => $session['amount_total'],
                'metadata' => $session['metadata'] ?? []
            ]);

            // Extract metadata
            $videoId = $session['metadata']['video_id'] ?? null;
            $telegramUsername = $session['metadata']['telegram_username'] ?? null;

            if (!$videoId || !$telegramUsername) {
                Log::error('Missing required metadata in payment session', [
                    'session_id' => $session['id'],
                    'video_id' => $videoId,
                    'telegram_username' => $telegramUsername
                ]);
                return;
            }

            // Get video
            $video = Video::find($videoId);
            if (!$video) {
                Log::error('Video not found for payment', ['video_id' => $videoId]);
                return;
            }

            // Create or get user (username only for now)
            $user = $this->getOrCreateUser($telegramUsername);

            // Create purchase record
            $purchase = Purchase::create([
                'user_id' => $user->id,
                'video_id' => $video->id,
                'amount' => $session['amount_total'],
                'currency' => $session['currency'],
                'purchase_status' => 'completed',
                'stripe_session_id' => $session['id'],
                'telegram_username' => $telegramUsername,
            ]);

            Log::info('Purchase record created', [
                'purchase_id' => $purchase->id,
                'video_id' => $video->id,
                'telegram_username' => $telegramUsername
            ]);

            // Send activation message instead of delivering video
            $this->sendActivationMessage($telegramUsername, $video);
        }
    }

    private function getOrCreateUser($telegramUsername)
    {
        // First try to find by username
        $user = User::where('telegram_username', $telegramUsername)->first();

        if ($user) {
            return $user;
        }

        // Create new user with minimal info (Telegram User ID will be linked later)
        return User::create([
            'name' => $telegramUsername,
            'email' => $telegramUsername . '@telegram.placeholder',
            'telegram_username' => $telegramUsername,
            // telegram_user_id will be set when user interacts with bot
        ]);
    }

    private function sendActivationMessage($telegramUsername, $video)
    {
        try {
            // Try to send message to username (this might not work if user hasn't started bot)
            $message = "🎉 *Payment Successful!*\n\n";
            $message .= "✅ Your purchase of *{$video->title}* has been confirmed!\n\n";
            $message .= "🤖 *Next Steps:*\n";
            $message .= "1. Start a chat with me: @videotestpowerbot\n";
            $message .= "2. Type /start to activate your purchase\n";
            $message .= "3. I'll deliver your video and set up unlimited access!\n\n";
            $message .= "💡 After activation, use /getvideo {$video->id} anytime to get your video.";

            // Note: This might fail if user hasn't started the bot yet, which is expected
            // The real activation happens when user types /start in the bot
            Telegram::sendMessage([
                'chat_id' => '@' . $telegramUsername,
                'text' => $message,
                'parse_mode' => 'Markdown'
            ]);

            Log::info('Activation message sent', [
                'telegram_username' => $telegramUsername,
                'video_id' => $video->id
            ]);
        } catch (\Exception $e) {
            // This is expected - most users won't have started the bot yet
            Log::info('Could not send activation message (expected)', [
                'telegram_username' => $telegramUsername,
                'error' => $e->getMessage(),
                'note' => 'User needs to start bot first'
            ]);
        }
    }

    /**
     * Deliver video to customer via Telegram
     */
    public function deliverVideoToTelegram(Purchase $purchase): void
    {
        try {
            $purchase->markAsRetrying();

            $video = $purchase->video;
            $telegramUsername = $purchase->telegram_username;

            Log::info('Sending purchase confirmation (no auto-delivery)', [
                'purchase_id' => $purchase->id,
                'video_id' => $video->id,
                'telegram_username' => $telegramUsername,
                'attempt' => $purchase->delivery_attempts + 1
            ]);

            // Clean username (remove @ if present)
            $username = ltrim($telegramUsername, '@');

            // Determine chat_id to use
            $chatId = '@' . $username; // Default to username

            // Special case for known users (you can expand this as needed)
            if (strtolower($username) === 'salesmanp2p') {
                $chatId = '5928450281'; // Your specific chat ID
            }

            // Send purchase confirmation with bot instructions (NO VIDEO DELIVERY)
            $confirmationMessage = "✅ *Payment Confirmed!*\n\n";
            $confirmationMessage .= "🎥 **Video:** {$video->title}\n";
            if ($video->description) {
                $confirmationMessage .= "📝 **Description:** {$video->description}\n";
            }
            $confirmationMessage .= "💰 **Amount:** $" . number_format($purchase->amount, 2) . "\n";
            $confirmationMessage .= "🆔 **Video ID:** {$video->id}\n\n";

            $confirmationMessage .= "🤖 **How to access your video:**\n";
            $confirmationMessage .= "1. Start a chat with our bot: @videotestpowerbot\n";
            $confirmationMessage .= "2. Use command: `/getvideo {$video->id}`\n";
            $confirmationMessage .= "3. Enjoy unlimited access to your video!\n\n";

            $confirmationMessage .= "📋 Use `/mypurchases` to see all your videos\n";
            $confirmationMessage .= "❓ Use `/help` for assistance";

            $response = Telegram::sendMessage([
                'chat_id' => $chatId,
                'text' => $confirmationMessage,
                'parse_mode' => 'Markdown'
            ]);

            // Mark as delivered (notification sent)
            $purchase->markAsDelivered();

            Log::info('Purchase confirmation sent successfully', [
                'purchase_id' => $purchase->id,
                'video_id' => $video->id,
                'telegram_username' => $telegramUsername,
                'message_id' => $response['message_id'] ?? null
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send purchase confirmation', [
                'purchase_id' => $purchase->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $purchase->markAsDeliveryFailed($e->getMessage());
        }
    }

    /**
     * Send video using Telegram file_id (fallback method)
     */
    private function sendVideoByFileId(Video $video, string $chatId, string $caption)
    {
        $videoType = $video->getVideoType();

        switch ($videoType) {
            case 'video':
                return Telegram::sendVideo([
                    'chat_id' => $chatId,
                    'video' => $video->telegram_file_id,
                    'caption' => $caption . "Here's your video! Thank you for your purchase! 🎬",
                    'parse_mode' => 'Markdown'
                ]);

            case 'document':
                return Telegram::sendDocument([
                    'chat_id' => $chatId,
                    'document' => $video->telegram_file_id,
                    'caption' => $caption . "Here's your video file! Thank you for your purchase! 🎬",
                    'parse_mode' => 'Markdown'
                ]);

            case 'animation':
                return Telegram::sendAnimation([
                    'chat_id' => $chatId,
                    'animation' => $video->telegram_file_id,
                    'caption' => $caption . "Here's your video! Thank you for your purchase! 🎬",
                    'parse_mode' => 'Markdown'
                ]);

            default:
                // Fallback to video
                return Telegram::sendVideo([
                    'chat_id' => $chatId,
                    'video' => $video->telegram_file_id,
                    'caption' => $caption . "Here's your video! Thank you for your purchase! 🎬",
                    'parse_mode' => 'Markdown'
                ]);
        }
    }
}
