<?php

namespace App\Events\Project;

/** @param string $action created|updated|deleted */
class CommentChanged extends ProjectBroadcastEvent
{
    public function __construct(string $projectUuid, public string $cardUuid, public string $commentUuid, string $action)
    {
        parent::__construct($projectUuid, $action);
    }

    public function broadcastAs(): string
    {
        return 'comment.changed';
    }

    public function broadcastWith(): array
    {
        return ['card_uuid' => $this->cardUuid, 'comment_uuid' => $this->commentUuid, 'action' => $this->action];
    }
}
