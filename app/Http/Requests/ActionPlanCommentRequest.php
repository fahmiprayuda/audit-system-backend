<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ActionPlanCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['required', 'string'],
            'workflow_action' => ['nullable', 'string', 'in:submitted,revision_required'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:10240'],
        ];
    }
}
