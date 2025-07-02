<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Video;
use Illuminate\Support\Facades\DB;

class VideosTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing videos
        Video::truncate();

        // Create sample videos with hardcoded data
        $videos = [
            [
                'title' => 'Free Sample Video',
                'description' => 'This is a free sample video for testing purposes.',
                'price' => 0.00,
                'telegram_file_id' => 'BAACAgIAAxkBAAICHmZhVxYAAY8yC8VYkEOUhFaYm8dHAAI7RwACqfxZS9rZOQz5tN8PNQQ',
                'file_unique_id' => 'AgADO0cAAqn8WUs',
                'duration' => '00:02:30',
                'file_size' => 1024000,
                'mime_type' => 'video/mp4',
                'width' => 1280,
                'height' => 720,
                'thumbnail_url' => 'https://via.placeholder.com/300x200/007bff/ffffff?text=Free+Video',
                'allow_preview' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Premium Video Content',
                'description' => 'High quality premium video content with exclusive material.',
                'price' => 9.99,
                'telegram_file_id' => 'BAACAgIAAxkBAAICIGZhVxYAAY8yC8VYkEOUhFaYm8dHAAI8RwACqfxZS9rZOQz5tN8PNQQ',
                'file_unique_id' => 'AgADPEcAAqn8WUs',
                'duration' => '00:15:45',
                'file_size' => 5120000,
                'mime_type' => 'video/mp4',
                'width' => 1920,
                'height' => 1080,
                'thumbnail_url' => 'https://via.placeholder.com/300x200/28a745/ffffff?text=Premium+Video',
                'allow_preview' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Tutorial Series Episode 1',
                'description' => 'Learn the basics in this comprehensive tutorial series.',
                'price' => 4.99,
                'telegram_file_id' => 'BAACAgIAAxkBAAICIWZhVxYAAY8yC8VYkEOUhFaYm8dHAAI9RwACqfxZS9rZOQz5tN8PNQQ',
                'file_unique_id' => 'AgADPUcAAqn8WUs',
                'duration' => '00:08:20',
                'file_size' => 2048000,
                'mime_type' => 'video/mp4',
                'width' => 1280,
                'height' => 720,
                'thumbnail_url' => 'https://via.placeholder.com/300x200/ffc107/000000?text=Tutorial+Video',
                'allow_preview' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ];

        foreach ($videos as $video) {
            Video::create($video);
        }

        $this->command->info('Sample videos seeded successfully!');
    }
}
