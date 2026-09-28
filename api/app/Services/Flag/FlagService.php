<?php

namespace App\Services\Flag;

use App\DTOs\Flag\FlagCreateDTO;
use App\DTOs\Flag\FlagUpdateDTO;
use App\Http\Resources\FlagResource;
use App\Models\Flag;
use App\Repositories\Flag\FlagRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class FlagService
{
    public function __construct(
        private FlagRepository $flagRepository,
    ) {}

    public function list(): JsonResponse
    {
        return response()->json(['data' => FlagResource::collection($this->flagRepository->all())]);
    }

    public function create(FlagCreateDTO $dto): JsonResponse
    {
        if ($this->flagRepository->slugExists($dto->toArray()['slug'])) {
            throw ValidationException::withMessages(['name' => ['Já existe uma flag com este nome.']]);
        }

        return response()->json(['data' => new FlagResource($this->flagRepository->create($dto))], 201);
    }

    public function update(string $uuid, FlagUpdateDTO $dto): JsonResponse
    {
        $flag = $this->find($uuid);
        $slug = $dto->toArray()['slug'] ?? null;

        if ($slug && $this->flagRepository->slugExists($slug, $flag->id)) {
            throw ValidationException::withMessages(['name' => ['Já existe uma flag com este nome.']]);
        }

        return response()->json(['data' => new FlagResource($this->flagRepository->update($flag, $dto))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $this->flagRepository->delete($this->find($uuid));

        return response()->json(null, 204);
    }

    private function find(string $uuid): Flag
    {
        $flag = $this->flagRepository->findByUuid($uuid);

        if (! $flag) {
            throw new HttpException(404, 'Flag não encontrada!');
        }

        return $flag;
    }
}
