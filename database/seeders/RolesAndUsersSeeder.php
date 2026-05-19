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
                'role' => 'district_admin',
                'password' => bcrypt('password'),
            ]
        );

        \App\Models\User::updateOrCreate(
            ['email' => 'creator@district.com'],
            [
                'name' => 'Event Creator',
                'role' => 'event_creator',
                'password' => bcrypt('password'),
            ]
        );
    }
}
