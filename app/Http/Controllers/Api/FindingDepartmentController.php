<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FindingDepartment;

class FindingDepartmentController extends Controller
{

// CREATE / ATTACH
public function store(Request $request)
{
    $request->validate([
        'finding_id' => 'required|exists:findings,id',
        'department_id' => 'required|exists:departments,id'
    ]);

    $fd = FindingDepartment::firstOrCreate(
        [
            'finding_id' => $request->finding_id,
            'department_id' => $request->department_id
        ],
        [
            'status' => 'need_further_review' // 🔥 default
        ]
    );

    return response()->json($fd);
}


// 🔥 UPDATE STATUS PER DEPARTMENT (INI KUNCI)
public function updateStatus(Request $request, $id)
{
    $request->validate([
        'status' => 'required|in:open,need_further_review,closed'
    ]);

    $fd = FindingDepartment::findOrFail($id);

    $fd->update([
        'status' => $request->status
    ]);

    return response()->json([
        'message' => 'Status updated',
        'data' => $fd
    ]);
}

public function destroy($id)
{
    $fd = FindingDepartment::findOrFail($id);

    // optional: delete action plans juga (biar clean)
    $fd->actionPlans()->delete();

    $fd->delete();

    return response()->json([
        'message' => 'Department removed from finding'
    ]);
}

}