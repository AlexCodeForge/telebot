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
            // Replace the existing thumbnail_url with more comprehensive fields
            $table->dropColumn('thumbnail_url');

            // Add new thumbnail fields
            $table->string('thumbnail_path')->nullable()->after('height'); // Local uploaded thumbnail
            $table->string('thumbnail_url')->nullable()->after('thumbnail_path'); // External URL (if any)
            $table->boolean('show_blurred_thumbnail')->default(true)->after('thumbnail_url'); // Show blurred version to customers
            $table->integer('blur_intensity')->default(10)->after('show_blurred_thumbnail'); // Blur intensity (1-20)
            $table->boolean('allow_preview')->default(false)->after('blur_intensity'); // Allow customers to see unblurred preview
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
            $table->string('thumbnail_url')->nullable()->after('height');
        });
    }
};
