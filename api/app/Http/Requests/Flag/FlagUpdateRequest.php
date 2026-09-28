<?php

namespace App\Http\Requests\Flag;

use Illuminate\Foundation\Http\FormRequest;

class FlagUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:60'],
            'color' => ['bail', 'sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return FlagCreateRequest::sharedMessages();
    }
}
