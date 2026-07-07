<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

use Carbon\Carbon;
use App\Models\AuditProject;
use App\Models\Finding;
use App\Models\ActionPlan;
use App\Models\FindingDepartment;


class DashboardController extends Controller
{
    private function projectIds(Request $request)
    {
        return AuditProject::query()

            ->when(
                $request->filled("start_date")
                &&
                $request->filled("end_date"),

                function ($q) use ($request) {

                    $q->whereBetween(
                        "release_date",
                        [
                            $request->start_date,
                            $request->end_date,
                        ]
                    );
                }
            )

            ->pluck("id");
    }

    private function actionPlans(Request $request)
    {
        $projectIds = $this->projectIds($request);

        return ActionPlan::query()

            ->whereHas(
                "findingDepartment.finding",
                function ($q) use ($projectIds) {

                    $q->whereIn(
                        "audit_project_id",
                        $projectIds
                    );
                }
            );
    }
    public function summary()
    {
        return response()->json([

            // projects
            "projects_total" =>
                AuditProject::count(),

            "projects_open" =>
                AuditProject::where(
                    "status",
                    "open"
                )->count(),

            "projects_in_progress" =>
                AuditProject::where(
                    "status",
                    "in_progress"
                )->count(),

            "projects_closed" =>
                AuditProject::where(
                    "status",
                    "closed"
                )->count(),

            // findings
            "findings_total" =>
                Finding::count(),

            "findings_open" =>
                Finding::where(
                    "status",
                    "open"
                )->count(),

            "findings_review" =>
                Finding::where(
                    "status",
                    "need_further_review"
                )->count(),

            "findings_closed" =>
                Finding::where(
                    "status",
                    "closed"
                )->count(),

            // action plans
            "action_total" =>
                ActionPlan::count(),

            "need_further_review" =>
                ActionPlan::where(
                    "status",
                    "need_further_review"
                )->count(),

            "submitted" =>
                ActionPlan::where(
                    "status",
                    "submitted"
                )->count(),

            "closed" =>
                ActionPlan::where(
                    "status",
                    "closed"
                )->count(),

            "overdue" =>
                ActionPlan::whereDate(
                    "due_date",
                    "<",
                    Carbon::today()
                )
                ->where(
                    "status",
                    "!=",
                    "closed"
                )
                ->count(),
        ]);
    }

    public function overview(Request $request)
    {
        $query = $this->actionPlans($request);

        $total = (clone $query)->count();

        $open = (clone $query)
            ->where('status', 'open')
            ->count();

        $nfr = (clone $query)
            ->where('status', 'need_further_review')
            ->count();

        $closed = (clone $query)
            ->where('status', 'closed')
            ->count();

        return response()->json([

            "period" => [
                "start" => $request->start_date,
                "end" => $request->end_date,
            ],

            "total_action_plans" => $total,

            "summary" => [

                "open" => $open,
                "need_further_review" => $nfr,
                "closed" => $closed,

                "open_percent" =>
                    $total ? round($open / $total * 100) : 0,

                "need_further_review_percent" =>
                    $total ? round($nfr / $total * 100) : 0,

                "closed_percent" =>
                    $total ? round($closed / $total * 100) : 0,
            ],

            "distribution" => [

                [
                    "name" => "Open",
                    "value" => $open,
                ],

                [
                    "name" => "Need Further Review",
                    "value" => $nfr,
                ],

                [
                    "name" => "Closed",
                    "value" => $closed,
                ],

            ]

        ]);
    }

    public function findingsByRisk()
    {
        return response()->json(

            Finding::select(
                "risk_rating",
                DB::raw(
                    "COUNT(*) as total"
                )
            )
            ->groupBy("risk_rating")
            ->get()

        );
    }

    public function findingsByCategory()
    {
        return response()->json(

            Finding::select(
                "risk_category",
                DB::raw(
                    "COUNT(*) as total"
                )
            )
            ->groupBy("risk_category")
            ->get()

        );
    }

