<?php

namespace App\Http\Controllers\Api;

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

    // FILTERS
    if ($request->risk_rating) {
        $query->where('risk_rating', $request->risk_rating);
    }

    if ($request->status) {
        $query->where('status', $request->status);
    }

    if ($request->department_id) {
        $query->whereHas('findingDepartments', function ($q) use ($request) {
            $q->where('department_id', $request->department_id);
        });
    }

    $findings = $query->latest()->paginate(10);

    // TRANSFORM
    $findings->getCollection()->transform(function ($f) {

        $f->is_overdue = false;

        if ($f->due_date && $f->status !== 'closed') {
            if (Carbon::parse($f->due_date)->isPast()) {
                $f->is_overdue = true;
            }
        }

        $f->description = $f->description ?? '';

        $f->departments = $f->findingDepartments->map(function ($fd) {
            return [
                'finding_department_id' => $fd->id,
                'department_id' => $fd->department->id,
                'department_name' => $fd->department->name,
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
        'findingDepartments.actionPlans.verifications'
    ])->findOrFail($id);

    return response()->json([
        'id' => $finding->id,
        'finding_code' => $finding->finding_code,
        'title' => $finding->title,
        'description' => $finding->description ?? '',
        'risk_rating' => $finding->risk_rating,
        'status' => $finding->status,
        'due_date' => $finding->due_date,

        'departments' => $finding->findingDepartments->map(function ($fd) {
            return [
                'finding_department_id' => $fd->id,
                'department_id' => $fd->department->id,
                'department_name' => $fd->department->name,

                'action_plans' => $fd->actionPlans->map(function ($ap) {
                    return [
                        'id' => $ap->id,
                        'root_cause' => $ap->root_cause ?? '',
                        'corrective_action' => $ap->corrective_action ?? '',
                        'status' => $ap->status,
                        'target_date' => $ap->target_date,

                        'evidences' => $ap->evidences->map(fn($e) => [
                            'id' => $e->id,
                            'file_path' => $e->file_path
                        ]),

                        'verifications' => $ap->verifications->map(fn($v) => [
                            'id' => $v->id,
                            'status' => $v->status,
                            'note' => $v->note ?? ''
                        ])
                    ];
                })
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
        'risk_rating' => 'required|string',
        'due_date' => 'nullable|date',
        'departments' => 'required|array',
        'departments.*' => 'exists:departments,id'
    ]);

    DB::beginTransaction();

    try {

        $project = AuditProject::with('company')->findOrFail($request->audit_project_id);

        $companyCode = $project->company->code;
        $year = Carbon::now()->year;

        $count = Finding::whereYear('created_at', $year)
            ->whereHas('project', fn($q) => $q->where('company_id', $project->company_id))
            ->count() + 1;

        $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);
        $findingCode = "FND-{$companyCode}-{$year}-{$sequence}";

        $finding = Finding::create([
            'audit_project_id' => $request->audit_project_id,
            'finding_code' => $findingCode,
            'title' => $request->title,
            'description' => $request->description ?? '',
            'risk_rating' => $request->risk_rating,
            'risk_category' => in_array($request->risk_rating, ['Extreme', 'Major'])
                ? 'Significant' : 'Moderate',
            'due_date' => $request->due_date,
            'status' => 'open',
            'created_by' => auth()->id() ?? 1
        ]);

        foreach ($request->departments as $deptId) {
            FindingDepartment::create([
                'finding_id' => $finding->id,
                'department_id' => $deptId
            ]);
        }

        DB::commit();

        return response()->json([
            'message' => 'Finding created successfully',
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
UPDATE
===================================================== */

public function update(Request $request, $id)
{
    $request->validate([
        'title' => 'sometimes|string',
        'description' => 'nullable|string',
        'risk_rating' => 'sometimes|string',
        'due_date' => 'nullable|date',
        'status' => 'sometimes|in:open,need_review,completed,closed'
    ]);

    $finding = Finding::findOrFail($id);

    $riskRating = $request->risk_rating ?? $finding->risk_rating;

    $finding->update([
        'title' => $request->title ?? $finding->title,
        'description' => $request->description ?? $finding->description,
        'risk_rating' => $riskRating,
        'risk_category' => in_array($riskRating, ['Extreme', 'Major'])
            ? 'Significant' : 'Moderate',
        'due_date' => $request->due_date ?? $finding->due_date,
        'status' => $request->status ?? $finding->status
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