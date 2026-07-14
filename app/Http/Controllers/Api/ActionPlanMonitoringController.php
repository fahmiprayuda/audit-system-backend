<?php
    
namespace App\Http\Controllers\Api;
    
use App\Http\Controllers\Controller;
use App\Models\ActionPlan;
use App\Models\Department;
use App\Models\ActionPlanExtension;
use App\Models\AuditProject;

use Illuminate\Http\Request;

class ActionPlanMonitoringController extends Controller
{
    private function percent($value, $total)
    {
        return $total
            ? round($value / $total * 100)
            : 0;
    }

    public function index()
    {
        $projectIds = AuditProject::query()

            ->when(
                request()->filled("start_date")
                &&
                request()->filled("end_date"),

                function ($q) {

                    $q->whereBetween(
                        "release_date",
                        [
                            request("start_date"),
                            request("end_date"),
                        ]
                    );
                }
            )

            ->pluck("id");

        $actionPlans = ActionPlan::whereHas(
            "findingDepartment.finding",
            function ($q) use ($projectIds) {

                $q->whereIn(
                    "audit_project_id",
                    $projectIds
                );

            }
        )->get();

        $totalActionPlans = $actionPlans->count();
        
        $summary = [

            'open' =>
                $actionPlans
                    ->where('status', 'open')
                    ->count(),

            'need_further_review' =>
                $actionPlans
                    ->where('status', 'need_further_review')
                    ->count(),

            'closed' =>
                $actionPlans
                    ->where('status', 'closed')
                    ->count(),

            'overdue' =>
                $actionPlans
                    ->filter(fn($ap) =>
                        $ap->status !== 'closed'
                        &&
                        $ap->due_date
                        &&
                        $ap->due_date->isPast()
                    )
                    ->count(),

            'submitted' =>
                $actionPlans
                    ->filter(fn($ap) =>
                        in_array(
                            'submitted',
                            $ap->flags ?? []
                        )
                    )
                    ->count(),

            'revision_required' =>
                $actionPlans
                    ->filter(fn($ap) =>
                        in_array(
                            'revision_required',
                            $ap->flags ?? []
                        )
                    )
                    ->count(),

            'on_site_validation' =>
                $actionPlans
                    ->filter(fn($ap) =>
                        in_array(
                            'on_site_validation',
                            $ap->flags ?? []
                        )
                    )
                    ->count(),
        ];

        $summary["open_percent"] =
            $this->percent(
                $summary["open"],
                $totalActionPlans
            );

        $summary["need_further_review_percent"] =
            $this->percent(
                $summary["need_further_review"],
                $totalActionPlans
            );

        $summary["closed_percent"] =
            $this->percent(
                $summary["closed"],
                $totalActionPlans
            );

        $aging = [
            '0_30' => 0,
            '31_60' => 0,
            '61_90' => 0,
            '90_plus' => 0,
        ];

        foreach ($actionPlans as $ap) {

            if (
                $ap->status === 'closed'
                || !$ap->due_date
                || !$ap->due_date->isPast()
            ) {
                continue;
            }

            $days = $ap->due_date->diffInDays(now());

            if ($days <= 30) {
                $aging['0_30']++;
            } elseif ($days <= 60) {
                $aging['31_60']++;
            } elseif ($days <= 90) {
                $aging['61_90']++;
            } else {
                $aging['90_plus']++;
            }
        }

        $departmentOverdue = Department::with([
            'findingDepartments.actionPlans'
                ])
                ->get()
                ->map(function ($dept) {

                    $count =
                        $dept->findingDepartments
                            ->flatMap->actionPlans
                            ->filter(fn($ap) =>
                                $ap->status !== 'closed'
                                &&
                                $ap->due_date
                                &&
                                $ap->due_date->isPast()
                            )
                            ->count();

                    return [
                        'department' => $dept->name,
                        'count' => $count,
                    ];
                })

                ->filter(fn ($item) =>
                    $item['count'] > 0)
                    
                ->sortByDesc('count')
                ->values();


        $extensionSummary = [

            'total_extensions' =>
                ActionPlanExtension::count(),

            'open' =>
                ActionPlanExtension::where(
                    'status_after_extension',
                    'open'
                )->count(),

            'need_further_review' =>
                ActionPlanExtension::where(
                    'status_after_extension',
                    'need_further_review'
                )->count(),

            'closed' =>
                ActionPlanExtension::where(
                    'status_after_extension',
                    'closed'
                )->count(),
        ];        

        $distribution = [

            [
                "name" => "Open",
                "value" => $summary["open"],
            ],

            [
                "name" => "Need Further Review",
                "value" => $summary["need_further_review"],
            ],

            [
                "name" => "Closed",
                "value" => $summary["closed"],
            ],

        ];

        return response()->json([
            "period" => [

                            "start" =>
                                request("start_date")
                                ??
                                AuditProject::min("release_date"),

                            "end" =>
                                request("end_date")
                                ??
                                AuditProject::max("release_date"),

                        ],
            "total_action_plans" => $totalActionPlans,
            "summary" => $summary,
            "distribution" => $distribution,
            "aging" => $aging,
            "department_overdue" => $departmentOverdue,
            "extension_summary" => $extensionSummary,
        ]);
    }

    public function flagDetails(Request $request)
    {
        $flag = $request->flag;

        $query = ActionPlan::query()
            ->with([
                "findingDepartment.department",
                "findingDepartment.finding",
            ]);

        switch ($flag) {

            case "overdue":

                $query
                    ->whereDate("due_date", "<", today())
                    ->where("status", "!=", "closed");

                break;

            case "submitted":

                $query
                    ->whereJsonContains(
                        "flags",
                        "submitted"
                    );

                break;

            case "revision_required":

                $query
                    ->whereJsonContains(
                        "flags",
                        "revision_required"
                    );

                break;

            case "on_site_validation":

                $query
                    ->whereJsonContains(
                        "flags",
                        "on_site_validation"
                    );

                break;
        }

        $items = $query->get();

        $departmentDistribution =

        $items

        ->groupBy(fn($item) =>
            $item
                ->findingDepartment
                ->department
                ->name
        )

        ->map(function ($rows, $dept) {

            return [

                "department" => $dept,

                "total" => $rows->count(),

            ];

        })

        ->values();

        $days =

        $items

        ->filter(fn($ap) =>

            $ap->due_date &&
            $ap->due_date->isPast()

        )

        ->map(fn($ap) =>

            $ap->due_date->diffInDays(now())

        );

        $summary = [
            "total" => $items->count(),
            "departments" => $departmentDistribution->count(),
            "oldest_days" => (int) ($days->max() ?? 0),
            "average_days" => round( $days->avg() ?? 0 ),
        ];

        return response()->json([
            "summary" => $summary,
            "items" => $items,
            "department_distribution" => array_values(
                $departmentDistribution->toArray()
            ),
        ]);
    }
}