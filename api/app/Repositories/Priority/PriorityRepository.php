<?php

namespace App\Repositories\Priority;

use App\DTOs\Priority\PriorityUpdateDTO;
use App\Models\Priority;
use Illuminate\Database\Eloquent\Collection;

class PriorityRepository
{
    public function all(): Collection
    {
        return Priority::query()->orderBy('position')->get();
    }

    public function findByUuid(string $uuid): ?Priority
    {
        return Priority::query()->where('uuid', $uuid)->first();
    }

    public function update(Priority $priority, PriorityUpdateDTO $dto): Priority
    {
        $priority->update($dto->toArray());

        return $priority;
    }
}
