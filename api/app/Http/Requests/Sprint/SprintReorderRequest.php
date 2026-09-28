<?php

namespace App\Http\Requests\Sprint;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SprintReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sprint_uuids' => ['bail', 'required', 'array', 'min:1'],
            'sprint_uuids.*' => ['bail', 'string', Rule::exists('sprints', 'uuid')],
        ];
    }

    public function messages(): array
    {
        return [
            'sprint_uuids.required' => 'Informe a ordem das sprints!',
            'sprint_uuids.array' => 'A ordem das sprints deve ser uma lista!',
            'sprint_uuids.*.exists' => 'Sprint inválida!',
        ];
    }
}
