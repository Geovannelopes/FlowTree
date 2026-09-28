<?php

namespace App\Http\Requests\Attachment;

use Illuminate\Foundation\Http\FormRequest;

class AttachmentCreateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['bail', 'required', 'file', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'O arquivo é obrigatório!',
            'file.file' => 'Envie um arquivo válido!',
            'file.max' => 'O arquivo deve ter no máximo 20MB!',
        ];
    }
}
