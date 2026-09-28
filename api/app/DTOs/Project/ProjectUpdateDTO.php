<?php

namespace App\DTOs\Project;

class ProjectUpdateDTO
{
    /** @param  array<int, string>|null  $memberUuids  null = não mexer nos membros */
    public function __construct(
        public array $attributes,
        public ?array $memberUuids,
    ) {}

    public static function fromRequest(array $validated): self
    {
        $attributes = [];

        foreach (['name', 'description', 'is_archived'] as $field) {
            if (array_key_exists($field, $validated)) {
                $attributes[$field] = $validated[$field];
            }
        }

        if (array_key_exists('color', $validated)) {
            $attributes['color'] = $validated['color'] ?? '#3f6650';
        }

        return new self(
            attributes: $attributes,
            memberUuids: $validated['member_uuids'] ?? null,
        );
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
