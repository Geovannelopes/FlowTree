<?php

namespace App\Http\Requests\BoardColumn;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BoardColumnReorderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'column_uuids' => ['bail', 'required', 'array', 'min:1'],
            'column_uuids.*' => ['bail', 'string', Rule::exists('board_columns', 'uuid')],
        ];
    }

    public function messages(): array
    {
        return [
            'column_uuids.required' => 'Informe a ordem das colunas!',
            'column_uuids.array' => 'A ordem das colunas deve ser uma lista!',
            'column_uuids.*.exists' => 'Coluna inválida!',
        ];
    }
}
