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
        'file_size',
        'duration',
        'width',
        'height',
        'thumbnail_path',
        'thumbnail_url',
        'thumbnail_blob_url',
        'show_blurred_thumbnail',
        'blur_intensity',
        'allow_preview',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'telegram_message_data' => 'array',
        'show_blurred_thumbnail' => 'boolean',
        'allow_preview' => 'boolean',
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
     * Get the thumbnail URL (uploaded or external)
     *
     * @return string|null
     */
    public function getThumbnailUrl(): ?string
    {
        // Check for Vercel Blob URL first (cloud storage)
        if ($this->thumbnail_blob_url) {
            return $this->thumbnail_blob_url;
        }

        // Check for uploaded thumbnail (local storage fallback)
        if ($this->thumbnail_path) {
            return asset('storage/thumbnails/' . $this->thumbnail_path);
        }

        // Check for external thumbnail URL
        if ($this->thumbnail_url) {
            return $this->thumbnail_url;
        }

        return null;
    }

    /**
     * Get the blurred thumbnail CSS style
     *
     * @return string
     */
    public function getBlurredThumbnailStyle(): string
    {
        if (!$this->show_blurred_thumbnail) {
            return '';
        }

        return "filter: blur({$this->blur_intensity}px);";
    }

    /**
     * Check if video has a thumbnail (uploaded, external, or potentially from Telegram)
     *
     * @return bool
     */
    public function hasThumbnail(): bool
    {
        // Check for Vercel Blob thumbnail URL (primary)
        if (!empty($this->thumbnail_blob_url)) {
            return true;
        }

        // Check for uploaded thumbnail (local storage fallback)
        if (!empty($this->thumbnail_path)) {
            return true;
        }

        // Check for external thumbnail URL
        if (!empty($this->thumbnail_url)) {
            return true;
        }

        // For now, we'll consider videos without uploaded thumbnails as not having thumbnails
        // In the future, we could extract and cache Telegram thumbnails
        return false;
    }

    /**
     * Check if thumbnail should be shown blurred to customers
     *
     * @return bool
     */
    public function shouldShowBlurred(): bool
    {
        return $this->show_blurred_thumbnail && $this->hasThumbnail();
    }
}
