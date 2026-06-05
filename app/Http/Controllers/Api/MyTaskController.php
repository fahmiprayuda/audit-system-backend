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
                'draft' => $tasks->where('status', 'draft')->count(),
                'submitted' => $tasks->where('status', 'submitted')->count(),
                'need_revision' => $tasks->where('status', 'need_revision')->count(),
                'approved' => $tasks->where('status', 'approved')->count(),
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

                    'target_date' =>
                        $task->target_date,

                ];
            })
        ]);
    }
}