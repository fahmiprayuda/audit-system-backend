<?php
    
namespace App\Http\Controllers\Api;
    
use App\Http\Controllers\Controller;
use App\Models\ActionPlan;
use App\Models\Department;
use App\Models\ActionPlanExtension;

use Illuminate\Http\Request;

class ActionPlanMonitoringController extends Controller
{
    public function index()
    {
        $actionPlans = ActionPlan::all();

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


        return response()->json([
            'summary' => $summary,
            'aging' => $aging,
            'department_overdue' => $departmentOverdue,
            'extension_summary' => $extensionSummary,
        ]);
    }
}