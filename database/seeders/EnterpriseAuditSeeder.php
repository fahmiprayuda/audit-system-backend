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

        // 3. CREATE / UPDATE USERS SAFELY WITH SOFT DELETES
        $manager = $this->upsertUser('manager@audit.com', [
            'name' => 'Audrey Kurniawan',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'level' => 'Audit Head',
            'department_id' => null,
        ]);

        $auditor = $this->upsertUser('auditor@audit.com', [
            'name' => 'Rian Pratama',
            'password' => Hash::make('password'),
            'role' => 'auditor',
            'level' => 'Senior Auditor',
            'department_id' => null,
        ]);

        $auditeeIT = $this->upsertUser('auditee@audit.com', [
            'name' => 'Budi Santoso',
            'password' => Hash::make('password'),
            'role' => 'auditee',
            'level' => 'IT Lead',
            'department_id' => $deptModels['IT']->id,
        ]);

        $auditeeFin = $this->upsertUser('auditee.fin@audit.com', [
            'name' => 'Dewi Lestari',
            'password' => Hash::make('password'),
            'role' => 'auditee',
            'level' => 'Finance Manager',
            'department_id' => $deptModels['FIN']->id,
        ]);

        $auditeeScm = $this->upsertUser('auditee.scm@audit.com', [
            'name' => 'Hendra Wijaya',
            'password' => Hash::make('password'),
            'role' => 'auditee',
            'level' => 'SCM Manager',
            'department_id' => $deptModels['SCM']->id,
        ]);

        // Also ensure original test users exist
        $this->upsertUser('fahmi@manager.local', [
            'name' => 'Fahmi Prayuda',
            'password' => Hash::make('password'),
            'role' => 'manager',
            'level' => 'Audit Head',
            'department_id' => null,
        ]);

        $this->upsertUser('aulia@auditor.local', [
            'name' => 'Aulia',
            'password' => Hash::make('password'),
            'role' => 'auditor',
            'level' => 'Senior Auditor',
            'department_id' => null,
        ]);

        $this->upsertUser('shavazia@auditee.local', [
            'name' => 'Shavazia',
            'password' => Hash::make('password'),
            'role' => 'auditee',
            'level' => 'Staff',
            'department_id' => $deptModels['IT']->id,
        ]);

        // 4. AUDIT PROJECTS
        $project1 = AuditProject::updateOrCreate(
            ['project_code' => 'AUD-FFS-2026-001'],
            [
                'company_id' => $ffs->id,
                'project_name' => 'FFS - IT Infrastructure & Cyber Security Audit 2026',
                'release_date' => Carbon::now()->subMonths(2)->format('Y-m-d'),
                'status' => 'open',
                'created_by' => $manager->id,
            ]
        );

        $project2 = AuditProject::updateOrCreate(
            ['project_code' => 'AUD-FFS-2026-002'],
            [
                'company_id' => $ffs->id,
                'project_name' => 'FFS - Financial Controls & Procurement Compliance Audit',
                'release_date' => Carbon::now()->subMonth()->format('Y-m-d'),
                'status' => 'open',
                'created_by' => $manager->id,
            ]
        );

        $project3 = AuditProject::updateOrCreate(
            ['project_code' => 'AUD-FFS-2025-Q4'],
            [
                'company_id' => $ffs->id,
                'project_name' => 'FFS - Plant Operations & Inventory Governance Audit 2025',
                'release_date' => Carbon::now()->subMonths(6)->format('Y-m-d'),
                'status' => 'closed',
                'created_by' => $manager->id,
            ]
        );

        // 5. FINDINGS & ACTION PLANS

        // --- Finding 1: ITGC Extreme Finding (Submitted, awaiting verification) ---
        $f1 = Finding::updateOrCreate(
            ['finding_code' => 'FND-ITGC-2026-001'],
            [
                'audit_project_id' => $project1->id,
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

        ActionPlanComment::create([
            'action_plan_id' => $ap1->id,
            'role' => 'auditee',
            'message' => 'Konfigurasi SSO Zero Trust sudah diterapkan di staging server. Dokumen bukti SOP dan screenshot konfigurasi terlampir untuk verifikasi.',
            'created_by' => $auditeeIT->id,
        ]);

        // --- Finding 2: SCM & Finance (Overdue Action Plan) ---
        $f2 = Finding::updateOrCreate(
            ['finding_code' => 'FND-FIN-2026-002'],
            [
                'audit_project_id' => $project2->id,
                'title' => 'Ketidaksesuaian Rekonsiliasi Akrual Logistik Vendor dan Selisih Invoice Ekspedisi',
                'description' => 'Terdapat selisih pencatatan delivery order (DO) dan invoice ekspedisi senilai Rp 418.000.000 yang belum dikonfirmasi selama lebih dari 90 hari.',
                'risk_rating' => 'Major',
                'risk_category' => 'Significant',
                'created_by' => $auditor->id,
                'status' => 'open',
            ]
        );

        $fd2Fin = FindingDepartment::firstOrCreate([
            'finding_id' => $f2->id,
            'department_id' => $deptModels['FIN']->id,
        ], ['status' => 'open']);

        $ap2 = ActionPlan::updateOrCreate(
            ['finding_department_id' => $fd2Fin->id],
            [
                'root_cause' => 'Proses 3-way matching manual menggunakan spreadsheet offline rentan typo invoice number dari vendor pihak ketiga.',
                'corrective_action' => 'Rekonsiliasi total bersama seluruh vendor tier-1 logistik dan integrasi otomatis e-Faktur ke modul AP.',
                'due_date' => Carbon::now()->subDays(12)->format('Y-m-d'), // Overdue!
                'status' => 'open',
                'flags' => ['overdue'],
            ]
        );

        // --- Finding 3: SCM Extension Requested ---
        $f3 = Finding::updateOrCreate(
            ['finding_code' => 'FND-SCM-2026-003'],
            [
                'audit_project_id' => $project2->id,
                'title' => 'Penyimpanan Bahan Baku Produksi di Gudang Transit Melampaui Kapasitas dan Suhu Standar',
                'description' => 'Suhu ruang pendingin raw material tercatat berfluktuasi 18-24°C (standar kualitas: maks 8°C), berisiko menurunkan masa kedaluwarsa bahan baku.',
                'risk_rating' => 'Major',
                'risk_category' => 'Significant',
                'created_by' => $auditor->id,
                'status' => 'open',
            ]
        );

        $fd3 = FindingDepartment::firstOrCreate([
            'finding_id' => $f3->id,
            'department_id' => $deptModels['SCM']->id,
        ], ['status' => 'open']);

        $ap3 = ActionPlan::updateOrCreate(
            ['finding_department_id' => $fd3->id],
            [
                'root_cause' => 'Chiller unit kompresor no. 2 mengalami kebocoran freon dan spare part impor mengalami kendala di pelabuhan.',
                'corrective_action' => 'Pengadaan unit HVAC rental cadangan dan instalasi sensor IoT suhu otomatis terhubung ke sistem alarm SMS.',
                'due_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
                'status' => 'open',
                'flags' => ['revision_required'],
            ]
        );

        ActionPlanExtension::create([
            'action_plan_id' => $ap3->id,
            'old_due_date' => Carbon::now()->subDays(5)->format('Y-m-d'),
            'new_due_date' => Carbon::now()->addDays(15)->format('Y-m-d'),
            'status_after_extension' => 'open',
            'reason' => 'Vendor rental HVAC mengonfirmasi instalasi mundur 10 hari karena kalibrasi sensor IoT.',
            'extended_by' => $auditor->id,
        ]);

        ActionPlanComment::create([
            'action_plan_id' => $ap3->id,
            'role' => 'auditor',
            'message' => 'Perpanjangan tanggal jatuh tempo disetujui hingga 15 hari ke depan. Harap cantumkan laporan log kalibrasi saat submit penyelesaian.',
            'created_by' => $auditor->id,
        ]);

        // --- Finding 4: Procurement Closed Finding (Success story) ---
        $f4 = Finding::updateOrCreate(
            ['finding_code' => 'FND-PROC-2025-004'],
            [
                'audit_project_id' => $project3->id,
                'title' => 'Pengadaan Direct-Award Tanpa Dokumentasi Pembanding 3 Penawaran Harga',
                'description' => 'Pembelian sparepart mesin packaging senilai Rp 85.000.000 dilakukan penunjukan langsung tanpa formulir justifikasi teknis pengadaan darurat.',
                'risk_rating' => 'Moderate',
                'risk_category' => 'Moderate',
                'created_by' => $auditor->id,
                'status' => 'closed',
            ]
        );

        $fd4 = FindingDepartment::firstOrCreate([
            'finding_id' => $f4->id,
            'department_id' => $deptModels['PROC']->id,
        ], ['status' => 'closed']);

        $ap4 = ActionPlan::updateOrCreate(
            ['finding_department_id' => $fd4->id],
            [
                'root_cause' => 'Kurangnya pemahaman tim buyer lapangan terkait batas otorisasi emergency purchase SOP-PROC-04.',
                'corrective_action' => 'Pembaruan approval matrix di ERP Oracle dan sosialisasi SOP pengadaan darurat kepada seluruh user operasional.',
                'due_date' => Carbon::now()->subMonths(2)->format('Y-m-d'),
                'status' => 'closed',
                'flags' => [],
                'submitted_at' => Carbon::now()->subMonths(2)->subDays(10),
                'closed_at' => Carbon::now()->subMonths(2),
                'submitted_by' => $auditeeIT->id,
                'closed_by' => $auditor->id,
            ]
        );

        Verification::create([
            'action_plan_id' => $ap4->id,
            'verified_by' => $auditor->id,
            'note' => 'Telah diverifikasi: Approval matrix ERP telah dikunci dengan limit otorisasi baru dan absensi pelatihan tim pengadaan telah lengkap.',
            'status' => 'closed',
            'created_at' => Carbon::now()->subMonths(2),
            'updated_at' => Carbon::now()->subMonths(2),
        ]);

        // --- Finding 5: HR Need Further Review (NFR) ---
        $f5 = Finding::updateOrCreate(
            ['finding_code' => 'FND-HR-2026-005'],
            [
                'audit_project_id' => $project2->id,
                'title' => 'Keterlambatan Pelaksanaan Medical Check Up (MCU) Tahunan Pekerja Area Berisiko Tinggi',
                'description' => 'Sebanyak 38 operator line boiler dan chemical handling belum menjalani MCU berkala sesuai regulasi K3 Permenakertrans.',
                'risk_rating' => 'Moderate',
                'risk_category' => 'Moderate',
                'created_by' => $auditor->id,
                'status' => 'need_further_review',
            ]
        );

        $fd5 = FindingDepartment::firstOrCreate([
            'finding_id' => $f5->id,
            'department_id' => $deptModels['HR']->id,
        ], ['status' => 'need_further_review']);

        ActionPlan::updateOrCreate(
            ['finding_department_id' => $fd5->id],
            [
                'root_cause' => null,
                'corrective_action' => 'Menyusun jadwal MCU bersama klinik rekanan dan verifikasi kelengkapan APD.',
                'due_date' => Carbon::now()->addDays(30)->format('Y-m-d'),
                'status' => 'need_further_review',
                'flags' => [],
            ]
        );

        // 6. AUDIT TRAILS & NOTIFICATIONS
        AuditTrail::create([
            'user_id' => $auditor->id,
            'module' => 'Finding',
            'action' => 'CREATE',
            'record_id' => $f1->id,
            'description' => 'Auditor Rian Pratama created finding FND-ITGC-2026-001 (Extreme Risk).',
            'old_values' => null,
            'new_values' => ['finding_code' => 'FND-ITGC-2026-001', 'risk_rating' => 'Extreme'],
        ]);

        AuditTrail::create([
            'user_id' => $auditeeIT->id,
            'module' => 'ActionPlan',
            'action' => 'SUBMIT',
            'record_id' => $ap1->id,
            'description' => 'Auditee Budi Santoso submitted action plan evidence for FND-ITGC-2026-001.',
            'old_values' => ['status' => 'open'],
            'new_values' => ['status' => 'open', 'flags' => ['submitted']],
        ]);

        AuditTrail::create([
            'user_id' => $auditor->id,
            'module' => 'ActionPlan',
            'action' => 'VERIFY',
            'record_id' => $ap4->id,
            'description' => 'Auditor Rian Pratama verified and closed action plan for FND-PROC-2025-004.',
            'old_values' => ['status' => 'open'],
            'new_values' => ['status' => 'closed'],
        ]);

        Notification::create([
            'user_id' => $auditor->id,
            'type' => 'action_plan_submitted',
            'title' => 'Action Plan Disubmit',
            'message' => 'Departemen IT telah mengirimkan bukti penyelesaian FND-ITGC-2026-001.',
            'url' => '/findings/' . $f1->id,
            'read_at' => null,
        ]);

        Notification::create([
            'user_id' => $auditeeFin->id,
            'type' => 'action_plan_overdue',
            'title' => 'Tenggat Waktu Lewat',
            'message' => 'Action plan FND-FIN-2026-002 telah melewati target due date.',
            'url' => '/findings/' . $f2->id,
            'read_at' => null,
        ]);
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
