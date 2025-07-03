<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add default settings for Vercel Blob store configuration
        Setting::set('vercel_blob_store_id', '');
        Setting::set('vercel_blob_base_url', '');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the Vercel Blob settings
        Setting::where('key', 'vercel_blob_store_id')->delete();
        Setting::where('key', 'vercel_blob_base_url')->delete();
    }
};
