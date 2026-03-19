<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Evidence;
use App\Models\ActionPlan;
use App\Models\User;

class EvidenceSeeder extends Seeder
{
    public function run(): void
    {

        $actionPlans = ActionPlan::all();
        $user = User::first();

        foreach ($actionPlans as $plan) {

            Evidence::create([
                'action_plan_id' => $plan->id,
                'file_path' => 'evidence/sample_document.pdf',
                'uploaded_by' => $user->id
            ]);

        }

    }
}