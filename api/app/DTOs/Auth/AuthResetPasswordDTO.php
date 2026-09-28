<?php

namespace App\DTOs\Auth;

class AuthResetPasswordDTO
{
    public function __construct(
        public string $token,
        public string $email,
        public string $password,
        public string $passwordConfirmation,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            token: $validated['token'],
            email: $validated['email'],
            password: $validated['password'],
            passwordConfirmation: $validated['password_confirmation'] ?? $validated['password'],
        );
    }

    /** Credenciais no formato esperado pelo Password broker. */
    public function toArray(): array
    {
        return [
            'token' => $this->token,
            'email' => $this->email,
            'password' => $this->password,
            'password_confirmation' => $this->passwordConfirmation,
        ];
    }
}
