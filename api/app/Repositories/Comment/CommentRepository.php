<?php

namespace App\Repositories\Comment;

use App\DTOs\Comment\CommentCreateDTO;
use App\Models\Card;
use App\Models\Comment;
use Illuminate\Database\Eloquent\Collection;

class CommentRepository
{
    public function listForCard(Card $card): Collection
    {
        return $card->comments()->with('user')->get();
    }

    public function findByUuid(string $uuid): ?Comment
    {
        return Comment::query()->with(['card.project', 'user'])->where('uuid', $uuid)->first();
    }

    public function create(Card $card, CommentCreateDTO $dto, int $userId): Comment
    {
        return $card->comments()->create([...$dto->toArray(), 'user_id' => $userId]);
    }

    public function update(Comment $comment, CommentCreateDTO $dto): Comment
    {
        $comment->update($dto->toArray());

        return $comment;
    }

    public function delete(Comment $comment): void
    {
        $comment->delete();
    }
}
