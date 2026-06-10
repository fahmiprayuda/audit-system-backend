<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\AuditProject;
use App\Models\Company;
use Carbon\Carbon;

class AuditProjectController extends Controller
{

    // ===============================
    // GET ALL PROJECTS
    // ===============================
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);

        $projects = AuditProject::with('company')
            ->latest()
            ->paginate($perPage);

        return response()->json($projects);
    }

    public function show($id)
{
    $project = \App\Models\AuditProject::with([
        'company',
        'findings.findingDepartments.department',
        'findings.findingDepartments.actionPlans'
    ])->findOrFail($id);

    $findingsCollection = $project->findings;

    // ===============================
    // SUMMARY
    // ===============================
    $summary = [
        "total" => $findingsCollection->count(),
        "significant" => $findingsCollection->where('risk_category', 'Significant')->count(),
        "moderate" => $findingsCollection->where('risk_category', 'Moderate')->count(),
        "open" => $findingsCollection->filter(fn($f) => $f->status === 'open')->count(),
        "in_progress" => $findingsCollection->filter(fn($f) => $f->status === 'in_progress')->count(),
        "pending_verify" => $findingsCollection->filter(fn($f) => $f->status === 'pending_verify')->count(),
        "closed" => $findingsCollection->filter(fn($f) => $f->status === 'closed')->count(),
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

            'departments' => $finding->findingDepartments->map(function ($fd) {
                return [
                    'finding_department_id' => $fd->id,
                    'department_id' => $fd->department->id,
                    'name' => $fd->department->name,
                    'status' => $fd->status, // 🔥 INI YANG PENTING

                    'action_plans' => $fd->actionPlans->map(function ($ap) {
                        return [
                            'id' => $ap->id,
                            'status' => $ap->status,
                            'due_date' => $ap->due_date,
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
        ->with('findingDepartments.department', 'findingDepartments.actionPlans')
        ->get()
        ->map(function ($f) {
            return [
                'id' => $f->id,
                'title' => $f->title,
                'status' => $f->status
            ];
        });
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
            'release_date'   => 'nullable|date',
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
            'release_date'   => $request->release_date,
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
            'release_date'   => 'nullable|date',
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
            'release_date'   => $request->release_date,
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