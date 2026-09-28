<?php

namespace App\Http\Controllers\Comment;

use App\DTOs\Comment\CommentCreateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\CommentCreateRequest;
use App\Http\Requests\Comment\CommentUpdateRequest;
use App\Services\Comment\CommentService;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function __construct(
        private CommentService $commentService,
    ) {}

    public function index(string $cardUuid): JsonResponse
    {
        return $this->commentService->list($cardUuid);
    }

    public function store(CommentCreateRequest $request, string $cardUuid): JsonResponse
    {
        return $this->commentService->create($cardUuid, CommentCreateDTO::fromRequest($request->validated()));
    }

    public function update(CommentUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->commentService->update($uuid, CommentCreateDTO::fromRequest($request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->commentService->destroy($uuid);
    }
}
