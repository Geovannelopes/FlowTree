<?php

namespace App\Http\Requests\Profile;

use Illuminate\Foundation\Http\FormRequest;

class ProfileAvatarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'avatar' => ['bail', 'required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'avatar.required' => 'A imagem é obrigatória!',
            'avatar.image' => 'O arquivo deve ser uma imagem!',
            'avatar.mimes' => 'A imagem deve ser JPG, PNG ou WEBP!',
            'avatar.max' => 'A imagem deve ter no máximo 5MB!',
        ];
    }
}