    public function actionPlansByStatus()
    {
        return response()->json(

            ActionPlan::select(
                "status",
                DB::raw(
                    "COUNT(*) as total"
                )
            )
            ->groupBy("status")
            ->get()

        );
    }

    public function overdueActionPlans()
    {
        return response()->json(

            ActionPlan::with([
                "findingDepartment.department",
                "findingDepartment.finding.project",
            ])
            ->whereDate(
                "due_date",
                "<",
                Carbon::today()
            )
            ->where(
                "status",
                "!=",
                "closed"
            )
            ->orderBy(
                "due_date"
            )
            ->get()

        );
    }

    public function overdueByDepartment()
    {
        return response()->json(

            ActionPlan::join(
                "finding_departments",
                "action_plans.finding_department_id",
                "=",
                "finding_departments.id"
            )
            ->join(
                "departments",
                "finding_departments.department_id",
                "=",
                "departments.id"
            )
            ->whereDate(
                "due_date",
                "<",
                Carbon::today()
            )
            ->where(
                "action_plans.status",
                "!=",
                "closed"
            )
            ->select(
                "departments.name",
                DB::raw(
                    "COUNT(*) as total"
                )
            )
            ->groupBy(
                "departments.name"
            )
            ->get()

        );
    }

    public function executiveSummary(Request $request)
    {
        $today = Carbon::today();

        $projectIds = AuditProject::query()
            ->when(
                $request->start_date,
                fn($q) =>
                $q->whereBetween(
                    'release_date',
                    [
                        $request->filled('start_date') &&
                        $request->filled('end_date'),
                    ]
                )
            )
            ->pluck('id');

        $totalFindings =
            Finding::whereIn(
                'audit_project_id',
                $projectIds
            )->count();

        $closedFindings =
            Finding::whereIn(
                'audit_project_id',
                $projectIds
            )
            ->where(
                'status',
                'closed'
            )
            ->count();

        $openFindings =
            Finding::query()

            ->join(
                'finding_departments',
                'findings.id',
                '=',
                'finding_departments.finding_id'
            )

            ->join(
                'action_plans',
                'finding_departments.id',
                '=',
                'action_plans.finding_department_id'
            )

            ->whereIn(
                'findings.audit_project_id',
                $projectIds
            )

            ->whereDate(
                'action_plans.due_date',
                '<',
                $today
            )

            ->where(
                'action_plans.status',
                '!=',
                'closed'
            )

            ->distinct('findings.id')

            ->count('findings.id');

        $openActions =
            ActionPlan::whereHas(
                'findingDepartment.finding',
                function ($q) use ($projectIds) {

                    $q->whereIn(
                        'audit_project_id',
                        $projectIds
                    );
                }
            )
            ->whereDate(
                'due_date',
                '<',
                $today
            )
            ->where(
                'status',
                '!=',
                'closed'
            )
            ->count();

        $dueSoon =
            ActionPlan::whereHas(
                'findingDepartment.finding',
                function ($q) use ($projectIds) {

                    $q->whereIn(
                        'audit_project_id',
                        $projectIds
                    );
                }
            )
            ->whereBetween(
                'due_date',
                [
                    $today,
                    $today->copy()->addDays(7)
                ]
            )
            ->where(
                'status',
                '!=',
                'closed'
            )
            ->count();

        return response()->json([
            'total_findings' =>
                $totalFindings,

            'open_findings' =>
                $openFindings,

            'open_actions' =>
                $openActions,

            'due_soon' =>
                $dueSoon,

            'closed_findings' =>
                $closedFindings,

            'historical_findings' =>
                Finding::count(),
        ]);
    }

