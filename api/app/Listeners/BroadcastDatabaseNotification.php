<?php

namespace App\Listeners;

use App\Events\Notification\NotificationCreated;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Sempre que uma notificação é gravada no banco (canal `database`), reemite-a
 * em tempo real via Reverb para o usuário dono.
 */
class BroadcastDatabaseNotification
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        $record = $event->response;
        $notifiable = $event->notifiable;

        if (! $record instanceof DatabaseNotification || ! $notifiable instanceof User) {
            return;
        }

        broadcast(new NotificationCreated($notifiable, $record));
    }
}
