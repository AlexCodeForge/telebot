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
            // Add video metadata fields (removed ->after() clauses to avoid PostgreSQL transaction issues)
            if (!Schema::hasColumn('videos', 'file_size')) {
                $table->integer('file_size')->nullable();
            }
            if (!Schema::hasColumn('videos', 'duration')) {
                $table->integer('duration')->nullable();
            }
            if (!Schema::hasColumn('videos', 'width')) {
                $table->integer('width')->nullable();
            }
            if (!Schema::hasColumn('videos', 'height')) {
                $table->integer('height')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn(['file_size', 'duration', 'width', 'height']);
        });
    }
};