    public function overdueFindingsByDepartment(Request $request)
    {
        $projectIds = AuditProject::query()

            ->when(
                $request->start_date &&
                $request->end_date,

                function ($q) use ($request) {

                    $q->whereBetween(
                        'release_date',
                        [
                            $request->start_date,
                            $request->end_date
                        ]
                    );

                }
            )

            ->pluck('id');

        $data = FindingDepartment::query()

            ->join(
                'departments',
                'finding_departments.department_id',
                '=',
                'departments.id'
            )

            ->join(
                'findings',
                'finding_departments.finding_id',
                '=',
                'findings.id'
            )

            ->join(
                'action_plans',
                'finding_departments.id',
                '=',
                'action_plans.finding_department_id'
            )

            ->whereIn(
                'findings.audit_project_id',
                $projectIds
            )

            ->whereDate(
                'action_plans.due_date',
                '<',
                now()
            )

            ->where(
                'action_plans.status',
                '!=',
                'closed'
            )

            ->select(
                'departments.name',

                DB::raw(
                    'COUNT(DISTINCT findings.id) as overdue_findings'
                ),
                
                DB::raw(
                    'COUNT(action_plans.id) as overdue_actions'
                )
            )

            ->groupBy(
                'departments.name'
            )

            ->orderByDesc('overdue_findings')

            ->get();

        return response()->json(
            $data
        );
    }

    public function topOverdueFindings(Request $request)
    {
        $projectIds = AuditProject::query()

            ->when(
                $request->start_date &&
                $request->end_date,

                function ($q) use ($request) {

                    $q->whereBetween(
                        'release_date',
                        [
                            $request->start_date,
                            $request->end_date
                        ]
                    );

                }
            )

            ->pluck('id');

        $data = Finding::query()

            ->join(
                'finding_departments',
                'findings.id',
                '=',
                'finding_departments.finding_id'
            )

            ->join(
                'departments',
                'finding_departments.department_id',
                '=',
                'departments.id'
            )

            ->join(
                'action_plans',
                'finding_departments.id',
                '=',
                'action_plans.finding_department_id'
            )

            ->whereIn(
                'findings.audit_project_id',
                $projectIds
            )

            ->whereDate(
                'action_plans.due_date',
                '<',
                today()
            )

            ->where(
                'action_plans.status',
                '!=',
                'closed'
            )

            ->select(
                'findings.id',
                'findings.finding_code',
                'findings.title',

                DB::raw(
                    'MIN(action_plans.due_date) as oldest_due_date'
                ),

                DB::raw(
                    'COUNT(DISTINCT action_plans.id) as overdue_actions'
                ),

                DB::raw(
                    'GROUP_CONCAT(DISTINCT departments.name) as departments'
                )
            )

            ->groupBy(
                'findings.id',
                'findings.finding_code',
                'findings.title'
            )

            ->get()

            ->map(function ($item) {

                $item->days_overdue =
                    Carbon::parse($item->oldest_due_date)
                    ->startOfDay()
                    ->diffInDays(
                        now()->startOfDay()
                    );

                return $item;
            })

            ->sortByDesc(
                'days_overdue'
            )

            ->values()

            ->take(10);

        return response()->json(
            $data
        );
    }

    public function getDueDateAttribute()
    {
        return $this->actionPlans()->max('due_date');
    }

    public function findingDetails($id)
    {
        $finding = Finding::with([

            'project',

            'findingDepartments.department',

            'findingDepartments.actionPlans' => function ($q) {
                $q->where('status', '!=', 'closed')
                ->orderBy('due_date');
            }

        ])->findOrFail($id);

        $actions = collect();

        foreach ($finding->findingDepartments as $fd) {

            foreach ($fd->actionPlans as $ap) {

                if (
                    $ap->status !== 'closed'
                    && $ap->due_date
                    && $ap->due_date->isPast()
                )

                $actions->push([

                    'id' => $ap->id,

                    'department' =>
                        $fd->department?->name,

                    'root_cause' =>
                        $ap->root_cause,

                    'corrective_action' =>
                        $ap->corrective_action,

                    'due_date' =>
                        $ap->due_date,

                    'status' =>
                        $ap->status,

                    'days_overdue' => (
                        $ap->status !== 'closed'
                        && $ap->due_date
                        && $ap->due_date->isPast()
                    )
                    ? abs(
                        now()->startOfDay()->diffInDays(
                            $ap->due_date->startOfDay(),
                            false
                        )
                    )
                    : 0

                ]);
            }
        }

        return response()->json([

            'id' =>
                $finding->id,

            'finding_code' =>
                $finding->finding_code,

            'title' =>
                $finding->title,

            'risk_rating' =>
                $finding->risk_rating,

            'status' =>
                $finding->status,

            'project' =>
                $finding->project?->project_name,

            'actions' =>
                $actions->values()

        ]);
    }

