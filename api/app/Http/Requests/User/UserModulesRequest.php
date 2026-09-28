<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'module_slugs' => ['bail', 'present', 'array'],
            'module_slugs.*' => ['bail', 'string', Rule::exists('modules', 'slug')],
        ];
    }

    public function messages(): array
    {
        return [
            'module_slugs.present' => 'Informe a lista de módulos!',
            'module_slugs.array' => 'Os módulos devem ser uma lista!',
            'module_slugs.*.exists' => 'Módulo inválido!',
        ];
    }
}
