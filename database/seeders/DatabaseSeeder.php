<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminEmail = strtolower(trim(env('ADMIN_EMAIL', 'admin@hysam.com')));
        $adminPassword = env('ADMIN_PASSWORD', 'admin123');

        // Seed Admin User
        User::updateOrCreate(
            ['id' => 'admin-user-1'],
            [
                'name' => 'Admin User',
                'email' => $adminEmail,
                'password' => Hash::make($adminPassword),
                'role' => 'admin',
                'disabled' => false,
                'permissions' => [
                    'create' => true,
                    'edit' => true,
                    'delete' => true,
                    'stockIn' => true,
                    'stockOut' => true
                ]
            ]
        );

        // Seed Staff User
        User::updateOrCreate(
            ['id' => 'staff-user-1'],
            [
                'name' => 'Staff User',
                'email' => 'staff@hysam.com',
                'password' => Hash::make('staff123'),
                'role' => 'staff',
                'disabled' => false,
                'permissions' => [
                    'create' => true,
                    'edit' => false,
                    'delete' => false,
                    'stockIn' => true,
                    'stockOut' => false
                ]
            ]
        );
    }
}
