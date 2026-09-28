<?php

namespace App\DTOs\Card;

class CardMoveDTO
{
    /** @param  string|null|false  $sprintUuid  false = manter a sprint atual */
    public function __construct(
        public string $boardColumnUuid,
        public string|null|false $sprintUuid,
        public int $position,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            boardColumnUuid: $validated['board_column_uuid'],
            sprintUuid: array_key_exists('sprint_uuid', $validated) ? $validated['sprint_uuid'] : false,
            position: (int) $validated['position'],
        );
    }
}
