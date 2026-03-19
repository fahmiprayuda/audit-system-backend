<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActionPlan;

class ActionPlanController extends Controller
{
    public function store(Request $request)
{
    $validated = $request->validate([
        'finding_department_id' => 'required|exists:finding_departments,id',
        'root_cause' => 'nullable|string',
        'corrective_action' => 'required|string',
        'target_date' => 'nullable|date',
        'status' => 'required|in:open,need_review,completed',
    ]);

    $actionPlan = ActionPlan::create($validated);

    return response()->json([
        'message' => 'Action Plan created successfully',
        'data' => $actionPlan
    ], 201);
}

public function bulkStore(Request $request)
    {
        $request->validate([
            'plans' => 'required|array',
        ]);

        foreach ($request->plans as $plan) {

            ActionPlan::create([
                'finding_department_id' => $plan['finding_department_id'],
                'root_cause' => $plan['root_cause'] ?? null,
                'corrective_action' => $plan['corrective_action'] ?? null,
                'target_date' => $plan['target_date'] ?? null,
                'status' => 'open'
            ]);
        }

        return response()->json([
            'message' => 'Action plans created successfully'
        ]);
    }
}
