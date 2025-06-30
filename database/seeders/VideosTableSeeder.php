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
        // Clear existing data
        DB::table('videos')->truncate();

        // Sample video data for testing
        $videos = [
            [
                'title' => 'Laravel Tutorial - Getting Started',
                'description' => 'A comprehensive beginner tutorial for Laravel framework covering installation, routing, and basic concepts.',
                'price' => 0.00, // Free video
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-Laravel-Tutorial',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Advanced PHP Patterns',
                'description' => 'Learn advanced PHP design patterns including Singleton, Factory, Observer, and more. Perfect for experienced developers.',
                'price' => 29.99,
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-PHP-Patterns',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Telegram Bot Development',
                'description' => 'Complete guide to building Telegram bots with PHP and Laravel. Includes webhook setup and advanced features.',
                'price' => 19.99,
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-Telegram-Bot',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Database Design Best Practices',
                'description' => 'Master database design principles, normalization, indexing strategies, and performance optimization techniques.',
                'price' => 24.99,
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-DB-Design',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'JavaScript ES6+ Features',
                'description' => 'Explore modern JavaScript features including arrow functions, destructuring, async/await, and modules.',
                'price' => 0.00, // Free video
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-JS-ES6',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'API Development with Laravel',
                'description' => 'Build robust RESTful APIs with Laravel. Covers authentication, rate limiting, versioning, and documentation.',
                'price' => 34.99,
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-API-Laravel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Vue.js Frontend Development',
                'description' => 'Complete Vue.js course covering components, state management with Vuex, routing, and integration with Laravel.',
                'price' => 39.99,
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-Vue-Frontend',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'DevOps for Laravel Apps',
                'description' => 'Deploy Laravel applications with Docker, configure CI/CD pipelines, and manage production environments.',
                'price' => 49.99,
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-DevOps-Laravel',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Testing in PHP',
                'description' => 'Comprehensive testing guide covering PHPUnit, feature tests, mocking, and test-driven development practices.',
                'price' => 27.99,
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-PHP-Testing',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => 'Security Best Practices',
                'description' => 'Essential security practices for web applications including OWASP Top 10, authentication, and data protection.',
                'price' => 0.00, // Free video
                'telegram_file_id' => 'BAADBAADrwADBREAAQ-Security-Guide',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        // Insert the sample data using the Video model
        foreach ($videos as $video) {
            Video::create($video);
        }

        $this->command->info('Successfully seeded ' . count($videos) . ' videos.');
    }
}
