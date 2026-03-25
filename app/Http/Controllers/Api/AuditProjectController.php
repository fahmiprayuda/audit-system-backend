<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AuditProject;
use App\Models\Finding;
use App\Models\Company;
use Carbon\Carbon;

class AuditProjectController extends Controller
{

    // ===============================
    // GET ALL PROJECTS
    // ===============================
    public function index()
    {
        return AuditProject::with('company')->get();
    }

    // ===============================
    // GET DETAIL PROJECT
    // ===============================
    public function show($id)
{
    $project = \App\Models\AuditProject::with([
        'company',
        'findings.findingDepartments.department',
        'findings.findingDepartments.actionPlans'
    ])->findOrFail($id);

    $findingsCollection = Finding::where('audit_project_id', $id)
        ->with([
            'findingDepartments.department',
            'findingDepartments.actionPlans'
        ])
        ->get();

    // ===============================
    // SUMMARY
    // ===============================
    $summary = [
        "total" => $findingsCollection->count(),
        "significant" => $findingsCollection->where('risk_category', 'Significant')->count(),
        "moderate" => $findingsCollection->where('risk_category', 'Moderate')->count(),
        "open" => $findingsCollection->where('status', 'open')->count(),
        "need_review" => $findingsCollection->where('status', 'need_review')->count(),
        "closed" => $findingsCollection->where('status', 'closed')->count(),
    ];

    // ===============================
    // FORMAT FINDINGS
    // ===============================
    $findings = $findingsCollection->map(function ($finding) {

        return [
            'id' => $finding->id,
            'finding_code' => $finding->finding_code,
            'title' => $finding->title,
            'risk_rating' => $finding->risk_rating,
            'risk_category' => $finding->risk_category,
            'status' => $finding->status,
            'due_date' => $finding->due_date,

            'departments' => $finding->findingDepartments->map(function ($fd) {
                return [
                    'finding_department_id' => $fd->id,
                    'department_id' => $fd->department->id,
                    'name' => $fd->department->name,

                    'action_plans' => $fd->actionPlans->map(function ($ap) {
                        return [
                            'id' => $ap->id,
                            'status' => $ap->status,
                            'target_date' => $ap->target_date,
                        ];
                    })
                ];
            }),
        ];
    });

    // ===============================
    // FINAL RESPONSE 🔥
    // ===============================
    return response()->json([
        "project" => $project,
        "summary" => $summary,
        "findings" => $findings
    ]);
}

    // ===============================
    // GET FINDINGS BY PROJECT
    // ===============================
    public function findings($id)
    {
        return Finding::where('audit_project_id', $id)
            ->with('findingDepartments.department')
            ->get();
    }

    // ===============================
    // CREATE PROJECT
    // ===============================
    public function store(Request $request)
    {
        // VALIDATION
        $request->validate([
            'company_id'   => 'required|exists:companies,id',
            'project_name' => 'required|string|max:255',
            'audit_type'   => 'nullable|string|max:255',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
        ]);

        // GET COMPANY
        $company = Company::findOrFail($request->company_id);

        $companyCode = $company->code;
        $year = Carbon::now()->year;

        // ===============================
        // GENERATE PROJECT CODE
        // ===============================
        $count = AuditProject::whereYear('created_at', $year)
            ->where('company_id', $company->id)
            ->count() + 1;

        $sequence = str_pad($count, 3, '0', STR_PAD_LEFT);

        $projectCode = "AUD-{$companyCode}-{$year}-{$sequence}";

        // ===============================
        // FIX PROJECT NAME (AUTO PREFIX)
        // ===============================
        $name = $request->project_name;

        if (!str_starts_with($name, $companyCode . ' -')) {
            $name = $companyCode . ' - ' . $name;
        }

        // ===============================
        // CREATE PROJECT
        // ===============================
        $project = AuditProject::create([
            'project_code' => $projectCode,
            'company_id'   => $request->company_id,
            'project_name' => $name,
            'audit_type'   => $request->audit_type,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date,
            'status'       => 'open',
            'created_by'   => auth()->id() ?? 1
        ]);

        return response()->json([
            'message' => 'Project created successfully',
            'data' => $project
        ]);
    }

    // ===============================
    // UPDATE PROJECT
    // ===============================
    public function update(Request $request, $id)
    {
        $request->validate([
            'project_name' => 'required|string|max:255',
            'audit_type'   => 'nullable|string|max:255',
            'start_date'   => 'nullable|date',
            'end_date'     => 'nullable|date|after_or_equal:start_date',
        ]);

        $project = AuditProject::findOrFail($id);

        $companyCode = $project->company->code;

        // FIX PREFIX SAAT UPDATE
        $name = $request->project_name;

        if (!str_starts_with($name, $companyCode . ' -')) {
            $name = $companyCode . ' - ' . $name;
        }

        $project->update([
            'project_name' => $name,
            'audit_type'   => $request->audit_type,
            'start_date'   => $request->start_date,
            'end_date'     => $request->end_date
        ]);

        return response()->json([
            'message' => 'Project updated successfully',
            'data' => $project
        ]);
    }

    // ===============================
    // DELETE PROJECT
    // ===============================
    public function destroy($id)
    {
        $project = AuditProject::findOrFail($id);

        // PROTECTION
        if ($project->findings()->count() > 0) {
            return response()->json([
                'message' => 'Project cannot be deleted because it already has findings.'
            ], 400);
        }

        $project->delete();

        return response()->json([
            'message' => 'Project deleted successfully.'
        ]);
    }
}