    public function projectPortfolio(Request $request)
    {
        $data = AuditProject::query()

            ->withCount('findings')

            ->withCount([
                'findings as open_findings' => function ($q) {
                    $q->where('status', 'open');
                },

                'findings as closed_findings' => function ($q) {
                    $q->where('status', 'closed');
                },

                'findings as nfr_findings' => function ($q) {
                    $q->where('status', 'need_further_review');
                },
            ])

            ->withCount([

                'findings as significant_findings' => function ($q) {

                    $q->whereIn(
                        'risk_category',
                        ['Significant']
                    );

                },

                'findings as moderate_findings' => function ($q) {

                    $q->where(
                        'risk_category',
                        'Moderate'
                    );

                }

            ])

            ->when(
                $request->start_date &&
                $request->end_date,

                function ($q) use ($request) {

                    $q->whereBetween(
                        'release_date',
                        [
                            $request->start_date,
                            $request->end_date
                        ]
                    );

                }
            )

            ->latest('release_date')

            ->get()

            ->map(function ($project) {

                $overdueActions =
                    DB::table('action_plans')

                    ->join(
                        'finding_departments',
                        'action_plans.finding_department_id',
                        '=',
                        'finding_departments.id'
                    )

                    ->join(
                        'findings',
                        'finding_departments.finding_id',
                        '=',
                        'findings.id'
                    )

                    ->where(
                        'findings.audit_project_id',
                        $project->id
                    )

                    ->whereDate(
                        'action_plans.due_date',
                        '<',
                        today()
                    )

                    ->where(
                        'action_plans.status',
                        '!=',
                        'closed'
                    )

                    ->count();

                return [

                    'id' =>
                        $project->id,

                    'project_code' =>
                        $project->project_code,

                    'project_name' =>
                        $project->project_name,

                    'release_date' =>
                        $project->release_date,

                    'total_findings' =>
                        $project->findings_count,

                    'significant_findings' =>
                    $project->significant_findings,
                    
                    'moderate_findings' =>
                        $project->moderate_findings,

                    'open_findings' =>
                        $project->open_findings,

                    'closed_findings' =>
                        $project->closed_findings,
                    
                    'nfr_findings' =>
                        $project->nfr_findings,

                    'overdue_actions' =>
                        $overdueActions,

                    'completion_percent' =>
                        $project->findings_count > 0
                            ? round(
                                (
                                    $project->closed_findings
                                    /
                                    $project->findings_count
                                ) * 100
                            )
                            : 0
                ];
            });

        return response()->json($data);
    }

    public function projectDetails($id)
    {
        $project = AuditProject::with([
            'findings'
        ])->findOrFail($id);

        return response()->json([

            'id' =>
                $project->id,

            'project_code' =>
                $project->project_code,

            'project_name' =>
                $project->project_name,

            'release_date' =>
                $project->release_date,

            'status' =>
                $project->status,

            'findings' =>

                $project->findings

                ->map(function ($finding) {

                    return [

                        'id' =>
                            $finding->id,

                        'finding_code' =>
                            $finding->finding_code,

                        'title' =>
                            $finding->title,

                        'risk_rating' =>
                            $finding->risk_rating,

                        'risk_category' =>
                            $finding->risk_category,

                        'status' =>
                            $finding->status,

                    ];

                })

        ]);
    }

}