<?php

namespace App\Http\Controllers\Api;

use App\Services\StatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ActionPlan;
use App\Models\ActionPlanComment;
use App\Models\actionPlanCommentAttachment;

class ActionPlanController extends Controller
{

    /* ================= STORE ================= */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'finding_department_id' => 'required|exists:finding_departments,id',
            'root_cause' => 'nullable|string',
            'corrective_action' => 'required|string',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'status' => 'required|in:draft,submitted,need_revision,approved'
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
                'start_date' => $plan['start_date'] ?? null,
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
        if (!$request->auditee_comment || trim($request->auditee_comment) === '') {
            return response()->json([
                'message' => 'Comment wajib diisi'
            ], 400);
        }

        $ap = ActionPlan::findOrFail($id);

        if (!$ap->canTransitionTo('submitted')) {
            return response()->json([
                'message' => 'Invalid status transition'
            ], 400);
        }

        $ap->update([
            'status' => 'submitted',
            'submitted_at' => now(),
            'submitted_by' => auth()->id()
        ]);

        ActionPlanComment::create([
            'action_plan_id' => $ap->id,
            'role' => auth()->user()->role,
            'message' => $request->auditee_comment,
            'created_by' => auth()->id()
        ]);

        if ($request->hasFile('evidences')) {

            foreach ($request->file('evidences') as $file) {

                $path = $file->store(
                    'evidences',
                    'public'
                );

                $ap->evidences()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        StatusService::sync(
            $ap->finding_department_id
        );

        return response()->json([
            'message' => 'Submitted'
        ]);
    }

    public function approve($id)
    {
        $ap = ActionPlan::findOrFail($id);

        $ap->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => auth()->id()
        ]);

        StatusService::sync($ap->finding_department_id);

        return response()->json(['message' => 'Approved']);
    }

    public function comment(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',
            'attachments.*' => 'file|max:10240'
        ]);

        $ap = ActionPlan::findOrFail($id);

         if ($ap->status === 'approved') {
                return response()->json([
                    'message' => 'Action Plan already closed'
                ], 403);
            }

        $comment = ActionPlanComment::create([
            'action_plan_id' => $ap->id,
            'role' => auth()->user()->role,
            'message' => $request->message,
            'created_by' => auth()->id(),
        ]);

        if ($request->hasFile('attachments')) {

            foreach ($request->file('attachments') as $file) {

                $path = $file->store(
                    'comment-attachments',
                    'public'
                );

                ActionPlanCommentAttachment::create([
                    'action_plan_comment_id' => $comment->id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return response()->json([
            'message' => 'Comment added'
        ]);
    }

}
