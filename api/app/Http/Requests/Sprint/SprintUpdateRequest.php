<?php

namespace App\Http\Requests\Sprint;

use Illuminate\Foundation\Http\FormRequest;

class SprintUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:100'],
            'goal' => ['bail', 'sometimes', 'nullable', 'string', 'max:2000'],
            'starts_at' => ['bail', 'sometimes', 'nullable', 'date_format:Y-m-d'],
            'ends_at' => ['bail', 'sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ];
    }

    public function messages(): array
    {
        return SprintCreateRequest::sharedMessages();
    }
}
