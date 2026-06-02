<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@audit.local'],
            [
                'name' => 'Admin Auditor',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        User::updateOrCreate(
            ['email' => 'auditor@audit.local'],
            [
                'name' => 'Lead Auditor',
                'password' => Hash::make('password'),
                'role' => 'auditor',
            ]
        );

        User::updateOrCreate(
            ['email' => 'auditee@audit.local'],
            [
                'name' => 'Warehouse PIC',
                'password' => Hash::make('password'),
                'role' => 'auditee',
            ]
        );
    }
}