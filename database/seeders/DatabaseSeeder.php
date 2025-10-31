<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()
            ->create([
                'name' => 'Administrator',
                'email' => 'admin@example.com',
                'password' => Hash::make('password'),
                'role' => Role::ADMIN,
                'status' => UserStatus::ACTIVE,
            ]);

        User::factory()
            ->create([
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => Hash::make('password'),
                'role' => Role::USER,
                'status' => UserStatus::ACTIVE,
            ]);

        User::factory()
            ->create([
                'name' => 'Moderator',
                'email' => 'moderator@example.com',
                'password' => Hash::make('password'),
                'role' => Role::MODERATOR,
                'status' => UserStatus::ACTIVE,
            ]);

        User::factory(4)
            ->state([
                'status' => UserStatus::ACTIVE,
            ])->create();

        User::factory(2)
            ->state([
                'status' => UserStatus::INACTIVE,
            ])->create();

        User::factory(2)
            ->state([
                'status' => UserStatus::SUSPENDED,
            ])->create();
    }
}
