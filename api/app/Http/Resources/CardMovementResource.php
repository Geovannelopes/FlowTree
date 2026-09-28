<?php

namespace App\Http\Resources;

use App\Models\BoardColumn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CardMovementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'from_column' => $this->column($this->fromColumn),
            'to_column' => $this->column($this->toColumn),
            'user' => $this->user ? new UserResource($this->user) : null,
            'created_at' => $this->created_at,
        ];
    }

    private function column(?BoardColumn $column): ?array
    {
        if (! $column) {
            return null;
        }

        return ['uuid' => $column->uuid, 'name' => $column->name, 'color' => $column->color];
    }
}
