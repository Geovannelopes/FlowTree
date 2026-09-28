<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SprintResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'goal' => $this->goal,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'status' => $this->status,
            'position' => $this->position,
            'completed_at' => $this->completed_at,
            'cards_count' => $this->whenCounted('cards'),
            'done_count' => $this->whenCounted('doneCards'),
        ];
    }
}
