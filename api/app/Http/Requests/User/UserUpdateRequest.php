<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UserUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'sometimes', 'required', 'string', 'max:255'],
            'email' => ['bail', 'sometimes', 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->route('uuid'), 'uuid')],
            'password' => ['bail', 'nullable', 'string', 'min:8'],
            'position' => ['bail', 'nullable', 'string', 'max:255'],
            'is_admin' => ['bail', 'sometimes', 'boolean'],
            'is_active' => ['bail', 'sometimes', 'boolean'],
            'module_slugs' => ['bail', 'sometimes', 'array'],
            'module_slugs.*' => ['bail', 'string', Rule::exists('modules', 'slug')],
        ];
    }

    public function messages(): array
    {
        return UserCreateRequest::sharedMessages() + [
            'email.required' => 'O e-mail é obrigatório!',
        ];
    }
}
