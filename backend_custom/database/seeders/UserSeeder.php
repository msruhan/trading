<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create admin user
        User::create([
            'name' => 'Admin',
            'email' => 'admin@tradingjournal.local',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'email_verified_at' => now(),
            'timezone' => 'Asia/Jakarta',
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ]);

        // Create demo user
        User::create([
            'name' => 'Demo User',
            'email' => 'demo@tradingjournal.local',
            'password' => Hash::make('password'),
            'role' => 'user',
            'email_verified_at' => now(),
            'timezone' => 'Asia/Jakarta',
            'settings' => [
                'theme' => 'dark',
                'notifications' => true,
            ],
        ]);

        $this->command->info('Users seeded successfully!');
    }
}

