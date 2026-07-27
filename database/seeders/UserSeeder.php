<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $fks = Company::where(
            "code",
            "FFS"
        )->first();

        $it = $fks
            ->departments()
            ->where("code", "IT")
            ->first();

        $finance = $fks
            ->departments()
            ->where("code", "FIN")
            ->first();
    
        User::updateOrCreate(
            [
                "email"=>"fahmi@manager.local"
            ],
            [
                'name' => 'Fahmi Prayuda',
                'password' => Hash::make('fahmi123'),
                'role' => 'manager',
                "department_id"=> null,
            ]
        );

        User::updateOrCreate(
            [
                "email"=>"aulia@auditor.local"
            ],
            [
                'name' => 'Aulia',
                'password' => Hash::make('aulia123'),
                'role' => 'auditor',
                "department_id"=> null,
            ]
        );        

        User::updateOrCreate(
            [
                "email"=>"shavazia@auditee.local"
            ],
            [
                'name' => 'Shavazia',
                'password' => Hash::make('shavazia123'),
                'role' => 'auditee',
                "department_id"=> $it->id,
            ]
        );
    }
    
}  