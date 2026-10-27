<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'audit_project_id' => ['required', 'exists:audit_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'risk_rating' => ['nullable', 'string', 'in:Extreme,Major,Moderate,Low'],
            'departments' => ['nullable', 'array'],
            'departments.*' => ['exists:departments,id'],
            'action_plans' => ['nullable', 'array'],
            'action_plans.*.department_id' => ['required_with:action_plans', 'exists:departments,id'],
            'action_plans.*.corrective_action' => ['required_with:action_plans', 'string'],
            'action_plans.*.root_cause' => ['nullable', 'string'],
            'action_plans.*.due_date' => ['nullable', 'date'],
        ];
    }
}
