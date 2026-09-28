<?php

namespace App\Services\Attachment;

use App\Events\Project\AttachmentChanged;
use App\Http\Resources\AttachmentResource;
use App\Models\Card;
use App\Repositories\Attachment\AttachmentRepository;
use App\Services\Card\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AttachmentService
{
    private const DIRECTORY = 'attachments';

    public function __construct(
        private AttachmentRepository $attachmentRepository,
        private CardService $cardService,
    ) {}

    public function list(string $cardUuid): JsonResponse
    {
        $card = $this->cardService->find($cardUuid);

        return response()->json(['data' => AttachmentResource::collection($this->attachmentRepository->listForCard($card))]);
    }

    public function create(string $cardUuid, UploadedFile $file): JsonResponse
    {
        $card = $this->cardService->find($cardUuid);
        $disk = config('filesystems.default');

        $attachment = $this->attachmentRepository->create($card, [
            'user_id' => Auth::id(),
            'disk' => $disk,
            'path' => $file->store(self::DIRECTORY.'/'.$card->id, $disk),
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize(),
        ]);

        $this->broadcast($card, 'created');

        return response()->json(['data' => new AttachmentResource($attachment->load('user'))], 201);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $attachment = $this->attachmentRepository->findByUuid($uuid);

        if (! $attachment) {
            throw new HttpException(404, 'Anexo não encontrado!');
        }

        Storage::disk($attachment->disk)->delete($attachment->path);
        $this->attachmentRepository->delete($attachment);

        $this->broadcast($attachment->card, 'deleted');

        return response()->json(null, 204);
    }

    private function broadcast(Card $card, string $action): void
    {
        $card->loadMissing('project');
        broadcast(new AttachmentChanged($card->project->uuid, $card->uuid, $action))->toOthers();
    }
}
