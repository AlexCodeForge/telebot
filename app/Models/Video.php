<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Video extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'price',
        'telegram_file_id',
        'telegram_group_chat_id',
        'telegram_message_id',
        'telegram_message_data',
        'video_type',
        'file_unique_id',
        'thumbnail_file_id',
        'thumbnail_url',
        'thumbnail_width',
        'thumbnail_height',
        'file_size',
        'duration',
        'width',
        'height',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'telegram_message_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the formatted price with currency symbol.
     *
     * @return string
     */
    public function getFormattedPriceAttribute(): string
    {
        return '$' . number_format($this->price, 2);
    }

    /**
     * Scope a query to only include videos with a specific price range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param float $min
     * @param float $max
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePriceRange($query, $min, $max)
    {
        return $query->whereBetween('price', [$min, $max]);
    }

    /**
     * Scope a query to search videos by title or description.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $search
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', '%' . $search . '%')
                ->orWhere('description', 'like', '%' . $search . '%');
        });
    }

    /**
     * Check if the video is free.
     *
     * @return bool
     */
    public function isFree(): bool
    {
        return $this->price == 0;
    }

    /**
     * Check if video is available for forwarding from Telegram group.
     *
     * @return bool
     */
    public function canForwardFromGroup(): bool
    {
        return !empty($this->telegram_group_chat_id) && !empty($this->telegram_message_id);
    }

    /**
     * Get the video file type (video, document, animation, etc.)
     *
     * @return string
     */
    public function getVideoType(): string
    {
        return $this->video_type ?? 'file';
    }

    /**
     * Get Telegram message data for forwarding
     *
     * @return array|null
     */
    public function getTelegramMessageData(): ?array
    {
        return $this->telegram_message_data;
    }

    /**
     * Set video data from Telegram message
     *
     * @param array $messageData
     * @return self
     */
    public function setFromTelegramMessage(array $messageData): self
    {
        // Extract video information from the message
        $video = null;
        $fileId = null;
        $fileUniqueId = null;
        $videoType = 'file';

        // Check different message types
        if (isset($messageData['video'])) {
            $video = $messageData['video'];
            $fileId = $video['file_id'];
            $fileUniqueId = $video['file_unique_id'];
            $videoType = 'video';
        } elseif (isset($messageData['document'])) {
            $video = $messageData['document'];
            $fileId = $video['file_id'];
            $fileUniqueId = $video['file_unique_id'];
            $videoType = 'document';
        } elseif (isset($messageData['animation'])) {
            $video = $messageData['animation'];
            $fileId = $video['file_id'];
            $fileUniqueId = $video['file_unique_id'];
            $videoType = 'animation';
        }

        if ($video && $fileId) {
            $this->telegram_file_id = $fileId;
            $this->file_unique_id = $fileUniqueId;
            $this->video_type = $videoType;
            $this->telegram_group_chat_id = $messageData['chat']['id'];
            $this->telegram_message_id = $messageData['message_id'];
            $this->telegram_message_data = $messageData;
        }

        return $this;
    }

    /**
     * Get the thumbnail URL for this video
     *
     * @return string|null
     */
    public function getThumbnailUrl(): ?string
    {
        if ($this->thumbnail_url) {
            return $this->thumbnail_url;
        }

        if ($this->thumbnail_file_id) {
            try {
                // Try to get the file URL from Telegram
                $telegram = app('telegram.bot');
                $file = $telegram->getFile(['file_id' => $this->thumbnail_file_id]);

                if (isset($file['file_path'])) {
                    $baseUrl = config('telegram.bot_api_url', 'https://api.telegram.org');
                    $token = config('telegram.bot_token');
                    return "{$baseUrl}/file/bot{$token}/{$file['file_path']}";
                }
            } catch (\Exception $e) {
                // If we can't get the URL, return null
                return null;
            }
        }

        return null;
    }

    /**
     * Check if video has a thumbnail
     *
     * @return bool
     */
    public function hasThumbnail(): bool
    {
        return !empty($this->thumbnail_file_id) || !empty($this->thumbnail_url);
    }
}
