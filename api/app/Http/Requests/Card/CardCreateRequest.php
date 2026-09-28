<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['bail', 'nullable', 'string', 'max:10000'],
            'board_column_uuid' => ['bail', 'required', 'string', Rule::exists('board_columns', 'uuid')],
            'sprint_uuid' => ['bail', 'nullable', 'string', Rule::exists('sprints', 'uuid')],
            'assignee_uuid' => ['bail', 'nullable', 'string', Rule::exists('users', 'uuid')],
            'priority' => ['bail', 'sometimes', 'string', Rule::exists('priorities', 'slug')],
            'due_date' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'flag_uuids' => ['bail', 'sometimes', 'array'],
            'flag_uuids.*' => ['bail', 'string', Rule::exists('flags', 'uuid')],
        ];
    }

    public function messages(): array
    {
        return self::sharedMessages() + [
            'board_column_uuid.required' => 'A coluna é obrigatória!',
        ];
    }

    public static function sharedMessages(): array
    {
        return [
            'title.required' => 'O título do card é obrigatório!',
            'title.max' => 'O título deve ter no máximo 255 caracteres!',
            'description.max' => 'A descrição deve ter no máximo 10000 caracteres!',
            'board_column_uuid.exists' => 'Coluna inválida!',
            'sprint_uuid.exists' => 'Sprint inválida!',
            'assignee_uuid.exists' => 'Responsável inválido!',
            'priority.exists' => 'Prioridade inválida!',
            'due_date.date_format' => 'O prazo deve estar no formato AAAA-MM-DD!',
            'flag_uuids.array' => 'As flags devem ser uma lista!',
            'flag_uuids.*.exists' => 'Flag inválida!',
        ];
    }
}
