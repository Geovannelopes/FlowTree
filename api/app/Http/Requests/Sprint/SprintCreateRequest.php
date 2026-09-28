<?php

namespace App\Http\Requests\Sprint;

use Illuminate\Foundation\Http\FormRequest;

class SprintCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100'],
            'goal' => ['bail', 'nullable', 'string', 'max:2000'],
            'starts_at' => ['bail', 'nullable', 'date_format:Y-m-d'],
            'ends_at' => ['bail', 'nullable', 'date_format:Y-m-d', 'after_or_equal:starts_at'],
        ];
    }

    public function messages(): array
    {
        return self::sharedMessages();
    }

    public static function sharedMessages(): array
    {
        return [
            'name.required' => 'O nome da sprint é obrigatório!',
            'name.max' => 'O nome da sprint deve ter no máximo 100 caracteres!',
            'goal.max' => 'O objetivo deve ter no máximo 2000 caracteres!',
            'starts_at.date_format' => 'A data de início deve estar no formato AAAA-MM-DD!',
            'ends_at.date_format' => 'A data de término deve estar no formato AAAA-MM-DD!',
            'ends_at.after_or_equal' => 'O término deve ser igual ou posterior ao início!',
        ];
    }
}
