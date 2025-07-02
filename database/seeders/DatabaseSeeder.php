<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Only seed the admin user - no videos needed
        $this->call([
            AdminUserSeeder::class,
        ]);
    }
}
