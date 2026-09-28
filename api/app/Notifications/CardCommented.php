<?php

namespace App\Notifications;

use App\Models\Card;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Alguém comentou num card do usuário (responsável ou criador). */
class CardCommented extends Notification
{
    use Queueable;

    public function __construct(
        public Card $card,
        public Comment $comment,
        public User $actor,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'card_commented',
            'card_uuid' => $this->card->uuid,
            'card_title' => $this->card->title,
            'project_uuid' => $this->card->project->uuid,
            'project_name' => $this->card->project->name,
            'actor_uuid' => $this->actor->uuid,
            'actor_name' => $this->actor->name,
            'comment_excerpt' => Str::limit($this->comment->body, 120),
        ];
    }
}
