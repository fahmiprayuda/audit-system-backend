<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Finding;
use App\Models\Department;
use App\Models\AuditProject;
use App\Models\User;

class FindingSeeder extends Seeder
{
    public function run(): void
    {
        $project = AuditProject::first();
        $user = User::first();

        $finance = Department::where('name','Finance & Accounting')->first();
        $manufacturing = Department::where('name','Manufacturing')->first();
        $procurement = Department::where('name','Procurement')->first();
        $supply = Department::where('name','Supply Chain')->first();
        $quality = Department::where('name','Quality')->first();
        $it = Department::where('name','IT Dept')->first();
        $rnd = Department::where('name','Research & Development')->first();
        $marketing = Department::where('name','Marketing')->first();
        $trade = Department::where('name','Trade Marketing')->first();
        $opex = Department::where('name','Operational Excellence')->first();
        $hrga = Department::where('name','HRGA')->first();

        $findings = [

                    [
                    'title' => 'Selisih stock bahan baku di gudang',
                    'description' => 'Stock fisik tidak sesuai dengan sistem inventory',
                    'risk_rating' => 'Extreme',
                    'status' => 'open',
                    'due_date' => '2026-04-01',
                    'departments' => [$supply]
                    ],

                    [
                    'title' => 'Dokumentasi inspeksi kualitas tidak lengkap',
                    'description' => 'Beberapa laporan QC tidak tersedia',
                    'risk_rating' => 'Moderate',
                    'status' => 'open',
                    'due_date' => '2026-04-10',
                    'departments' => [$quality]
                    ]

                    ];

        $counter = 1;
        foreach ($findings as $data) {

            $finding = Finding::create([

                    'audit_project_id' => $project->id,
                    'created_by' => $user->id,

                    'finding_code' => 'FND-2026-' . str_pad($counter,3,'0',STR_PAD_LEFT),

                    'title' => $data['title'],
                    'description' => $data['description'],

                    'risk_rating' => $data['risk_rating'],

                    'risk_category' => in_array($data['risk_rating'], ['Extreme','Major'])
                        ? 'Significant'
                        : 'Moderate',

                    'status' => $data['status'],
                    'due_date' => $data['due_date']

                ]);

                $counter++;

            foreach ($data['departments'] as $dept) {
                if ($dept) {
                    $finding->departments()->attach($dept->id);
                }
            }

        }

    }
}