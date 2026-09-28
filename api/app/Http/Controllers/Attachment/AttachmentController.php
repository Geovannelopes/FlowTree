<?php

namespace App\Http\Controllers\Attachment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attachment\AttachmentCreateRequest;
use App\Services\Attachment\AttachmentService;
use Illuminate\Http\JsonResponse;

class AttachmentController extends Controller
{
    public function __construct(
        private AttachmentService $attachmentService,
    ) {}

    public function index(string $cardUuid): JsonResponse
    {
        return $this->attachmentService->list($cardUuid);
    }

    public function store(AttachmentCreateRequest $request, string $cardUuid): JsonResponse
    {
        return $this->attachmentService->create($cardUuid, $request->file('file'));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->attachmentService->destroy($uuid);
    }
}
