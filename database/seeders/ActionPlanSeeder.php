<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ActionPlan;
use App\Models\FindingDepartment;

class ActionPlanSeeder extends Seeder
{
    public function run(): void
    {

        $findingDepartments = FindingDepartment::all();

        foreach ($findingDepartments as $fd) {

            ActionPlan::create([
                'finding_department_id' => $fd->id,
                'root_cause' => 'Proses kontrol internal tidak berjalan dengan baik',
                'corrective_action' => 'Meningkatkan prosedur kontrol dan melakukan monitoring rutin',
                'target_date' => now()->addDays(rand(10,40)),
                'status' => collect(['open','need_review','completed'])->random()
            ]);

        }

    }
}