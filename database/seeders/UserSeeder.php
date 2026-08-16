<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            ['name' => 'Test User', 'email' => 'test@example.com'],
            ['name' => 'Alice Nguyen', 'email' => 'alice@possystem.test'],
            ['name' => 'Budi Santoso', 'email' => 'budi@possystem.test'],
            ['name' => 'Carla Reyes', 'email' => 'carla@possystem.test'],
            ['name' => 'Dinesh Patel', 'email' => 'dinesh@possystem.test'],
        ];

        foreach ($users as $user) {
            User::query()->firstOrCreate(
                ['email' => $user['email']],
                [...$user, 'password' => 'password'],
            );
        }

        User::factory()->count(45)->create();
    }
}
