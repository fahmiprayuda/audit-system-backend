<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AuditProject;
use App\Models\Company;
use App\Models\User;

class AuditProjectSeeder extends Seeder
{
    public function run(): void
    {

        $user = User::first();

        $ffs = Company::where('code','FFS')->first();

        AuditProject::create([
            'project_code' => 'AUD-FFS-2026-001',
            'company_id' => $ffs->id,
            'project_name' => 'FFS - General Audit 2026',
            'release_date' => '2026-01-01',
            'status' => 'open',
            'created_by' => $user->id
        ]);
    }
}