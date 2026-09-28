<?php

namespace App\Http\Requests\Flag;

use Illuminate\Foundation\Http\FormRequest;

class FlagCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:60'],
            'color' => ['bail', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    public function messages(): array
    {
        return self::sharedMessages();
    }

    public static function sharedMessages(): array
    {
        return [
            'name.required' => 'O nome da flag é obrigatório!',
            'name.max' => 'O nome da flag deve ter no máximo 60 caracteres!',
            'color.regex' => 'A cor deve estar no formato hexadecimal (#RRGGBB)!',
        ];
    }
}
