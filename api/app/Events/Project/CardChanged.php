<?php

namespace App\Events\Project;

/** @param string $action created|updated|moved|deleted */
class CardChanged extends ProjectBroadcastEvent
{
    public function __construct(string $projectUuid, public string $cardUuid, string $action)
    {
        parent::__construct($projectUuid, $action);
    }

    public function broadcastAs(): string
    {
        return 'card.changed';
    }

    public function broadcastWith(): array
    {
        return ['card_uuid' => $this->cardUuid, 'action' => $this->action];
    }
}
