<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BoardColumnResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'color' => $this->color,
            'position' => $this->position,
            'wip_limit' => $this->wip_limit,
            'is_done' => (bool) $this->is_done,
            'cards' => CardResource::collection($this->whenLoaded('cards')),
            'cards_count' => $this->whenCounted('cards'),
        ];
    }
}
