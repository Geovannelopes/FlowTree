<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'card_id' => $this->card_id,
            'comment_id' => $this->comment_id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => (int) $this->size,
            'url' => $this->url,
            'user' => $this->whenLoaded('user', fn () => $this->user ? new UserResource($this->user) : null),
            'created_at' => $this->created_at,
        ];
    }
}
