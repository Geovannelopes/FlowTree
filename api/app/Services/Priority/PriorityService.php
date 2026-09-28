<?php

namespace App\Services\Priority;

use App\DTOs\Priority\PriorityUpdateDTO;
use App\Http\Resources\PriorityResource;
use App\Repositories\Priority\PriorityRepository;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PriorityService
{
    public function __construct(
        private PriorityRepository $priorityRepository,
    ) {}

    public function list(): JsonResponse
    {
        return response()->json(['data' => PriorityResource::collection($this->priorityRepository->all())]);
    }

    public function update(string $uuid, PriorityUpdateDTO $dto): JsonResponse
    {
        $priority = $this->priorityRepository->findByUuid($uuid);

        if (! $priority) {
            throw new HttpException(404, 'Prioridade não encontrada!');
        }

        return response()->json(['data' => new PriorityResource($this->priorityRepository->update($priority, $dto))]);
    }
}
