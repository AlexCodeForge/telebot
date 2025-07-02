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
      // Add new fields for Telegram group forwarding (removed ->after() clauses to avoid order issues)
      if (!Schema::hasColumn('videos', 'telegram_group_chat_id')) {
        $table->string('telegram_group_chat_id')->nullable();
      }
      if (!Schema::hasColumn('videos', 'forwarded_to_group_at')) {
        $table->timestamp('forwarded_to_group_at')->nullable();
      }

      // Add new fields for Telegram group forwarding (removed ->after() clauses to avoid PostgreSQL transaction issues)
      if (!Schema::hasColumn('videos', 'telegram_message_id')) {
        $table->integer('telegram_message_id')->nullable();
      }
      if (!Schema::hasColumn('videos', 'telegram_message_data')) {
        $table->json('telegram_message_data')->nullable(); // Store full message object
      }
      if (!Schema::hasColumn('videos', 'video_type')) {
        $table->string('video_type')->default('file'); // 'file', 'video', 'document'
      }
      if (!Schema::hasColumn('videos', 'file_unique_id')) {
        $table->string('file_unique_id')->nullable(); // Telegram's unique file identifier
      }

      // Remove old file path fields if they exist (or make them nullable)
      // We'll keep telegram_file_id as it's still useful for direct file references

                  // Indexes already exist - commenting out to avoid duplicate error
      // $table->index(['telegram_group_chat_id', 'telegram_message_id']);
      // $table->index('file_unique_id');
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
        'file_unique_id',
        'forwarded_to_group_at'
      ]);

      $table->dropIndex(['telegram_group_chat_id', 'telegram_message_id']);
      $table->dropIndex(['file_unique_id']);
    });
  }
};
