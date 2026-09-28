<?php

namespace App\Events\Project;

/** @param string $action created|updated|deleted|reordered */
class ColumnChanged extends ProjectBroadcastEvent
{
    public function __construct(string $projectUuid, public ?string $columnUuid, string $action)
    {
        parent::__construct($projectUuid, $action);
    }

    public function broadcastAs(): string
    {
        return 'column.changed';
    }

    public function broadcastWith(): array
    {
        return ['column_uuid' => $this->columnUuid, 'action' => $this->action];
    }
}
