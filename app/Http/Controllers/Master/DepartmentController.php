<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends Controller
{
    public function index(Company $company)
    {
        return $company->departments()
            ->orderBy("name")
            ->get();
    }

    public function store(Request $request, Company $company)
    {
        $validated = $request->validate([
            "name" => [
                "required",
                Rule::unique("departments")
                    ->where(fn ($q) => $q->where("company_id", $company->id)),
            ],

            "code" => [
                "required",
                Rule::unique("departments")
                    ->where(fn ($q) => $q->where("company_id", $company->id)),
            ],
        ]);

        $validated["company_id"] = $company->id;

        $department = Department::create($validated);

        return response()->json([
            "message" => "Department created successfully.",
            "data" => $department,
        ], 201);
    }

    public function update(Request $request, Department $department)
    {
        $validated = $request->validate([
            "name" => [
                "required",
                Rule::unique("departments")
                    ->where(fn ($q) => $q->where("company_id", $department->company_id))
                    ->ignore($department->id),
            ],

            "code" => [
                "required",
                Rule::unique("departments")
                    ->where(fn ($q) => $q->where("company_id", $department->company_id))
                    ->ignore($department->id),
            ],
        ]);

        $department->update($validated);

        return response()->json([
            "message" => "Department updated successfully.",
            "data" => $department,
        ]);
    }

    public function destroy(Department $department)
    {
        if ($department->findingDepartments()->exists()) {
            return response()->json([
                "message" => "Department is already used."
            ], 422);
        }

        $department->delete();

        return response()->json([
            "message" => "Department deleted successfully."
        ]);
    }
}