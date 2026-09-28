<?php

namespace App\Http\Controllers\Auth;

use App\DTOs\Auth\AuthLoginDTO;
use App\DTOs\Auth\AuthResetPasswordDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AuthForgotPasswordRequest;
use App\Http\Requests\Auth\AuthLoginRequest;
use App\Http\Requests\Auth\AuthResetPasswordRequest;
use App\Services\Auth\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $authService,
    ) {}

    public function login(AuthLoginRequest $request): JsonResponse
    {
        return $this->authService->login(AuthLoginDTO::fromRequest($request->validated()));
    }

    public function me(): JsonResponse
    {
        return $this->authService->me();
    }

    public function refresh(): JsonResponse
    {
        return $this->authService->refresh();
    }

    public function logout(): JsonResponse
    {
        return $this->authService->logout();
    }

    public function forgotPassword(AuthForgotPasswordRequest $request): JsonResponse
    {
        return $this->authService->forgotPassword($request->validated('email'));
    }

    public function resetPassword(AuthResetPasswordRequest $request): JsonResponse
    {
        return $this->authService->resetPassword(AuthResetPasswordDTO::fromRequest($request->validated()));
    }
}
