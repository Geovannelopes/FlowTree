<?php

namespace App\DTOs\BoardColumn;

class BoardColumnCreateDTO
{
    public function __construct(
        public string $name,
        public ?string $color,
        public ?int $wipLimit,
        public bool $isDone,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            color: $validated['color'] ?? null,
            wipLimit: isset($validated['wip_limit']) ? (int) $validated['wip_limit'] : null,
            isDone: (bool) ($validated['is_done'] ?? false),
        );
    }

    /** `color` nula = a próxima da paleta (decidida no service). */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'color' => $this->color,
            'wip_limit' => $this->wipLimit,
            'is_done' => $this->isDone,
        ];
    }
}
