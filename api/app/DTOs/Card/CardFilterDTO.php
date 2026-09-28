<?php

namespace App\DTOs\Card;

class CardFilterDTO
{
    public function __construct(
        public ?string $columnUuid,
        public ?string $sprint,
        public ?string $assigneeUuid,
        public ?string $priority,
        public ?string $flagUuid,
        public ?string $search,
    ) {}

    public static function fromQuery(array $query): self
    {
        return new self(
            columnUuid: $query['column'] ?? null,
            sprint: $query['sprint'] ?? null,
            assigneeUuid: $query['assignee'] ?? null,
            priority: $query['priority'] ?? null,
            flagUuid: $query['flag'] ?? null,
            search: $query['search'] ?? null,
        );
    }
}
