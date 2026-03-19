<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\FindingDepartment;

class FindingDepartmentController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'finding_id' => 'required|exists:findings,id',
        'department_id' => 'required|exists:departments,id'
    ]);

    // 🔥 PAKAI ATAU BUAT
    $fd = FindingDepartment::firstOrCreate([
        'finding_id' => $request->finding_id,
        'department_id' => $request->department_id
    ]);

    return response()->json($fd);
}
}