<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
{

    User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    $this->call([
        DepartmentSeeder::class,
        CompanySeeder::class,
        AuditProjectSeeder::class,
        FindingSeeder::class,
        ActionPlanSeeder::class,
        EvidenceSeeder::class
    ]);

    }
}