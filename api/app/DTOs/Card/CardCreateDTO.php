<?php

namespace App\DTOs\Card;

class CardCreateDTO
{
    /** @param  array<int, string>|null  $flagUuids */
    public function __construct(
        public string $title,
        public ?string $description,
        public string $boardColumnUuid,
        public ?string $sprintUuid,
        public ?string $assigneeUuid,
        public string $priority,
        public ?string $dueDate,
        public ?array $flagUuids,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            title: $validated['title'],
            description: $validated['description'] ?? null,
            boardColumnUuid: $validated['board_column_uuid'],
            sprintUuid: $validated['sprint_uuid'] ?? null,
            assigneeUuid: $validated['assignee_uuid'] ?? null,
            priority: $validated['priority'] ?? 'medium',
            dueDate: $validated['due_date'] ?? null,
            flagUuids: $validated['flag_uuids'] ?? null,
        );
    }

    /** Atributos diretos do card (as referências por uuid são resolvidas no service). */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'due_date' => $this->dueDate,
        ];
    }
}
