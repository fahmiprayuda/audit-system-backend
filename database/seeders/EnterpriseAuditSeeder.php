<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Department;
use App\Models\User;
use App\Models\AuditProject;
use App\Models\Finding;
use App\Models\FindingDepartment;
use App\Models\ActionPlan;
use App\Models\ActionPlanExtension;
use App\Models\ActionPlanComment;
use App\Models\Verification;
use App\Models\AuditTrail;
use App\Models\Notification;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class EnterpriseAuditSeeder extends Seeder
{
    public function run(): void
    {
        // 1. ENSURE COMPANIES
        $ffs = Company::firstOrCreate(
            ['code' => 'FFS'],
            ['name' => 'FKS Food Sejahtera']
        );

        $fma = Company::firstOrCreate(
            ['code' => 'FMA'],
            ['name' => 'FKS Multi Agro']
        );

        // 2. ENSURE DEPARTMENTS FOR FFS
        $depts = [
            'IT' => 'Information Technology',
            'FIN' => 'Finance & Accounting',
            'SCM' => 'Supply Chain & Logistics',
            'PROC' => 'Procurement & Purchasing',
            'MFG' => 'Manufacturing & Operations',
            'HR' => 'Human Resources & GA',
        ];

        $deptModels = [];
        foreach ($depts as $code => $name) {
            $deptModels[$code] = Department::firstOrCreate(
                ['code' => $code, 'company_id' => $ffs->id],
                ['name' => $name]
            );
        }

        // 3. CREATE / UPDATE USERS (TIDAK DIRUBAH)
        $manager = $this->upsertUser('manager@audit.com', [
            'name' => 'Joko Mulyono',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'level' => 'Audit Head',
            'department_id' => null,
        ]);

        $auditor = $this->upsertUser('auditor@audit.com', [
            'name' => 'Gibran',
            'password' => Hash::make('password'),
            'role' => 'auditor',
            'level' => 'Senior Auditor',
            'department_id' => null,
        ]);

        $auditeeIT = $this->upsertUser('auditee@audit.com', [
            'name' => 'Bahlil Laksamana',
            'password' => Hash::make('password'),
            'role' => 'auditee',
            'level' => 'IT Lead',
            'department_id' => $deptModels['IT']->id,
        ]);

        // Also ensure original test users exist
        $this->upsertUser('fahmi@manager.local', [
            'name' => 'Fahmi Prayuda',
            'password' => Hash::make('Fahmi123'),
            'role' => 'manager',
            'level' => 'Audit Head',
            'department_id' => null,
        ]);

        $this->upsertUser('aulia@auditor.local', [
            'name' => 'Aulia',
            'password' => Hash::make('Aulia123'),
            'role' => 'auditor',
            'level' => 'Senior Auditor',
            'department_id' => null,
        ]);

        $this->upsertUser('shavazia@auditee.local', [
            'name' => 'Shavazia',
            'password' => Hash::make('Shavazia123'),
            'role' => 'auditee',
            'level' => 'Staff',
            'department_id' => $deptModels['IT']->id,
        ]);

        // 4. CLEAN UP UNWANTED OLD DUMMY PROJECTS & FINDINGS
        AuditProject::whereIn('project_code', ['AUD-FFS-2026-002', 'AUD-FFS-2025-Q4'])->delete();
        Finding::whereIn('finding_code', ['FND-SCM-2026-003', 'FND-PROC-2025-004', 'FND-HR-2026-005', 'FND-FIN-2026-002'])->delete();

        // 5. SINGLE AUDIT PROJECT (HANYA 1 PROJECT)
        $project = AuditProject::updateOrCreate(
            ['project_code' => 'AUD-FFS-2026-001'],
            [
                'company_id' => $ffs->id,
                'project_name' => 'FFS - IT Infrastructure & Cyber Security Audit 2026',
                'release_date' => Carbon::now()->subDays(10)->format('Y-m-d'),
                'status' => 'open',
                'created_by' => $manager->id,
            ]
        );

        // 6. SINGLE FINDING & ACTION PLAN FOR THE PROJECT
        $f1 = Finding::updateOrCreate(
            ['finding_code' => 'FND-ITGC-2026-001'],
            [
                'audit_project_id' => $project->id,
                'title' => 'Kelemahan Kontrol Akses Privileged & Ketiadaan Multi-Factor Authentication pada Production Server',
                'description' => 'Ditemukan 14 akun root/admin sharing tanpa audit trail login individual, dan port SSH terbuka ke public subnet tanpa VPN/MFA requirement.',
                'risk_rating' => 'Extreme',
                'risk_category' => 'Significant',
                'created_by' => $auditor->id,
                'status' => 'open',
            ]
        );

        $fd1 = FindingDepartment::firstOrCreate([
            'finding_id' => $f1->id,
            'department_id' => $deptModels['IT']->id,
        ], ['status' => 'open']);

        $ap1 = ActionPlan::updateOrCreate(
            ['finding_department_id' => $fd1->id],
            [
                'root_cause' => 'Arsitektur legacy bastion server belum diintegrasikan dengan SSO Active Directory perusahaan pasca migrasi cloud.',
                'corrective_action' => 'Implementasi Bastion Host terkunci dengan Cloudflare Zero Trust MFA dan pembatasan IP whitelist per departemen IT.',
                'due_date' => Carbon::now()->addDays(20)->format('Y-m-d'),
                'status' => 'open',
                'flags' => ['submitted'],
                'submitted_at' => Carbon::now()->subDays(2),
                'submitted_by' => $auditeeIT->id,
            ]
        );

        // Ensure comment only created if not already present
        if (!ActionPlanComment::where('action_plan_id', $ap1->id)->exists()) {
            ActionPlanComment::create([
                'action_plan_id' => $ap1->id,
                'role' => 'auditee',
                'message' => 'Konfigurasi SSO Zero Trust sudah diterapkan di staging server. Dokumen bukti SOP dan screenshot konfigurasi terlampir untuk verifikasi.',
                'created_by' => $auditeeIT->id,
            ]);
        }

        // 7. AUDIT TRAILS & NOTIFICATIONS (IDEMPOTENT)
        if (!AuditTrail::where('record_id', $f1->id)->where('module', 'Finding')->exists()) {
            AuditTrail::create([
                'user_id' => $auditor->id,
                'module' => 'Finding',
                'action' => 'CREATE',
                'record_id' => $f1->id,
                'description' => 'Auditor Gibran created finding FND-ITGC-2026-001 (Extreme Risk).',
                'old_values' => null,
                'new_values' => ['finding_code' => 'FND-ITGC-2026-001', 'risk_rating' => 'Extreme'],
            ]);
        }

        if (!Notification::where('user_id', $auditor->id)->where('url', '/findings/' . $f1->id)->exists()) {
            Notification::create([
                'user_id' => $auditor->id,
                'type' => 'action_plan_submitted',
                'title' => 'Action Plan Disubmit',
                'message' => 'Departemen IT telah mengirimkan bukti penyelesaian FND-ITGC-2026-001.',
                'url' => '/findings/' . $f1->id,
                'read_at' => null,
            ]);
        }
    }

    private function upsertUser(string $email, array $attributes): User
    {
        $user = User::withTrashed()->where('email', $email)->first();

        if ($user) {
            $user->fill($attributes);
            $user->deleted_at = null; // ensure active/restored
            $user->save();
            return $user;
        }

        return User::create(array_merge($attributes, [
            'email' => $email,
        ]));
    }
}
