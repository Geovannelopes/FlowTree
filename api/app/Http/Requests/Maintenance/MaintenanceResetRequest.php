<?php

namespace App\Http\Requests\Maintenance;

use App\DTOs\Maintenance\MaintenanceResetDTO;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MaintenanceResetRequest extends FormRequest
{
    /** Ação destrutiva: só administradores (o módulo `settings` não basta); o front confirma antes. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_admin;
    }

    protected function failedAuthorization(): void
    {
        throw new HttpException(403, 'Apenas administradores podem resetar o banco de dados!');
    }

    public function rules(): array
    {
        return [
            'mode' => ['bail', 'required', 'string', Rule::in(MaintenanceResetDTO::MODES)],
        ];
    }

    public function messages(): array
    {
        return [
            'mode.required' => 'Informe o tipo de reset!',
            'mode.in' => 'Tipo de reset inválido! Use "demo" ou "clean".',
        ];
    }
}
