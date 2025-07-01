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
        Schema::table('purchases', function (Blueprint $table) {
            // Add UUID for secure purchase identification
            $table->uuid('purchase_uuid')->after('id')->unique();

            // Add telegram user ID for linking purchases to telegram users
            $table->string('telegram_user_id')->nullable()->after('telegram_username');

            // Add purchase verification status
            $table->enum('verification_status', ['pending', 'verified', 'invalid'])->default('pending')->after('purchase_status');

            // Add indexes for performance
            $table->index('purchase_uuid');
            $table->index('telegram_user_id');
            $table->index(['telegram_username', 'verification_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex(['purchases_purchase_uuid_index']);
            $table->dropIndex(['purchases_telegram_user_id_index']);
            $table->dropIndex(['purchases_telegram_username_verification_status_index']);

            $table->dropColumn('purchase_uuid');
            $table->dropColumn('telegram_user_id');
            $table->dropColumn('verification_status');
        });
    }
};
