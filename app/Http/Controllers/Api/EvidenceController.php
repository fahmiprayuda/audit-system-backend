<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Evidence;

class EvidenceController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'action_plan_id' => 'required|exists:action_plans,id',
            'file' => 'required|file|mimes:jpg,jpeg,png,pdf,doc,docx,xlsx|max:20480'
        ], [
            'file.max' => 'Ukuran file maksimal 20MB',
            'file.mimes' => 'Format file tidak didukung'
        ]);

        $file = $request->file('file');

        $path = $file->store('evidences', 'public');

        $evidence = Evidence::create([
            'action_plan_id' => $request->action_plan_id,
            'file_path' => $path,
            'file_name' => $file->getClientOriginalName(),
            'uploaded_by' => auth()->id()
        ]);

        return response()->json([
            'message' => 'Evidence uploaded successfully',
            'data' => $evidence
        ], 201);
    }
}