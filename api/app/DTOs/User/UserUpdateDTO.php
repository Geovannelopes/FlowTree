<?php

namespace App\DTOs\User;

class UserUpdateDTO
{
    /**
     * Só as chaves presentes no request são atualizadas.
     *
     * @param  array<int, string>|null  $moduleSlugs
     */
    public function __construct(
        public array $attributes,
        public ?array $moduleSlugs,
    ) {}

    public static function fromRequest(array $validated): self
    {
        $attributes = [];

        foreach (['name', 'email', 'position', 'is_admin', 'is_active'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (! empty($validated['password'])) {
            $attributes['password'] = $validated['password'];
        }

        return new self(
            attributes: $attributes,
            moduleSlugs: $validated['module_slugs'] ?? null,
        );
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
