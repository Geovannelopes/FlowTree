<?php

namespace App\Http\Requests\Card;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['bail', 'sometimes', 'required', 'string', 'max:255'],
            'description' => ['bail', 'sometimes', 'nullable', 'string', 'max:10000'],
            'board_column_uuid' => ['bail', 'sometimes', 'required', 'string', Rule::exists('board_columns', 'uuid')],
            'sprint_uuid' => ['bail', 'sometimes', 'nullable', 'string', Rule::exists('sprints', 'uuid')],
            'assignee_uuid' => ['bail', 'sometimes', 'nullable', 'string', Rule::exists('users', 'uuid')],
            'priority' => ['bail', 'sometimes', 'string', Rule::exists('priorities', 'slug')],
            'due_date' => ['bail', 'sometimes', 'nullable', 'date_format:Y-m-d'],
            'flag_uuids' => ['bail', 'sometimes', 'array'],
            'flag_uuids.*' => ['bail', 'string', Rule::exists('flags', 'uuid')],
        ];
    }

    public function messages(): array
    {
        return CardCreateRequest::sharedMessages() + [
            'board_column_uuid.required' => 'A coluna é obrigatória!',
        ];
    }
}
