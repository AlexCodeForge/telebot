<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();

            // Stripe session information
            $table->string('stripe_session_id')->unique();
            $table->string('stripe_payment_intent_id')->nullable();
            $table->string('stripe_customer_id')->nullable();

            // Purchase details
            $table->unsignedBigInteger('video_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Nullable since Telegram users might not have accounts
            $table->decimal('amount', 10, 2); // Amount paid in dollars
            $table->string('currency', 3)->default('usd');

            // Customer information
            $table->string('customer_email')->nullable();
            $table->string('telegram_username');

            // Delivery tracking
            $table->enum('delivery_status', ['pending', 'delivered', 'failed', 'retrying'])->default('pending');
            $table->timestamp('delivered_at')->nullable();
            $table->text('delivery_notes')->nullable(); // For error messages or delivery confirmations
            $table->integer('delivery_attempts')->default(0);

            // Purchase status
            $table->enum('purchase_status', ['completed', 'refunded', 'disputed'])->default('completed');
            $table->timestamp('refunded_at')->nullable();

            // Additional metadata
            $table->json('stripe_metadata')->nullable(); // Store full Stripe session metadata
            $table->json('delivery_metadata')->nullable(); // Store Telegram message IDs, etc.

            $table->timestamps();

            // Foreign key constraints
            $table->foreign('video_id')->references('id')->on('videos')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance
            $table->index(['telegram_username', 'created_at']);
            $table->index(['delivery_status', 'created_at']);
            $table->index(['purchase_status', 'created_at']);
            $table->index('stripe_customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
