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
            // Replace the existing thumbnail_url with more comprehensive fields (removed ->after() to avoid PostgreSQL issues)
            if (Schema::hasColumn('videos', 'thumbnail_url')) {
                $table->dropColumn('thumbnail_url');
            }

            // Add new thumbnail fields
            if (!Schema::hasColumn('videos', 'thumbnail_path')) {
                $table->string('thumbnail_path')->nullable(); // Local uploaded thumbnail
            }
            if (!Schema::hasColumn('videos', 'thumbnail_url')) {
                $table->string('thumbnail_url')->nullable(); // External URL (if any)
            }
            if (!Schema::hasColumn('videos', 'show_blurred_thumbnail')) {
                $table->boolean('show_blurred_thumbnail')->default(true); // Show blurred version to customers
            }
            if (!Schema::hasColumn('videos', 'blur_intensity')) {
                $table->integer('blur_intensity')->default(10); // Blur intensity (1-20)
            }
            if (!Schema::hasColumn('videos', 'allow_preview')) {
                $table->boolean('allow_preview')->default(false); // Allow customers to see unblurred preview
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('videos', function (Blueprint $table) {
            $table->dropColumn([
                'thumbnail_path',
                'show_blurred_thumbnail',
                'blur_intensity',
                'allow_preview'
            ]);

            // Add back the original thumbnail_url
            $table->string('thumbnail_url')->nullable();
        });
    }
};
