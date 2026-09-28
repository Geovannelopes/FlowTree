<?php

namespace App\Events\Project;

/** @param string $action created|deleted */
class AttachmentChanged extends ProjectBroadcastEvent
{
    public function __construct(string $projectUuid, public string $cardUuid, string $action)
    {
        parent::__construct($projectUuid, $action);
    }

    public function broadcastAs(): string
    {
        return 'attachment.changed';
    }

    public function broadcastWith(): array
    {
        return ['card_uuid' => $this->cardUuid, 'action' => $this->action];
    }
}
