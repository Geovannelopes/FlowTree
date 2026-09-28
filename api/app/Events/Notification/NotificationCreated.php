<?php

namespace App\Events\Notification;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Transmite uma notificação recém-gravada para o seu dono. O payload reusa o
 * NotificationResource — idêntico ao da listagem REST.
 */
class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(
        public User $user,
        public DatabaseNotification $notification,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('notifications.'.$this->user->uuid);
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return (new NotificationResource($this->notification))->resolve();
    }
}
