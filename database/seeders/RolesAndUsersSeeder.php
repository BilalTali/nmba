<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesAndUsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        \App\Models\User::updateOrCreate(
            ['email' => 'admin@district.com'],
            [
                'name' => 'District Admin',
                'role' => 'admin',
                'password' => 'Welcome@123', // will be hashed via the User model mutator
            ]
        );

        \App\Models\User::updateOrCreate(
            ['email' => 'creator@district.com'],
            [
                'name' => 'Event Creator',
                'role' => 'block_worker',
                'block_id' => 14, // Assigning to 'Badgam' block for testing
                'password' => 'Welcome@123',
            ]
        );
    }
}
