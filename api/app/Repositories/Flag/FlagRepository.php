<?php

namespace App\Repositories\Flag;

use App\DTOs\Flag\FlagCreateDTO;
use App\DTOs\Flag\FlagUpdateDTO;
use App\Models\Flag;
use Illuminate\Database\Eloquent\Collection;

class FlagRepository
{
    public function all(): Collection
    {
        return Flag::query()->orderBy('name')->get();
    }

    public function findByUuid(string $uuid): ?Flag
    {
        return Flag::query()->where('uuid', $uuid)->first();
    }

    public function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        return Flag::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists();
    }

    public function create(FlagCreateDTO $dto): Flag
    {
        return Flag::create($dto->toArray());
    }

    public function update(Flag $flag, FlagUpdateDTO $dto): Flag
    {
        $flag->update($dto->toArray());

        return $flag;
    }

    public function delete(Flag $flag): void
    {
        $flag->delete();
    }
}
