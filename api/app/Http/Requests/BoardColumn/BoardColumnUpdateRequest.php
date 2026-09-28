<?php

namespace App\Http\Requests\BoardColumn;

use Illuminate\Foundation\Http\FormRequest;

class BoardColumnUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:100'],
            'color' => ['bail', 'sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'wip_limit' => ['bail', 'sometimes', 'nullable', 'integer', 'min:1', 'max:999'],
            'is_done' => ['bail', 'sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return BoardColumnCreateRequest::sharedMessages();
    }
}
