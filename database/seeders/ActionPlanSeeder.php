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

          $user = User::first();

            $status = collect([
                'draft',
                'submitted',
                'need_revision',
                'approved'
            ])->random();

            ActionPlan::create([
                'finding_department_id' => $fd->id,

                'root_cause' => 'Proses kontrol internal tidak berjalan dengan baik',

                'corrective_action' => 'Meningkatkan prosedur kontrol dan melakukan monitoring rutin',

                'target_date' => now()->addDays(rand(10,40)),

                'status' => $status,

                'submitted_at' => in_array($status, [
                    'submitted',
                    'need_revision',
                    'approved'
                ]) ? now()->subDays(rand(1,5)) : null,

                'approved_at' => $status === 'approved'
                    ? now()
                    : null,

                'submitted_by' => in_array($status, [
                    'submitted',
                    'need_revision',
                    'approved'
                ]) ? $user->id : null,

                'approved_by' => $status === 'approved'
                    ? $user->id
                    : null,
            ]);

        }

    }
}