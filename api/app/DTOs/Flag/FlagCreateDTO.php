<?php

namespace App\DTOs\Flag;

use Illuminate\Support\Str;

class FlagCreateDTO
{
    public function __construct(
        public string $name,
        public string $color,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            color: $validated['color'] ?? '#6f8a63',
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'slug' => Str::slug($this->name),
            'color' => $this->color,
        ];
    }
}
