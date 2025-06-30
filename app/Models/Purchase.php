<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Purchase extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'stripe_session_id',
        'stripe_payment_intent_id',
        'stripe_customer_id',
        'video_id',
        'user_id',
        'amount',
        'currency',
        'customer_email',
        'telegram_username',
        'delivery_status',
        'delivered_at',
        'delivery_notes',
        'delivery_attempts',
        'purchase_status',
        'refunded_at',
        'stripe_metadata',
        'delivery_metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'delivered_at' => 'datetime',
        'refunded_at' => 'datetime',
        'stripe_metadata' => 'json',
        'delivery_metadata' => 'json',
        'delivery_attempts' => 'integer',
    ];

    /**
     * Get the video that was purchased.
     */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /**
     * Get the user who made the purchase (if they have an account).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending deliveries.
     */
    public function scopePendingDelivery($query)
    {
        return $query->where('delivery_status', 'pending');
    }

    /**
     * Scope for failed deliveries.
     */
    public function scopeFailedDelivery($query)
    {
        return $query->where('delivery_status', 'failed');
    }

    /**
     * Scope for completed purchases.
     */
    public function scopeCompleted($query)
    {
        return $query->where('purchase_status', 'completed');
    }

    /**
     * Scope for purchases by Telegram username.
     */
    public function scopeByTelegramUser($query, $username)
    {
        return $query->where('telegram_username', ltrim($username, '@'));
    }

    /**
     * Check if the purchase has been delivered.
     */
    public function isDelivered(): bool
    {
        return $this->delivery_status === 'delivered';
    }

    /**
     * Check if delivery has failed.
     */
    public function hasDeliveryFailed(): bool
    {
        return $this->delivery_status === 'failed';
    }

    /**
     * Check if delivery is retrying.
     */
    public function isRetrying(): bool
    {
        return $this->delivery_status === 'retrying';
    }

    /**
     * Mark delivery as successful.
     */
    public function markAsDelivered(array $deliveryMetadata = []): void
    {
        $this->update([
            'delivery_status' => 'delivered',
            'delivered_at' => now(),
            'delivery_metadata' => array_merge($this->delivery_metadata ?? [], $deliveryMetadata),
        ]);
    }

    /**
     * Mark delivery as failed.
     */
    public function markAsDeliveryFailed(string $reason = null): void
    {
        $this->update([
            'delivery_status' => 'failed',
            'delivery_notes' => $reason,
            'delivery_attempts' => $this->delivery_attempts + 1,
        ]);
    }

    /**
     * Set delivery status to retrying.
     */
    public function markAsRetrying(string $reason = null): void
    {
        $this->update([
            'delivery_status' => 'retrying',
            'delivery_notes' => $reason,
            'delivery_attempts' => $this->delivery_attempts + 1,
        ]);
    }

    /**
     * Get formatted amount.
     */
    public function getFormattedAmountAttribute(): string
    {
        return '$' . number_format($this->amount, 2);
    }

    /**
     * Get clean Telegram username (without @).
     */
    public function getCleanUsernameAttribute(): string
    {
        return ltrim($this->telegram_username, '@');
    }

    /**
     * Check if purchase can be retried for delivery.
     */
    public function canRetryDelivery(): bool
    {
        return $this->delivery_attempts < 3 &&
            in_array($this->delivery_status, ['pending', 'failed', 'retrying']);
    }
}
