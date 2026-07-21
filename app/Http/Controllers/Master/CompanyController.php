<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CompanyController extends Controller
{
    public function index()
    {
        return Company::withCount("auditProjects")
            ->orderBy("name")
            ->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            "code" => "required|string|max:10|unique:companies,code",
            "name" => "required|string|max:255|unique:companies,name",
        ]);

        $company = Company::create($validated);

        return response()->json([
            "message" => "Company created successfully.",
            "data" => $company
        ] , 201);
    }

    public function update(Request $request, Company $company)
    {
        $validated = $request->validate([
            "code" => "required|string|max:10|unique:companies,code,".$company->id,
            "name" => "required|string|max:255|unique:companies,name," . $company->id,
        ]);

        $company->update($validated);

        return response()->json([
            "message" => "Company updated successfully.",
            "data" => $company
        ]);
    }

    public function destroy(Company $company)
    {
        if ($company->auditProjects()->exists()) {

            return response()->json([
                "message" => "Company is already used by projects."
            ], 422);

        }

        $company->delete();

        return response()->json([
            "message" => "Company deleted successfully."
        ]);
    }

    public function checkCode(Request $request)
    {

        $exists = Company::where(
            "code",
            strtoupper($request->code)
        )
        
        ->when(
            $request->ignore,
            fn($query) => $query->where("id", "!=", $request->ignore)
        )

        ->exists();

        return response()->json([
            "available" => !$exists,
        ]);
    }
}