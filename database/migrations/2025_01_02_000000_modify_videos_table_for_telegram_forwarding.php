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
    Schema::table('videos', function (Blueprint $table) {
      // Add new fields for Telegram group forwarding
      $table->string('telegram_group_chat_id')->nullable()->after('telegram_file_id');
      $table->integer('telegram_message_id')->nullable()->after('telegram_group_chat_id');
      $table->json('telegram_message_data')->nullable()->after('telegram_message_id'); // Store full message object
      $table->string('video_type')->default('file')->after('telegram_message_data'); // 'file', 'video', 'document'
      $table->string('file_unique_id')->nullable()->after('video_type'); // Telegram's unique file identifier

      // Remove old file path fields if they exist (or make them nullable)
      // We'll keep telegram_file_id as it's still useful for direct file references

      // Add indexes for better performance
      $table->index(['telegram_group_chat_id', 'telegram_message_id']);
      $table->index('file_unique_id');
    });
  }

  /**
   * Reverse the migrations.
   */
  public function down(): void
  {
    Schema::table('videos', function (Blueprint $table) {
      $table->dropColumn([
        'telegram_group_chat_id',
        'telegram_message_id',
        'telegram_message_data',
        'video_type',
        'file_unique_id'
      ]);

      $table->dropIndex(['telegram_group_chat_id', 'telegram_message_id']);
      $table->dropIndex(['file_unique_id']);
    });
  }
};
