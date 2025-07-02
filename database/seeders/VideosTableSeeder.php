<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class VideosTableSeeder extends Seeder
{
    public function run()
    {
        // No video seeding needed - admin will upload videos via admin panel
        $this->command->info('Video seeding skipped - use admin panel to upload videos');
    }
}
