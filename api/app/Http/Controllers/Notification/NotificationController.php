<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Services\Notification\NotificationService;
use Illuminate\Http\JsonResponse;

class NotificationController extends Controller
{
    public function __construct(
        private NotificationService $notificationService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->notificationService->list();
    }

    public function unreadCount(): JsonResponse
    {
        return $this->notificationService->unreadCount();
    }

    public function readAll(): JsonResponse
    {
        return $this->notificationService->readAll();
    }

    public function markRead(string $id): JsonResponse
    {
        return $this->notificationService->markRead($id);
    }

    public function destroy(string $id): JsonResponse
    {
        return $this->notificationService->destroy($id);
    }
}
