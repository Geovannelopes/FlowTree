<?php

namespace App\DTOs\User;

class UserCreateDTO
{
    /** @param  array<int, string>|null  $moduleSlugs */
    public function __construct(
        public string $name,
        public string $email,
        public string $password,
        public ?string $position,
        public bool $isAdmin,
        public bool $isActive,
        public ?array $moduleSlugs,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            email: $validated['email'],
            password: $validated['password'],
            position: $validated['position'] ?? null,
            isAdmin: (bool) ($validated['is_admin'] ?? false),
            isActive: (bool) ($validated['is_active'] ?? true),
            moduleSlugs: $validated['module_slugs'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'position' => $this->position,
            'is_admin' => $this->isAdmin,
            'is_active' => $this->isActive,
        ];
    }
}
