<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class ProjectLogoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // `allow_svg`: a regra `image` bloqueia SVG por padrão a partir do Laravel 11.
            'logo' => ['bail', 'required', 'file', 'image:allow_svg', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.required' => 'O logo é obrigatório!',
            'logo.image' => 'O arquivo deve ser uma imagem!',
            'logo.mimes' => 'O logo deve ser JPG, PNG, WEBP ou SVG!',
            'logo.max' => 'O logo deve ter no máximo 5MB!',
        ];
    }
}
