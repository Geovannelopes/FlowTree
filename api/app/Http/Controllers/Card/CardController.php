<?php

namespace App\Http\Controllers\Card;

use App\DTOs\Card\CardCreateDTO;
use App\DTOs\Card\CardFilterDTO;
use App\DTOs\Card\CardMoveDTO;
use App\DTOs\Card\CardUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Card\CardCreateRequest;
use App\Http\Requests\Card\CardMoveRequest;
use App\Http\Requests\Card\CardUpdateRequest;
use App\Services\Card\CardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardController extends Controller
{
    public function __construct(
        private CardService $cardService,
    ) {}

    public function index(Request $request, string $projectUuid): JsonResponse
    {
        return $this->cardService->list($projectUuid, CardFilterDTO::fromQuery($request->query()));
    }

    public function show(string $uuid): JsonResponse
    {
        return $this->cardService->show($uuid);
    }

    public function store(CardCreateRequest $request, string $projectUuid): JsonResponse
    {
        return $this->cardService->create($projectUuid, CardCreateDTO::fromRequest($request->validated()));
    }

    public function update(CardUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->cardService->update($uuid, CardUpdateDTO::fromRequest($request->validated()));
    }

    public function move(CardMoveRequest $request, string $uuid): JsonResponse
    {
        return $this->cardService->move($uuid, CardMoveDTO::fromRequest($request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->cardService->destroy($uuid);
    }
}
