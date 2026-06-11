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
            ['email' => 'fahmi@manager.local'],
            [
                'name' => 'Fahmi',
                'password' => Hash::make('fahmi123'),
                'role' => 'manager',
                'department_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'aulia@auditor.local'],
            [
                'name' => 'Aulia',
                'password' => Hash::make('aulia123'),
                'role' => 'auditor',
                'department_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'shavazia@auditee.local'],
            [
                'name' => 'Shavazia',
                'password' => Hash::make('shavazia123'),
                'role' => 'auditee',
                'department_id' => 3,
            ]
        );

        User::updateOrCreate(
            ['email' => 'ghaisani@auditee.local'],
            [
                'name' => 'Ghaisani',
                'password' => Hash::make('ghaisani123'),
                'role' => 'auditee',
                'department_id' => 1,
            ]
        );
    }
}