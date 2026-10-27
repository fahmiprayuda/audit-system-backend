<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionPlan;

use Illuminate\Http\Request;

class MyTaskController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user()->load('department.company');

        if (!$user->department_id) {
            return response()->json([
                'message' => 'User has no department'
            ], 400);
        }

        $request->validate(['queue' => 'nullable|in:all,new,waiting,revision,site,overdue,closed']);

        $query = ActionPlan::with([
            'findingDepartment.department.company',
            'findingDepartment.finding.project'
        ])
        ->where('status', '!=', 'closed')
        ->whereHas('findingDepartment', function ($q) use ($user) {
            $q->where('department_id', $user->department_id);
        });

        // Semua task untuk summary //
        $allTasks = $query
                ->latest()
                ->get()
                ->reject(fn ($task) => $task->status === 'closed')
                ->values();

        // Task yang ditampilkan pada table //
        $tasks = $allTasks;

        if ($request->filled('queue') && $request->queue !== 'all') {
            $tasks = $tasks
                ->filter(fn ($task) =>
                    $task->queue === $request->queue
                )
                ->values();
        }

        $summary = [
            'all'       => $allTasks->count(),
            'new'       => $allTasks->where('queue', 'new')->count(),
            'revision'  => $allTasks->where('queue', 'revision')->count(),
            'waiting'   => $allTasks->where('queue', 'waiting')->count(),
            'site'      => $allTasks->where('queue', 'site')->count(),
            'overdue'   => $allTasks->where('queue', 'overdue')->count(),
        ];

        return response()->json([
            'company' => $user->department?->company ? [
                'id' => $user->department->company->id,
                'name' => $user->department->company->name,
                'code' => $user->department->company->code,
            ] : null,

            'department' => [
                'id' => $user->department->id,
                'name' => $user->department->name,
                'code' => $user->department->code,
            ],

            'summary' => $summary,

            'tasks' => $tasks->map(function ($task) {
                $finding = $task->findingDepartment?->finding;
                return [
                    'id' => $task->id,
                    'finding_id' => $finding?->id,
                    'finding_code' => $finding?->finding_code,
                    'finding_department_id' => $task->findingDepartment?->id,
                    'title' => $finding?->title,
                    'risk_rating' => $finding?->risk_rating ?? 'Moderate',
                    'risk_category' => $finding?->risk_category ?? 'Moderate',
                    'project_name' => $finding?->project?->project_name,
                    'project_code' => $finding?->project?->project_code,
                    'root_cause' => $task->root_cause,
                    'corrective_action' => $task->corrective_action,
                    'status' => $task->status,
                    'queue' => $task->queue,
                    'flags' => $task->flags,
                    'primary_flag' => $task->primary_flag,
                    'due_date' => $task->due_date,
                ];
            })
        ]);
    }
}