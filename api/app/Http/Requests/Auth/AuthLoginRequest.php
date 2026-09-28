<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class AuthLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['bail', 'required', 'string', 'email'],
            'password' => ['bail', 'required', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'O e-mail é obrigatório!',
            'email.email' => 'Informe um e-mail válido!',
            'password.required' => 'A senha é obrigatória!',
        ];
    }
}
