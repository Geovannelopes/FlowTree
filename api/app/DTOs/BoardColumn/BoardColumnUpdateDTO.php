<?php

namespace App\DTOs\BoardColumn;

class BoardColumnUpdateDTO
{
    public function __construct(
        public array $attributes,
    ) {}

    public static function fromRequest(array $validated): self
    {
        $attributes = [];

        foreach (['name', 'color', 'wip_limit', 'is_done'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('color', $attributes) && $attributes['color'] === null) {
            unset($attributes['color']);
        }

        return new self($attributes);
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
