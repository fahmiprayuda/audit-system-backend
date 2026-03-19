<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Evidence;
use Illuminate\Support\Facades\Storage;

class EvidenceController extends Controller
{
    public function store(Request $request)
{
    $request->validate([
        'action_plan_id' => 'required|exists:action_plans,id',
        'file' => 'required|file|max:5120'
    ]);

    $path = $request->file('file')->store('evidences', 'public');

    $evidence = Evidence::create([
        'action_plan_id' => $request->action_plan_id,
        'file_path' => $path,
        'uploaded_by' => auth()->id() ?? 1
    ]);

    return response()->json([
        'message' => 'Evidence uploaded successfully',
        'data' => $evidence
    ], 201);
}
}
