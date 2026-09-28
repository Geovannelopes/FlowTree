<?php

namespace App\Services\User;

use App\DTOs\User\UserCreateDTO;
use App\DTOs\User\UserUpdateDTO;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\User\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function list(?string $search): JsonResponse
    {
        return UserResource::collection($this->userRepository->paginate($search))->response();
    }

    public function assignable(): JsonResponse
    {
        return response()->json(['data' => UserResource::collection($this->userRepository->assignable())]);
    }

    public function show(string $uuid): JsonResponse
    {
        return $this->respond($this->find($uuid));
    }

    public function create(UserCreateDTO $dto): JsonResponse
    {
        $user = $this->userRepository->create($dto);

        return $this->respond($user, 201);
    }

    public function update(string $uuid, UserUpdateDTO $dto): JsonResponse
    {
        $user = $this->find($uuid);

        // Ninguém tira o próprio acesso de admin nem se desativa.
        if ($user->id === Auth::id()) {
            if (array_key_exists('is_admin', $dto->attributes) && ! $dto->attributes['is_admin'] && $user->is_admin) {
                throw new HttpException(422, 'Você não pode remover o seu próprio acesso de administrador!');
            }
            if (array_key_exists('is_active', $dto->attributes) && ! $dto->attributes['is_active']) {
                throw new HttpException(422, 'Você não pode desativar a sua própria conta!');
            }
        }

        $user = $this->userRepository->update($user, $dto);

        return $this->respond($user);
    }

    public function syncModules(string $uuid, array $slugs): JsonResponse
    {
        $user = $this->find($uuid);
        $this->userRepository->syncModules($user, $slugs);

        return $this->respond($user);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $user = $this->find($uuid);

        if ($user->id === Auth::id()) {
            throw new HttpException(422, 'Você não pode excluir a sua própria conta!');
        }

        $this->userRepository->delete($user);

        return response()->json(null, 204);
    }

    private function find(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user) {
            throw new HttpException(404, 'Usuário não encontrado!');
        }

        return $user;
    }

    private function respond(User $user, int $status = 200): JsonResponse
    {
        return response()->json(['data' => new UserResource($user->load('modules'))], $status);
    }
}
