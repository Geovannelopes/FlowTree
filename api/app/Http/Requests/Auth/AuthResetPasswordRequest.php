<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AuthResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['bail', 'required', 'string'],
            'email' => ['bail', 'required', 'string', 'email'],
            'password' => ['bail', 'required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'O token é obrigatório!',
            'email.required' => 'O e-mail é obrigatório!',
            'email.email' => 'Informe um e-mail válido!',
            'password.required' => 'A senha é obrigatória!',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres!',
            'password.confirmed' => 'A confirmação da senha não confere!',
        ];
    }
}
