<?php

namespace App\DTOs\Sprint;

class SprintCreateDTO
{
    public function __construct(
        public string $name,
        public ?string $goal,
        public ?string $startsAt,
        public ?string $endsAt,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            name: $validated['name'],
            goal: $validated['goal'] ?? null,
            startsAt: $validated['starts_at'] ?? null,
            endsAt: $validated['ends_at'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'goal' => $this->goal,
            'starts_at' => $this->startsAt,
            'ends_at' => $this->endsAt,
        ];
    }
}
