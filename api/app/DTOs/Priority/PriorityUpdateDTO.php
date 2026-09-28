<?php

namespace App\DTOs\Priority;

class PriorityUpdateDTO
{
    public function __construct(
        public array $attributes,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(array_intersect_key($validated, array_flip(['name', 'color'])));
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
