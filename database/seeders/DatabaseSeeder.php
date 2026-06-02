<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DepartmentSeeder::class,
            CompanySeeder::class,
            UserSeeder::class,
            AuditProjectSeeder::class,
            FindingSeeder::class,
            ActionPlanSeeder::class,
            EvidenceSeeder::class,
        ]);
    }
}