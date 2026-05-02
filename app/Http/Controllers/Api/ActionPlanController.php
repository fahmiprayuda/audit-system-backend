<?php

namespace App\Http\Controllers\Api;

use App\Services\StatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActionPlan;
use App\Models\ActionPlanComment;
use App\Models\FindingDepartment;
use App\Models\Finding;
use App\Models\AuditProject;

class ActionPlanController extends Controller
{

    /* ================= STORE ================= */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'finding_department_id' => 'required|exists:finding_departments,id',
            'root_cause' => 'nullable|string',
            'corrective_action' => 'required|string',
            'target_date' => 'nullable|date',
            'status' => 'required|in:draft,submitted,approved,in_progress,done,verified'
        ]);

        $ap = ActionPlan::create($validated);

        StatusService::sync($ap->finding_department_id);

        return response()->json([
            'message' => 'Action Plan created successfully',
            'data' => $ap
        ], 201);
    }

    public function bulkStore(Request $request)
    {
        $request->validate([
            'plans' => 'required|array',
        ]);

        foreach ($request->plans as $plan) {

            $ap = ActionPlan::create([
                'finding_department_id' => $plan['finding_department_id'],
                'root_cause' => $plan['root_cause'] ?? null,
                'corrective_action' => $plan['corrective_action'] ?? null,
                'target_date' => $plan['target_date'] ?? null,
                'status' => 'draft'
            ]);

            StatusService::sync($ap->finding_department_id);
        }

        return response()->json([
            'message' => 'Action plans created successfully'
        ]);
    }

    /* ================= ACTION ================= */

    public function submit(Request $request, $id)
    {
        $ap = ActionPlan::findOrFail($id);

        $ap->update(['status' => 'submitted']);

        ActionPlanComment::create([
            'action_plan_id' => $ap->id,
            'role' => 'auditee',
            'message' => $request->auditee_comment
        ]);

        StatusService::sync($ap->finding_department_id);

        return response()->json(['message' => 'Submitted']);
    }

    public function reject(Request $request, $id)
    {
        $ap = ActionPlan::findOrFail($id);

        $ap->update(['status' => 'need_revision']);

        ActionPlanComment::create([
            'action_plan_id' => $ap->id,
            'role' => 'auditor',
            'message' => $request->comment
        ]);

        StatusService::sync($ap->finding_department_id);

        return response()->json(['message' => 'Need revision']);
    }

    public function approve($id)
    {
        $ap = ActionPlan::findOrFail($id);

        if (!$ap->canTransitionTo('approved')) {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $ap->update(['status' => 'approved']);

        StatusService::sync($ap->finding_department_id);

        return response()->json(['message' => 'Approved']);
    }

    public function start($id)
    {
        $ap = ActionPlan::findOrFail($id);

        if (!$ap->canTransitionTo('in_progress')) {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $ap->update(['status' => 'in_progress']);

        StatusService::sync($ap->finding_department_id);

        return response()->json(['message' => 'Started']);
    }

    public function done($id)
    {
        $ap = ActionPlan::findOrFail($id);

        if (!$ap->canTransitionTo('done')) {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $ap->update(['status' => 'done']);

        StatusService::sync($ap->finding_department_id);

        return response()->json(['message' => 'Completed']);
    }

    public function verify($id)
    {
        $ap = ActionPlan::findOrFail($id);

        if (!$ap->canTransitionTo('verified')) {
            return response()->json(['message' => 'Invalid status'], 400);
        }

        $ap->update([
            'status' => 'verified',
            'verified_at' => now()
        ]);

        StatusService::sync($ap->finding_department_id);

        return response()->json(['message' => 'Verified']);
    }

}