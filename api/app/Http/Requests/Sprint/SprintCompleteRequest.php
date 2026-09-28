<?php

namespace App\Http\Requests\Sprint;

use Illuminate\Foundation\Http\FormRequest;

class SprintCompleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // null = os cards abertos ficam; 'backlog' = voltam ao backlog; uuid = vão para outra sprint.
            'move_open_to' => ['bail', 'nullable', 'string', 'max:40'],
        ];
    }

    public function messages(): array
    {
        return [
            'move_open_to.string' => 'Destino inválido para os cards abertos!',
        ];
    }
}
