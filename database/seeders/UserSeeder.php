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
                'name' => 'Manager Audit',
                'password' => Hash::make('manager123'),
                'role' => 'manager',
                'department_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'aulia@auditor.local'],
            [
                'name' => 'Nur Aulia Rahmawati',
                'password' => Hash::make('audit123'),
                'role' => 'auditor',
                'department_id' => null,
            ]
        );

        User::updateOrCreate(
            ['email' => 'shavazia@auditee.local'],
            [
                'name' => 'Shavazia',
                'password' => Hash::make('auditee123'),
                'role' => 'auditee',
                'department_id' => 3,
            ]
        );

        User::updateOrCreate(
            ['email' => 'ghaisani@auditee.local'],
            [
                'name' => 'Warehouse PIC',
                'password' => Hash::make('auditee321'),
                'role' => 'auditee',
                'department_id' => 1,
            ]
        );
    }
}