<?php

namespace App\DTOs\Maintenance;

class MaintenanceResetDTO
{
    /** Recria os dados de demonstração (equipe, flags e projetos). */
    public const MODE_DEMO = 'demo';

    /** Apaga tudo e deixa só o administrador que executou. */
    public const MODE_CLEAN = 'clean';

    public const MODES = [self::MODE_DEMO, self::MODE_CLEAN];

    public function __construct(
        public string $mode,
    ) {}

    public static function fromRequest(array $validated): self
    {
        return new self(
            mode: $validated['mode'],
        );
    }

    public function toArray(): array
    {
        return [
            'mode' => $this->mode,
        ];
    }
}
