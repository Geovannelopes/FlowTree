<?php

namespace App\Services\Comment;

use App\DTOs\Comment\CommentCreateDTO;
use App\Events\Project\CommentChanged;
use App\Http\Resources\CommentResource;
use App\Models\Card;
use App\Models\Comment;
use App\Models\User;
use App\Notifications\CardCommented;
use App\Repositories\Comment\CommentRepository;
use App\Repositories\User\UserRepository;
use App\Services\Card\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CommentService
{
    public function __construct(
        private CommentRepository $commentRepository,
        private UserRepository $userRepository,
        private CardService $cardService,
    ) {}

    public function list(string $cardUuid): JsonResponse
    {
        $card = $this->cardService->find($cardUuid);

        return response()->json(['data' => CommentResource::collection($this->commentRepository->listForCard($card))]);
    }

    public function create(string $cardUuid, CommentCreateDTO $dto): JsonResponse
    {
        $card = $this->cardService->find($cardUuid);
        /** @var User $actor */
        $actor = Auth::user();

        $comment = $this->commentRepository->create($card, $dto, $actor->id);

        $this->notify($card, $comment, $actor);
        $this->broadcast($card, $comment, 'created');

        return response()->json(['data' => new CommentResource($comment->load('user'))], 201);
    }

    public function update(string $uuid, CommentCreateDTO $dto): JsonResponse
    {
        $comment = $this->find($uuid);
        $this->assertOwner($comment);

        $this->commentRepository->update($comment, $dto);
        $this->broadcast($comment->card, $comment, 'updated');

        return response()->json(['data' => new CommentResource($comment->load('user'))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $comment = $this->find($uuid);
        $this->assertOwner($comment);

        $this->commentRepository->delete($comment);
        $this->broadcast($comment->card, $comment, 'deleted');

        return response()->json(null, 204);
    }

    /** Responsável e criador do card são avisados (exceto quem comentou). */
    private function notify(Card $card, Comment $comment, User $actor): void
    {
        $card->loadMissing('project');

        $recipients = collect([$card->assignee_id, $card->created_by])
            ->filter()
            ->unique()
            ->reject(fn ($id) => (int) $id === $actor->id);

        foreach ($recipients as $id) {
            $this->userRepository->findById((int) $id)?->notify(new CardCommented($card, $comment, $actor));
        }
    }

    private function assertOwner(Comment $comment): void
    {
        /** @var User $user */
        $user = Auth::user();

        if ($comment->user_id !== $user->id && ! $user->is_admin) {
            throw new HttpException(403, 'Você só pode alterar os seus próprios comentários!');
        }
    }

    private function broadcast(Card $card, Comment $comment, string $action): void
    {
        $card->loadMissing('project');
        broadcast(new CommentChanged($card->project->uuid, $card->uuid, $comment->uuid, $action))->toOthers();
    }

    private function find(string $uuid): Comment
    {
        $comment = $this->commentRepository->findByUuid($uuid);

        if (! $comment) {
            throw new HttpException(404, 'Comentário não encontrado!');
        }

        return $comment;
    }
}
