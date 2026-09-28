<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:255'],
            'description' => ['bail', 'sometimes', 'nullable', 'string', 'max:2000'],
            'color' => ['bail', 'sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_archived' => ['bail', 'sometimes', 'boolean'],
            'member_uuids' => ['bail', 'sometimes', 'array'],
            'member_uuids.*' => ['bail', 'string', Rule::exists('users', 'uuid')],
        ];
    }

    public function messages(): array
    {
        return ProjectCreateRequest::sharedMessages();
    }
}
