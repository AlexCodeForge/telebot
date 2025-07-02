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
        Schema::create('telegram_bots', function (Blueprint $table) {
            $table->id();
            $table->string('token')->unique(); // Bot token
            $table->bigInteger('bot_id')->unique(); // Telegram bot ID
            $table->string('username'); // Bot username (without @)
            $table->string('first_name'); // Bot display name
            $table->text('description')->nullable(); // Bot description
            $table->boolean('is_active')->default(true); // Is this bot active
            $table->timestamp('fetched_at'); // When bot info was last fetched
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telegram_bots');
    }
};
