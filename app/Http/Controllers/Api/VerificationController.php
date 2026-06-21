<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Verification;
use App\Models\ActionPlan;
use Illuminate\Http\Request;

class VerificationController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'action_plan_id' => 'required|exists:action_plans,id',
        'status' => 'required|in:closed,rejected,revision',
        'verification_note' => 'nullable|string'
    ]);

    $verification = Verification::create([
        'action_plan_id' => $request->action_plan_id,
        'verified_by' => auth()->id() ?? 1,
        'verification_note' => $request->verification_note,
        'status' => $request->status,
        'verified_at' => now()
    ]);

    // update status action plan
    $actionPlan = ActionPlan::find($request->action_plan_id);

        if ($request->status === 'closed') {
            $actionPlan->status = 'completed';
        }

        if ($request->status === 'rejected') {
            $actionPlan->status = 'need_further_review';
        }

        $actionPlan->save();

        $findingDepartment = $actionPlan->findingDepartment;
        $finding = $findingDepartment->finding;

        $remaining = $finding->findingDepartments()
            ->whereHas('actionPlans', function ($q) {
                $q->where('status', '!=', 'completed');
            })->count();

            if ($remaining == 0) {
                $finding->status = 'closed';
                $finding->save();
}

    return response()->json([
        'message' => 'Verification submitted',
        'data' => $verification
    ], 201);
}
}
