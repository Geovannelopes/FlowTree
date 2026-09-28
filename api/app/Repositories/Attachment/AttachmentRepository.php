<?php

namespace App\Repositories\Attachment;

use App\Models\Attachment;
use App\Models\Card;
use Illuminate\Database\Eloquent\Collection;

class AttachmentRepository
{
    public function listForCard(Card $card): Collection
    {
        return $card->attachments()->with('user')->get();
    }

    public function findByUuid(string $uuid): ?Attachment
    {
        return Attachment::query()->with(['card.project', 'user'])->where('uuid', $uuid)->first();
    }

    public function create(Card $card, array $attributes): Attachment
    {
        return $card->attachments()->create($attributes);
    }

    public function delete(Attachment $attachment): void
    {
        $attachment->delete();
    }
}
