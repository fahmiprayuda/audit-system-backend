<?php

namespace App\Http\Controllers\Api;

use App\Services\StatusService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Finding;
use App\Models\FindingDepartment;
use App\Models\AuditProject;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FindingController extends Controller
{

/* =====================================================
GET ALL FINDINGS
===================================================== */

public function index(Request $request)
{
    $query = Finding::with([
        'project:id,project_code,company_id',
        'project.company',
        'findingDepartments.department',
        'findingDepartments.actionPlans'
    ]);

    // SEARCH
    if ($request->search) {
        $search = $request->search;

        $query->where(function ($q) use ($search) {
            $q->where('finding_code', 'like', "%$search%")
              ->orWhere('title', 'like', "%$search%")
              ->orWhereHas('project', function ($p) use ($search) {
                  $p->where('project_code', 'like', "%$search%");
              });
        });
    }

    // FILTER RISK
    if ($request->status) {
        $query->where('status', $request->status);
    }

    // FILTER DEPARTMENT
    if ($request->department_id) {
        $ids = explode(',', $request->department_id);

        $query->whereHas('findingDepartments', function ($q) use ($ids) {
            $q->whereIn('department_id', $ids);
        });
    }

    $findings = $query->latest()->paginate(10);

    // TRANSFORM
    $findings->getCollection()->transform(function ($f) {

        $f->description = $f->description ?? '';

        // 🔥 OVERDUE BASED ON FD (optional nanti bisa per dept)
        $f->is_overdue = false;

        $f->is_overdue = $f->findingDepartments
                ->flatMap->actionPlans
                ->contains(fn($ap) =>
                    $ap->target_date &&
                    Carbon::parse($ap->target_date)->isPast() &&
                    $ap->status !== 'approved'
                );

        $f->departments = $f->findingDepartments->map(function ($fd) {
        return [
                'id' => $fd->department->id,
                'name' => $fd->department->name,
                'status' => $fd->status,

                'action_plans' => $fd->actionPlans->map(function ($ap) {
                    return [
                        'id' => $ap->id,
                        'status' => $ap->status,
                        'target_date' => $ap->target_date
                    ];
                })
            ];
        });

        return $f;
    });

    return response()->json($findings);
}


/* =====================================================
SHOW DETAIL
===================================================== */

public function show($id)
{
    $finding = Finding::with([
        'project.company',
        'findingDepartments.department',
        'findingDepartments.actionPlans',
        'findingDepartments.actionPlans.evidences',
        'findingDepartments.actionPlans.verifications',
        'findingDepartments.actionPlans.comments'
    ])->findOrFail($id);

    return response()->json([
        'id' => $finding->id,
        'finding_code' => $finding->finding_code,
        'title' => $finding->title,
        'description' => $finding->description ?? '',
        'risk_rating' => $finding->risk_rating,
        'due_date' => $finding->due_date,

        // 🔥 OPTIONAL: summary status
        'status' => $finding->status,

        'departments' => $finding->findingDepartments->map(function ($fd) {
            return [
                'finding_department_id' => $fd->id,
                'department_id' => $fd->department->id,
                'name' => $fd->department->name, // ✅ INI WAJIB
                'status' => $fd->status,

                'action_plans' => $fd->actionPlans->map(fn($ap) => [
                'id' => $ap->id,
                'root_cause' => $ap->root_cause ?? '',
                'corrective_action' => $ap->corrective_action ?? '',
                'status' => $ap->status,
                'start_date' => $ap->start_date,
                'target_date' => $ap->target_date,

                'evidences' => $ap->evidences->map(fn($e) => [
                    'id' => $e->id,
                    'file_name' => $e->file_name,
                    'file_path' => $e->file_path,
                ]),

                'comments' => $ap->comments->map(fn($c) => [
                    'role' => $c->role,
                    'message' => $c->message,
                    'created_by' => $c->created_by,
                ])
            ])

            ];
        })
    ]);
}


/* =====================================================
CREATE
===================================================== */

public function store(Request $request)
{
    $request->validate([
        'audit_project_id' => 'required|exists:audit_projects,id',
        'title' => 'required|string',
        'description' => 'nullable|string',
        'risk_rating' => 'nullable|string',
        'due_date' => 'nullable|date',

        'departments' => 'nullable|array',
        'departments.*' => 'exists:departments,id',

        'action_plans' => 'nullable|array',
        'action_plans.*.department_id' => 'required_with:action_plans|exists:departments,id',
        'action_plans.*.corrective_action' => 'required_with:action_plans|string',
    ]);

    DB::beginTransaction();

    try {

        // ===============================
        // GENERATE FINDING CODE
        // ===============================
        $project = AuditProject::with('company')
        ->findOrFail($request->audit_project_id);

        if (!$project->company) {
            throw new \Exception('Project belum punya company');
        }

        $companyCode = $project->company->code;
        $year = Carbon::now()->year;

        $count = Finding::whereYear('created_at', $year)
            ->whereHas('project', fn($q) => $q->where('company_id', $project->company_id))
            ->count() + 1;

        $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
        $findingCode = "FND-{$companyCode}-{$year}-{$sequence}";

        // ===============================
        // CREATE FINDING
        // ===============================
        $finding = Finding::create([
            'audit_project_id' => $request->audit_project_id,
            'finding_code' => $findingCode,
            'title' => $request->title,
            'description' => $request->description ?? '',
            'risk_rating' => $request->risk_rating,
            'risk_category' => in_array($request->risk_rating, ['Extreme', 'Major'])
                ? 'Significant' : 'Moderate',
            'start_date' => now(),
            'created_by' => auth()->id() ?? 1,

            'status' => 'open' // 🔥 WAJIB
        ]);

        // ===============================
        // CREATE FD + ACTION PLAN
        // ===============================
        if ($request->departments) {
            foreach ($request->departments as $deptId) {

                $fd = FindingDepartment::create([
                    'finding_id' => $finding->id,
                    'department_id' => $deptId,
                    'status' => 'open'
                ]);

                if ($request->action_plans) {
                    $relatedPlans = collect($request->action_plans)
                        ->where('department_id', $deptId);

                    foreach ($relatedPlans as $ap) {
                        \App\Models\ActionPlan::create([
                            'finding_department_id' => $fd->id,
                            'root_cause' => $ap['root_cause'] ?? '',
                            'corrective_action' => $ap['corrective_action'],
                            'start_date' => $ap['start_date'] ?? null,
                            'target_date' => $ap['target_date'] ?? null,
                            'status' => 'draft'
                        ]);
                    }
                }

                StatusService::sync($fd->id);
            }
        }

        DB::commit();

        return response()->json([
            'message' => 'Finding + Action Plans created successfully',
            'data' => $finding
        ]);

    } catch (\Exception $e) {

        DB::rollBack();

        return response()->json([
            'message' => 'Failed to create finding',
            'error' => $e->getMessage()
        ], 500);

    }
}


/* =====================================================
UPDATE FINDING (GLOBAL DATA ONLY)
===================================================== */

public function update(Request $request, $id)
{
    $request->validate([
        'title' => 'sometimes|string',
        'description' => 'nullable|string',
        'risk_rating' => 'sometimes|string',
        'due_date' => 'nullable|date'
    ]);

    $finding = Finding::findOrFail($id);

    $riskRating = $request->risk_rating ?? $finding->risk_rating;

    $finding->update([
        'title' => $request->title ?? $finding->title,
        'description' => $request->description ?? $finding->description,
        'risk_rating' => $riskRating,
        'risk_category' => in_array($riskRating, ['Extreme', 'Major'])
            ? 'Significant' : 'Moderate',
        'due_date' => $request->due_date ?? $finding->due_date
    ]);

    return response()->json([
        'message' => 'Finding updated successfully',
        'data' => $finding
    ]);
}


/* =====================================================
DELETE
===================================================== */

public function destroy($id)
{
    Finding::findOrFail($id)->delete();

    return response()->json([
        'message' => 'Finding deleted successfully'
    ]);
}

}