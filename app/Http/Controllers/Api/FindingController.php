<?php

namespace App\Http\Controllers\Api;

use App\Services\AuditTrailService;
use App\Services\StatusService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFindingRequest;
use Illuminate\Http\Request;
use App\Models\Finding;
use App\Models\FindingDepartment;
use App\Models\FindingSequence;
use App\Models\AuditProject;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FindingController extends Controller
{
    private function authorizeAuditee($findingId)
    {
        $user = auth()->user();

        if (!$user || $user->role !== 'auditee') {
            return;
        }

        $allowed = FindingDepartment::where(
            'finding_id',
            $findingId
        )
        ->where(
            'department_id',
            $user->department_id
        )
        ->exists();

        abort_unless($allowed, 403);
    }

/* =====================================================
GET ALL FINDINGS
===================================================== */
    public function index(Request $request)
    {
        $query = Finding::with([
            'creator:id,name,email,role',
            'project:id,project_code,company_id,created_by',
            'project.creator:id,name,email,role',
            'project.company',
            'findingDepartments.department',
            'findingDepartments.department.users:id,name,email,role,department_id',
            'findingDepartments.actionPlans',
            'findingDepartments.actionPlans.submittedBy:id,name,email,role',
            'findingDepartments.actionPlans.closedBy:id,name,email,role'
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

        // FILTER RISK RATING
        if ($request->filled('risk_rating')) {
            $query->where('risk_rating', $request->risk_rating);
        }

        // FILTER STATUS
        if ($request->filled('status')) {
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
                        $ap->due_date &&
                        Carbon::parse($ap->due_date)->isPast() &&
                        $ap->status !== 'closed'
                    );

            $f->departments = $f->findingDepartments->map(function ($fd) {
            return [
                    'id' => $fd->department->id,
                    'name' => $fd->department->name,
                    'status' => $fd->status,

                    'action_plans' => $fd->actionPlans->map(function ($ap) {
                        return [
                            'id' => $ap->id,
                            'root_cause' => $ap->root_cause,
                            'corrective_action' => $ap->corrective_action,
                            'status' => $ap->status,
                            'due_date' => $ap->due_date,

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
        $this->authorizeAuditee($id);   

        $finding = Finding::with([
            'creator:id,name,email,role',
            'project.creator:id,name,email,role',
            'project.company.departments',
            'findingDepartments.department.users:id,name,email,role,department_id',
            'findingDepartments.actionPlans.submittedBy:id,name,email,role',
            'findingDepartments.actionPlans.closedBy:id,name,email,role',
            'findingDepartments.actionPlans.verifications',
            'findingDepartments.actionPlans.comments.creator',
            'findingDepartments.actionPlans.comments.attachments',
            'findingDepartments.actionPlans.extensions.extender'
        ])->findOrFail($id);

        return response()->json([
            'id' => $finding->id,
            'finding_code' => $finding->finding_code,
            'title' => $finding->title,
            'description' => $finding->description ?? '',
            'risk_rating' => $finding->risk_rating,
            'risk_category' => $finding->risk_category,
            'created_at' => $finding->created_at,
            'creator' => $finding->creator ? [
                'id' => $finding->creator->id,
                'name' => $finding->creator->name,
                'email' => $finding->creator->email,
                'role' => $finding->creator->role,
            ] : null,

            'project' => [
                'id' => $finding->project->id,
                'project_code' => $finding->project->project_code,
                'project_name' => $finding->project->project_name,
                'release_date' => $finding->project->release_date,
                'creator' => $finding->project->creator ? [
                    'id' => $finding->project->creator->id,
                    'name' => $finding->project->creator->name,
                    'email' => $finding->project->creator->email,
                    'role' => $finding->project->creator->role,
                ] : null,

                'company' => [
                    'id' => $finding->project->company->id,
                    'name' => $finding->project->company->name,
                    'departments' => $finding->project->company->departments
                ]
            ],

            // 🔥 OPTIONAL: summary status
            'status' => $finding->status,

            'departments' => $finding->findingDepartments->map(function ($fd) {
                $pics = $fd->department->users
                    ->where('role', 'auditee')
                    ->values()
                    ->map(fn($u) => [
                        'id' => $u->id,
                        'name' => $u->name,
                        'email' => $u->email,
                    ]);

                return [
                    'finding_department_id' => $fd->id,
                    'department_id' => $fd->department->id,
                    'name' => $fd->department->name, // ✅ INI WAJIB
                    'code' => $fd->department->code,
                    'status' => $fd->status,
                    'pics' => $pics,
                    'pic_name' => $pics->first()['name'] ?? ($fd->department->name . ' PIC'),

                    'action_plans' => $fd->actionPlans->map(function ($ap) use ($pics, $fd) {
                        return [
                            'id' => $ap->id,
                            'root_cause' => $ap->root_cause ?? '',
                            'corrective_action' => $ap->corrective_action ?? '',
                            'status' => $ap->status,
                            'queue' => $ap->queue,
                            'primary_flag' => $ap->primary_flag,
                            'due_date' => $ap->due_date,
                            'submitted_at' => $ap->submitted_at,
                            'closed_at' => $ap->closed_at,
                            'submitted_by' => $ap->submittedBy ? [
                                'id' => $ap->submittedBy->id,
                                'name' => $ap->submittedBy->name,
                                'role' => $ap->submittedBy->role,
                            ] : null,
                            'closed_by' => $ap->closedBy ? [
                                'id' => $ap->closedBy->id,
                                'name' => $ap->closedBy->name,
                                'role' => $ap->closedBy->role,
                            ] : null,
                            'pic_name' => $ap->submittedBy?->name ?? ($pics->first()['name'] ?? ($fd->department->name . ' PIC')),

                        'flags' => array_values(
                                    array_unique(
                                        array_merge(
                                            $ap->flags ?? [],
                                            (
                                                $ap->due_date &&
                                                $ap->due_date->isPast() &&
                                                $ap->status !== 'closed'
                                            )
                                                ? ['overdue']
                                                : []
                                        )
                                    )
                                ),

                    'extensions' => $ap->extensions
                        ->sortByDesc('created_at')
                        ->values()
                        ->map(fn($e) => [
                            'id' => $e->id,
                            'old_due_date' => $e->old_due_date,
                            'new_due_date' => $e->new_due_date,
                            'status_after_extension' => $e->status_after_extension,
                            'reason' => $e->reason,
                            'extended_by' => $e->extender?->name,
                            'created_at' => $e->created_at,
                        ]),

                    'comments' => $ap->comments
                        ->sortBy('created_at')
                        ->values()
                        ->map(fn($c) => [

                            'id' => $c->id,
                            'user_id' => $c->created_by,
                            'role' => $c->role,
                            'message' => $c->message,
                            'user_name' => $c->creator?->name,
                            'created_at' => $c->created_at,
                            'attachments' => $c->attachments->map(fn($a) => [
                                'id' => $a->id,
                                'file_name' => $a->file_name,
                                'file_path' => $a->file_path,
                            ])
                        ])
                    ];
                }),
            ];
        }),
    ]);
}


/* =====================================================
CREATE
===================================================== */

    public function store(StoreFindingRequest $request)
    {
        $validated = $request->validated();

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

            $companyCode = $project->company->code;
            $year = now()->year;

            $sequenceRow = FindingSequence::lockForUpdate()
                ->firstOrCreate(
                    [
                        'company_code' => $companyCode,
                        'year' => $year
                    ],
                    [
                        'last_number' => 0
                    ]
                );

            $sequenceRow->increment('last_number');

            $sequence = str_pad(
                $sequenceRow->fresh()->last_number,
                3,
                '0',
                STR_PAD_LEFT
            );
            
            $findingCode = "FND-{$companyCode}-{$year}-{$sequence}";

            // ===============================
            // CREATE FINDING
            // ===============================
            $riskRating = $request->risk_rating ?? 'Moderate';

            $finding = Finding::create([
                'audit_project_id' => $request->audit_project_id,
                'finding_code' => $findingCode,
                'title' => $request->title,
                'description' => $request->description ?? '',
                'risk_rating' => $riskRating,

                'risk_category' => in_array($riskRating, ['Extreme', 'Major'])
                    ? 'Significant' : 'Moderate',
                
                'created_by' => auth()->id() ?? 1,
                'status' => 'open'
            ]);

            // ===============================
            // CREATE FD + ACTION PLAN
            // ===============================
            if ($request->departments) {
                foreach ($request->departments as $deptId) {

                    $fd = FindingDepartment::create([
                        'finding_id' => $finding->id,
                        'department_id' => $deptId,
                        'status' => 'need_further_review'
                    ]);

                    if ($request->action_plans) {
                        $relatedPlans = collect($request->action_plans)
                            ->where('department_id', $deptId);

                        foreach ($relatedPlans as $ap) {
                            \App\Models\ActionPlan::create([
                                'finding_department_id' => $fd->id,
                                'root_cause' => $ap['root_cause'] ?? '',
                                'corrective_action' => $ap['corrective_action'],
                                'due_date' => $ap['due_date'] ?? null,
                                'status' => 'need_further_review'
                            ]);
                        }
                    }

                    StatusService::sync($fd->id);

                    // $fd->refresh();

                    // dd($fd->status);
                    // ===============================
                    // Audit Trail
                    // ===============================
                    AuditTrailService::log(
                            'finding',
                            'create',
                            $finding->id,
                            'Created finding'
                        );
                        
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Finding + Action Plans created successfully',
                'data' => $finding
            ], 201);

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
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $finding = Finding::findOrFail($id);

        $riskRating = $request->risk_rating ?? $finding->risk_rating;

        $finding->update([
            'title' => $request->title ?? $finding->title,
            'description' => $request->description ?? $finding->description,
            'risk_rating' => $riskRating,

            'risk_category' =>
                in_array($riskRating, ['Extreme', 'Major'])
                    ? 'Significant'
                    : 'Moderate',

            'department_id' =>
                $request->filled('department_id')
                    ? $request->department_id
                    : $finding->department_id,
        ]);

        // refresh biar data terbaru kebaca
        $finding->refresh();

        return response()->json([
            'message' => 'Finding updated successfully',
            'data' => $finding,
        ]);
    }

/* =====================================================
DELETE
===================================================== */
    public function destroy($id)
    {
        $finding = Finding::findOrFail($id);

        if ($finding->actionPlans()->count() > 0) {
            return response()->json([
                'message' => 'Finding cannot be deleted because it already has action plans.'
            ], 400);
        }

        $finding->delete();

        return response()->json([
            'message' => 'Finding deleted successfully.'
        ]);

        // $project = $finding->project;

        // // hapus finding
        // $finding->delete();

        // // reload findings
        // $project->load("findings");

        // $findings = $project->findings;

        // // ===== update status =====
        // if ($findings->count() === 0) {
        //     $project->status = "open";

        // } elseif (
        //     $findings->every(fn ($f) => $f->status === "closed")
        // ) {
        //     $project->status = "closed";

        // } else {
        //     $project->status = "in progress";
        // }

        // $project->save();

        // return response()->json([
        //     "message" => "Finding deleted"
        // ]);
    }

}