<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing admin users
        User::where('email', 'admin@telebot.com')->delete();

        // Create admin user with hardcoded values
        User::create([
            'name' => 'Admin User',
            'email' => 'admin@telebot.com',
            'email_verified_at' => now(),
            'password' => Hash::make('admin123'),
            'is_admin' => true,
            'telegram_username' => null,
            'telegram_user_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@telebot.com');
        $this->command->info('Password: admin123');
    }
}
