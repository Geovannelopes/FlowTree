<?php

namespace App\DTOs\Profile;

class ProfileUpdateDTO
{
    public function __construct(
        public string $name,
        public ?string $position,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            position: $validated['position'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'position' => $this->position,
        ];
    }
}
