<?php

namespace App\Repositories\User;

use App\DTOs\User\UserCreateDTO;
use App\DTOs\User\UserUpdateDTO;
use App\Models\Module;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserRepository
{
    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function findByUuid(string $uuid): ?User
    {
        return User::query()->with('modules')->where('uuid', $uuid)->first();
    }

    public function findById(int $id): ?User
    {
        return User::query()->find($id);
    }

    public function paginate(?string $search): LengthAwarePaginator
    {
        return User::query()
            ->with('modules')
            ->when($search, fn ($query, $term) => $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%");
            }))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();
    }

    /** Usuários ativos, para o seletor de responsável. */
    public function assignable(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'uuid', 'name', 'email', 'position', 'avatar', 'is_admin', 'is_active', 'created_at']);
    }

    public function create(UserCreateDTO $dto): User
    {
        $user = User::create($dto->toArray());

        if ($dto->moduleSlugs !== null) {
            $this->syncModules($user, $dto->moduleSlugs);
        }

        return $user;
    }

    public function update(User $user, UserUpdateDTO $dto): User
    {
        $user->update($dto->toArray());

        if ($dto->moduleSlugs !== null) {
            $this->syncModules($user, $dto->moduleSlugs);
        }

        return $user;
    }

    /** @param  array<int, string>  $slugs */
    public function syncModules(User $user, array $slugs): void
    {
        $user->modules()->sync(Module::query()->whereIn('slug', $slugs)->pluck('id'));
    }

    public function delete(User $user): void
    {
        $user->delete();
    }
}
