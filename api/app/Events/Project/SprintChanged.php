<?php

namespace App\Events\Project;

/** @param string $action created|updated|deleted|activated|completed|reopened|reordered|assigned */
class SprintChanged extends ProjectBroadcastEvent
{
    public function __construct(string $projectUuid, public ?string $sprintUuid, string $action)
    {
        parent::__construct($projectUuid, $action);
    }

    public function broadcastAs(): string
    {
        return 'sprint.changed';
    }

    public function broadcastWith(): array
    {
        return ['sprint_uuid' => $this->sprintUuid, 'action' => $this->action];
    }
}
