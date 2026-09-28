<?php

namespace App\DTOs\Project;

class ProjectCreateDTO
{
    /** @param  array<int, string>  $memberUuids */
    public function __construct(
        public string $name,
        public ?string $description,
        public string $color,
        public array $memberUuids = [],
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            description: $validated['description'] ?? null,
            color: $validated['color'] ?? '#3f6650',
            memberUuids: $validated['member_uuids'] ?? [],
        );
    }

    /** Só as colunas do projeto: `memberUuids` não é atributo. */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
        ];
    }
}
