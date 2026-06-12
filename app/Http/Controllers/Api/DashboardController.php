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
    private function periodFilter($query, Request $request)
    {
        if (
            $request->start_date &&
            $request->end_date
        ) {
            $query->whereBetween(
                'release_date',
                [
                    $request->start_date,
                    $request->end_date
                ]
            );
        }

        return $query;
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

            "draft" =>
                ActionPlan::where(
                    "status",
                    "draft"
                )->count(),

            "submitted" =>
                ActionPlan::where(
                    "status",
                    "submitted"
                )->count(),

            "need_revision" =>
                ActionPlan::where(
                    "status",
                    "need_revision"
                )->count(),

            "approved" =>
                ActionPlan::where(
                    "status",
                    "approved"
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
                    "approved"
                )
                ->count(),
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
                "approved"
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
                "approved"
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
                'approved'
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
                'approved'
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
                'approved'
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
                'approved'
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
                'approved'
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

}