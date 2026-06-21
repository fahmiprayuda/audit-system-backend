<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionPlan;

class MyTaskController extends Controller
{
    public function index()
    {
        $user = auth()->user()->load('department');

        if (!$user->department_id) {
            return response()->json([
                'message' => 'User has no department'
            ], 400);
        }


        $tasks = ActionPlan::with([
            'findingDepartment.department',
            'findingDepartment.finding'
        ])
        ->whereHas('findingDepartment', function ($q) use ($user) {
            $q->where('department_id', $user->department_id);
        })
        ->latest()
        ->get();

        return response()->json([
            'department' => [
                'id' => $user->department->id,
                'name' => $user->department->name,
            ],


            'summary' => [
                'total' => $tasks->count(),
                'need_further_review' => $tasks->where('status', 'need_further_review')->count(),
                'submitted' => $tasks->where('status', 'submitted')->count(),
                'closed' => $tasks->where('status', 'closed')->count(),
            ],

            'tasks' => $tasks->map(function ($task) {

                return [

                    'id' => $task->id,

                    'finding_id' =>
                        $task->findingDepartment->finding->id,

                    'finding_code' =>
                        $task->findingDepartment->finding->finding_code,

                    'finding_department_id' =>
                        $task->findingDepartment->id,

                    'title' =>
                        $task->findingDepartment->finding->title,

                    'root_cause' =>
                        $task->root_cause,

                    'corrective_action' =>
                        $task->corrective_action,

                    'status' =>
                        $task->status,

                    'due_date' =>
                        $task->due_date,

                ];
            })
        ]);
    }
}