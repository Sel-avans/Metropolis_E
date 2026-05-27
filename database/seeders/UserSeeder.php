<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's users.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator',
                'email' => 'admin@t.nl',
                'password' => Hash::make('test'),
                'role' => UserRole::Administrator->value,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'City Planner',
                'email' => 'planner@t.nl',
                'password' => Hash::make('test'),
                'role' => UserRole::City_planner->value,
                'email_verified_at' => now(),
            ],
            [
                'name' => 'Municipal Policy Maker',
                'email' => 'policy@t.nl',
                'password' => Hash::make('test'),
                'role' => UserRole::Municipal_Policy_Maker->value,
                'email_verified_at' => now(),
            ],
        ];

        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }
    }
}
