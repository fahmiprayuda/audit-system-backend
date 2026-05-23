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
        $tps = Company::where('code','TPS')->first();
        $ptp = Company::where('code','PTP')->first();

        AuditProject::create([
            'project_code' => 'AUD-FFS-2026-001',
            'company_id' => $ffs->id,
            'project_name' => 'FFS - General Audit 2026',
            'start_date' => '2026-01-01',
            'status' => 'open',
            'created_by' => $user->id
        ]);

        AuditProject::create([
            'project_code' => 'AUD-TPS-2026-001',
            'company_id' => $tps->id,
            'project_name' => 'TPS - Procurement Audit 2026',
            'start_date' => '2026-02-01',
            'status' => 'open',
            'created_by' => $user->id
        ]);

        AuditProject::create([
            'project_code' => 'AUD-PTP-2026-001',
            'company_id' => $ptp->id,
            'project_name' => 'PTP - Operational Audit 2026',
            'start_date' => '2026-03-01',
            'status' => 'open',
            'created_by' => $user->id
        ]);

    }
}