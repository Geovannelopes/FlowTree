<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProjectResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'name' => $this->name,
            'description' => $this->description,
            'color' => $this->color,
            'logo_url' => $this->logo_url,
            'is_archived' => (bool) $this->is_archived,
            'owner' => new UserResource($this->whenLoaded('owner')),
            'members' => UserResource::collection($this->whenLoaded('members')),
            'columns' => BoardColumnResource::collection($this->whenLoaded('columns')),
            'sprints' => SprintResource::collection($this->whenLoaded('sprints')),
            'active_sprint' => $this->whenLoaded('activeSprint', fn () => $this->activeSprint ? new SprintResource($this->activeSprint) : null),
            'cards_count' => $this->whenCounted('cards'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
