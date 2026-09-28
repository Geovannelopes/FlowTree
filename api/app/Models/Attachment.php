<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    use HasUuid;

    protected $fillable = [
        'card_id',
        'comment_id',
        'user_id',
        'disk',
        'path',
        'filename',
        'mime_type',
        'size',
    ];

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): ?string
    {
        return MediaUrl::resolve($this->path, $this->disk);
    }
}
