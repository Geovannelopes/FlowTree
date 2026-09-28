<?php

namespace App\Http\Requests\Priority;

use Illuminate\Foundation\Http\FormRequest;

class PriorityUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:60'],
            'color' => ['bail', 'sometimes', 'required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'O nome da prioridade é obrigatório!',
            'name.max' => 'O nome da prioridade deve ter no máximo 60 caracteres!',
            'color.required' => 'A cor é obrigatória!',
            'color.regex' => 'A cor deve estar no formato hexadecimal (#RRGGBB)!',
        ];
    }
}
