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
                'need_further_review',
                'submitted',
                'closed'
            ])->random();

            ActionPlan::create([
                'finding_department_id' => $fd->id,

                'root_cause' => 'Proses kontrol internal tidak berjalan dengan baik',

                'corrective_action' => 'Meningkatkan prosedur kontrol dan melakukan monitoring rutin',

                'due_date' => now()->addDays(rand(10,40)),

                'status' => $status,

                'submitted_at' => in_array($status, [
                    'submitted',
                    'closed'
                ]) ? now()->subDays(rand(1,5)) : null,

                'closed_at' => $status === 'closed'
                    ? now()
                    : null,

                'submitted_by' => in_array($status, [
                    'submitted',
                    'closed'
                ]) ? $user->id : null,

                'closed_by' => $status === 'closed'
                    ? $user->id
                    : null,
            ]);

        }

    }
}