<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActionPlan;

class MyTaskController extends Controller
{
    public function index()
    {
        $user = auth()->user();

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
            'summary' => [
                'total' => $tasks->count(),
                'draft' => $tasks->where('status', 'draft')->count(),
                'submitted' => $tasks->where('status', 'submitted')->count(),
                'need_revision' => $tasks->where('status', 'need_revision')->count(),
                'approved' => $tasks->where('status', 'approved')->count(),
            ],

            'tasks' => $tasks
        ]);
    }
}