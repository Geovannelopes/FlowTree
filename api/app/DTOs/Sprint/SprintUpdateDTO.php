<?php

namespace App\DTOs\Sprint;

class SprintUpdateDTO
{
    public function __construct(
        public array $attributes,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(array_intersect_key($validated, array_flip(['name', 'goal', 'starts_at', 'ends_at'])));
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
