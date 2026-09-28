<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardMoveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'board_column_uuid' => ['bail', 'required', 'string', Rule::exists('board_columns', 'uuid')],
            'sprint_uuid' => ['bail', 'sometimes', 'nullable', 'string', Rule::exists('sprints', 'uuid')],
            'position' => ['bail', 'required', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'board_column_uuid.required' => 'A coluna de destino é obrigatória!',
            'board_column_uuid.exists' => 'Coluna inválida!',
            'sprint_uuid.exists' => 'Sprint inválida!',
            'position.required' => 'A posição é obrigatória!',
            'position.integer' => 'A posição deve ser um número inteiro!',
            'position.min' => 'A posição deve ser no mínimo 0!',
        ];
    }
}
