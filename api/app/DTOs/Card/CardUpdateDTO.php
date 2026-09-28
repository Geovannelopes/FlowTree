<?php

namespace App\DTOs\Card;

class CardUpdateDTO
{
    /**
     * Só as chaves presentes no request são aplicadas. As referências por uuid
     * ficam separadas para o service resolver (`false` = não informado).
     *
     * @param  array<int, string>|null  $flagUuids
     */
    public function __construct(
        public array $attributes,
        public string|false $boardColumnUuid,
        public string|null|false $sprintUuid,
        public string|null|false $assigneeUuid,
        public ?array $flagUuids,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            attributes: array_intersect_key($validated, array_flip(['title', 'description', 'priority', 'due_date'])),
            boardColumnUuid: array_key_exists('board_column_uuid', $validated) ? $validated['board_column_uuid'] : false,
            sprintUuid: array_key_exists('sprint_uuid', $validated) ? $validated['sprint_uuid'] : false,
            assigneeUuid: array_key_exists('assignee_uuid', $validated) ? $validated['assignee_uuid'] : false,
            flagUuids: $validated['flag_uuids'] ?? null,
        );
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
