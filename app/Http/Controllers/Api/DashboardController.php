<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use App\Models\AuditProject;
use App\Models\Finding;
use App\Models\ActionPlan;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
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
}