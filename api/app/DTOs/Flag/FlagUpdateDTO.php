<?php

namespace App\DTOs\Flag;

use Illuminate\Support\Str;

class FlagUpdateDTO
{
    public function __construct(
        public array $attributes,
    ) {}

    public static function fromRequest(array $validated): self
    {
        $attributes = [];

        if (array_key_exists('name', $validated)) {
            $attributes['name'] = $validated['name'];
            $attributes['slug'] = Str::slug($validated['name']);
        }

        if (array_key_exists('color', $validated)) {
            $attributes['color'] = $validated['color'] ?? '#6f8a63';
        }

        return new self($attributes);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
