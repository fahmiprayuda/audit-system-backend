<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActionPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'finding_department_id' => ['required', 'exists:finding_departments,id'],
            'root_cause' => ['nullable', 'string'],
            'corrective_action' => ['required', 'string'],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
