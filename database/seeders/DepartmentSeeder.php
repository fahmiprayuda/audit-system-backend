<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {

        Department::insert([

            ['name' => 'Finance & Accounting'],
            ['name' => 'HRGA'],
            ['name' => 'IT Dept'],
            ['name' => 'Manufacturing'],
            ['name' => 'Procurement'],
            ['name' => 'Supply Chain'],
            ['name' => 'Research & Development'],
            ['name' => 'Quality'],
            ['name' => 'Trade Marketing'],
            ['name' => 'Marketing'],
            ['name' => 'Operational Excellence']

        ]);

    }
}