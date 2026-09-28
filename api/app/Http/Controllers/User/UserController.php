<?php

namespace App\Http\Controllers\User;

use App\DTOs\User\UserCreateDTO;
use App\DTOs\User\UserUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\User\UserCreateRequest;
use App\Http\Requests\User\UserModulesRequest;
use App\Http\Requests\User\UserUpdateRequest;
use App\Services\User\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->userService->list($request->query('search'));
    }

    public function assignable(): JsonResponse
    {
        return $this->userService->assignable();
    }

    public function show(string $uuid): JsonResponse
    {
        return $this->userService->show($uuid);
    }

    public function store(UserCreateRequest $request): JsonResponse
    {
        return $this->userService->create(UserCreateDTO::fromRequest($request->validated()));
    }

    public function update(UserUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->userService->update($uuid, UserUpdateDTO::fromRequest($request->validated()));
    }

    public function syncModules(UserModulesRequest $request, string $uuid): JsonResponse
    {
        return $this->userService->syncModules($uuid, $request->validated('module_slugs'));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->userService->destroy($uuid);
    }
}
