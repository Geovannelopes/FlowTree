<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:255'],
            'email' => ['bail', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['bail', 'required', 'string', 'min:8'],
            'position' => ['bail', 'nullable', 'string', 'max:255'],
            'is_admin' => ['bail', 'sometimes', 'boolean'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'module_slugs' => ['bail', 'sometimes', 'array'],
            'module_slugs.*' => ['bail', 'string', Rule::exists('modules', 'slug')],
        ];
    }

    public function messages(): array
    {
        return self::sharedMessages() + [
            'email.required' => 'O e-mail é obrigatório!',
            'password.required' => 'A senha é obrigatória!',
        ];
    }

    public static function sharedMessages(): array
    {
        return [
            'name.required' => 'O nome é obrigatório!',
            'name.max' => 'O nome deve ter no máximo 255 caracteres!',
            'email.email' => 'Informe um e-mail válido!',
            'email.unique' => 'Este e-mail já está em uso!',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres!',
            'position.max' => 'O cargo deve ter no máximo 255 caracteres!',
            'is_admin.boolean' => 'O campo administrador deve ser verdadeiro ou falso!',
            'is_active.boolean' => 'O campo ativo deve ser verdadeiro ou falso!',
            'module_slugs.array' => 'Os módulos devem ser uma lista!',
            'module_slugs.*.exists' => 'Módulo inválido!',
        ];
    }
}
