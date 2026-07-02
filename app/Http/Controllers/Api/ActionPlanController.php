<?php

namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use App\Services\AuditTrailService;
use App\Services\StatusService;
use App\Services\NotificationService;
use App\Models\User;
use App\Models\ActionPlan;
use App\Models\ActionPlanComment;
use App\Models\ActionPlanExtension;
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
            'due_date' => 'nullable|date',
        ]);

        $validated['status'] =
            'need_further_review';

        $ap = ActionPlan::create($validated);

        NotificationService::newActionPlan($ap);

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
                'due_date' => $plan['due_date'] ?? null,
                'status' => 'need_further_review'
            ]);

            NotificationService::newActionPlan($ap);

            StatusService::sync($ap->finding_department_id);
        }

        return response()->json([
            'message' => 'Action plans created successfully'
        ]);
    }

    /* ================= ACTION ================= */

    // public function submit(Request $request, $id)
    // {
    //     if (!$request->auditee_comment || trim($request->auditee_comment) === '') {
    //         return response()->json([
    //             'message' => 'Comment wajib diisi'
    //         ], 400);
    //     }

    //     if ($ap->status === 'closed') {
    //         return response()->json([
    //             'message' => 'Action Plan already closed'
    //         ], 400);
    //     }

    //     $ap = ActionPlan::findOrFail($id);
    //     $ap->addFlag('submitted');
    //     $ap->removeFlag('revision_required');

    //     ActionPlanComment::create([
    //         'action_plan_id' => $ap->id,
    //         'role' => auth()->user()->role,
    //         'message' => $request->auditee_comment,
    //         'created_by' => auth()->id()
    //     ]);

    //     if ($request->hasFile('evidences')) {

    //         foreach ($request->file('evidences') as $file) {

    //             $path = $file->store(
    //                 'evidences',
    //                 'public'
    //             );

    //             $ap->evidences()->create([
    //                 'file_path' => $path,
    //                 'file_name' => $file->getClientOriginalName(),
    //                 'uploaded_by' => auth()->id(),
    //             ]);
    //         }
    //     }

    //     StatusService::sync(
    //         $ap->finding_department_id
    //     );

    //     AuditTrailService::log(
    //         'action_plan',
    //         'submit',
    //         $ap->id,
    //         'Action plan submitted'
    //     );

    //     return response()->json([
    //         'message' => 'Submitted'
    //     ]);
    // }

    public function close($id)
    {
        $ap = ActionPlan::findOrFail($id);

        if ($ap->status === 'closed') {
            return response()->json([
                'message' => 'Action Plan already closed'
            ], 400);
        }

        $ap->update([
            'status' => 'closed',
            'flags' => null,
            'closed_at' => now(),
            'closed_by' => auth()->id()
        ]);

        NotificationService::actionPlanClosed($ap);

        StatusService::sync($ap->finding_department_id);

        AuditTrailService::log(
            'action_plan',
            'close',
            $ap->id,
            'Action plan closed'
        );

        return response()->json(['message' => 'Closed']);
    }

    public function comment(Request $request, $id)
    {
        $request->validate([
            'message' => 'required|string',

            'workflow_action' =>
                'nullable|in:submitted,revision_required',

            'attachments.*' =>
                'file|max:10240'
        ]);

        $ap = ActionPlan::findOrFail($id);

         if ($ap->status === 'closed') {
                return response()->json([
                    'message' => 'Action Plan already closed'
                ], 403);
            }

          
            DB::beginTransaction();
        try {
                $comment = ActionPlanComment::create([
                'action_plan_id' => $ap->id,
                'role' => auth()->user()->role,
                'message' => $request->message,
                'created_by' => auth()->id(),
            ]);

            $user = auth()->user();

            if ($request->filled('workflow_action')) {

                // Validasi role
                if (
                    $user->role === 'auditee'
                    &&
                    $request->workflow_action !== 'submitted'
                ) {
                    return response()->json([
                        'message' => 'Invalid workflow action.'
                    ], 403);
                }

                if (
                    in_array($user->role, ['auditor', 'manager'])
                    &&
                    $request->workflow_action !== 'revision_required'
                ) {
                    return response()->json([
                        'message' => 'Invalid workflow action.'
                    ], 403);
                }

                $ap->replacePrimaryFlag(
                    $request->workflow_action
                );

                if ($request->workflow_action === 'submitted') {

                    NotificationService::submitted($ap);

                } elseif ($request->workflow_action === 'revision_required') {

                    NotificationService::revisionRequired($ap);

                }

                AuditTrailService::log(
                    'action_plan',
                    'workflow_changed',
                    $ap->id,
                    "Workflow changed to {$request->workflow_action}"
                );
            }

            // selalu kirim notif comment
            NotificationService::comment($ap, $user);

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

            AuditTrailService::log(
                'comment',
                'create',
                $comment->id,
                'Added comment'
            );

            DB::commit();

            return response()->json([
                'message' => 'Comment added'
            ]);

        } catch (\Exception $e) {

            DB::rollBack();

            return response()->json([
                'message' => 'Failed to add comment',
                'error' => $e->getMessage(),
            ], 500);
        }
        
    }
        
    // public function requestRevision(Request $request, $id)
    // {

    //     $request->validate([
    //         'message' => 'required|string'
    //     ]);

    //     $ap = ActionPlan::findOrFail($id);

    //     $ap->addFlag('revision_required');
    //     $ap->removeFlag('submitted');

    //     ActionPlanComment::create([
    //         'action_plan_id' => $ap->id,
    //         'role' => auth()->user()->role,
    //         'message' => $request->message,
    //         'created_by' => auth()->id(),
    //     ]);

    //     AuditTrailService::log(
    //         'action_plan',
    //         'revision_requested',
    //         $ap->id,
    //         'Revision requested'
    //     );

    //     return response()->json([
    //         'message' => 'Revision requested'
    //     ]);
    // }    

    public function extend(Request $request, $id)
    {
        $request->validate([
            'new_due_date' => 'required|date',
            'status_after_extension' =>
                'required|in:open,need_further_review,closed',
            'reason' => 'required|string|max:1000',
        ]);

        $ap = ActionPlan::findOrFail($id);

        $ap->removeFlag('overdue');

        // Simpan history extension
        ActionPlanExtension::create([
            'action_plan_id' => $ap->id,
            'old_due_date' => $ap->due_date,
            'new_due_date' => $request->new_due_date,
            'status_after_extension'
                => $request->status_after_extension,
            'reason' => $request->reason,
            'extended_by' => auth()->id(),
        ]);

        // Jika auditor pilih Need Further Review
        if ($request->status_after_extension === 'need_further_review') {

            $ap->replacePrimaryFlag('on_site_validation');

            NotificationService::siteValidation($ap);
        }

        // Jika langsung Closed
        if ($request->status_after_extension === 'closed') {
            $ap->update([
                'flags' => []
            ]);
        }

        $ap->update([
            'due_date' => $request->new_due_date,
            'status' => $request->status_after_extension,
        ]);

        StatusService::sync(
            $ap->finding_department_id
        );

        AuditTrailService::log(
            'action_plan',
            'extend',
            $ap->id,
            "Extended due date from {$ap->getOriginal('due_date')} to {$request->new_due_date}"
        );

        return response()->json([
            'message' => 'Action Plan extended successfully'
        ]);
    }
}
