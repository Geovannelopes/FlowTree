<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'project_id' => $this->project_id,
            'board_column_id' => $this->board_column_id,
            'board_column_uuid' => $this->whenLoaded('column', fn () => $this->column?->uuid),
            'sprint_id' => $this->sprint_id,
            'sprint_uuid' => $this->whenLoaded('sprint', fn () => $this->sprint?->uuid, null),
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority,
            'due_date' => $this->due_date?->toDateString(),
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'position' => $this->position,
            'is_overdue' => $this->is_overdue,
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? new UserResource($this->assignee) : null),
            'creator' => $this->whenLoaded('creator', fn () => $this->creator ? new UserResource($this->creator) : null),
            'flags' => FlagResource::collection($this->whenLoaded('flags')),
            'comments' => CommentResource::collection($this->whenLoaded('comments')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'movements' => CardMovementResource::collection($this->whenLoaded('movements')),
            'comments_count' => $this->whenCounted('comments'),
            'attachments_count' => $this->whenCounted('attachments'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
