<?php

namespace App\Http\Requests\BoardColumn;

use Illuminate\Foundation\Http\FormRequest;

class BoardColumnCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100'],
            'color' => ['bail', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'wip_limit' => ['bail', 'nullable', 'integer', 'min:1', 'max:999'],
            'is_done' => ['bail', 'sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return self::sharedMessages();
    }

    public static function sharedMessages(): array
    {
        return [
            'name.required' => 'O nome da coluna é obrigatório!',
            'name.max' => 'O nome da coluna deve ter no máximo 100 caracteres!',
            'color.regex' => 'A cor deve estar no formato hexadecimal (#RRGGBB)!',
            'wip_limit.integer' => 'O limite de WIP deve ser um número inteiro!',
            'wip_limit.min' => 'O limite de WIP deve ser no mínimo 1!',
            'wip_limit.max' => 'O limite de WIP deve ser no máximo 999!',
            'is_done.boolean' => 'O campo "concluído" deve ser verdadeiro ou falso!',
        ];
    }
}
