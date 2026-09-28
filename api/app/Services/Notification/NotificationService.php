<?php

namespace App\Services\Notification;

use App\Http\Resources\NotificationResource;
use App\Models\User;
use App\Repositories\Notification\NotificationRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class NotificationService
{
    public function __construct(
        private NotificationRepository $notificationRepository,
    ) {}

    public function list(): JsonResponse
    {
        return NotificationResource::collection($this->notificationRepository->paginate($this->user()))->response();
    }

    public function unreadCount(): JsonResponse
    {
        return response()->json(['count' => $this->notificationRepository->unreadCount($this->user())]);
    }

    public function readAll(): JsonResponse
    {
        $this->notificationRepository->markAllRead($this->user());

        return response()->json(null, 204);
    }

    public function markRead(string $id): JsonResponse
    {
        $notification = $this->find($id);
        $notification->markAsRead();

        return response()->json(['data' => new NotificationResource($notification)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $this->find($id)->delete();

        return response()->json(null, 204);
    }

    private function find(string $id): DatabaseNotification
    {
        $notification = $this->notificationRepository->find($this->user(), $id);

        if (! $notification) {
            throw new HttpException(404, 'Notificação não encontrada!');
        }

        return $notification;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
