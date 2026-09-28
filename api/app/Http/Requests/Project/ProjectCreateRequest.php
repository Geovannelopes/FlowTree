<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProjectCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'description' => ['bail', 'nullable', 'string', 'max:2000'],
            'color' => ['bail', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'member_uuids' => ['bail', 'sometimes', 'array'],
            'member_uuids.*' => ['bail', 'string', Rule::exists('users', 'uuid')],
        ];
    }

    public function messages(): array
    {
        return self::sharedMessages();
    }

    public static function sharedMessages(): array
    {
        return [
            'name.required' => 'O nome do projeto é obrigatório!',
            'name.max' => 'O nome do projeto deve ter no máximo 255 caracteres!',
            'description.max' => 'A descrição deve ter no máximo 2000 caracteres!',
            'color.regex' => 'A cor deve estar no formato hexadecimal (#RRGGBB)!',
            'member_uuids.array' => 'Os membros devem ser uma lista!',
            'member_uuids.*.exists' => 'Membro inválido!',
            'is_archived.boolean' => 'O campo arquivado deve ser verdadeiro ou falso!',
        ];
    }
}
