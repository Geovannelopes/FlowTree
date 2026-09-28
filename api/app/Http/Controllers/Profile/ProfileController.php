<?php

namespace App\Http\Controllers\Profile;

use App\DTOs\Profile\ProfileUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Profile\ProfileAvatarRequest;
use App\Http\Requests\Profile\ProfilePasswordRequest;
use App\Http\Requests\Profile\ProfileUpdateRequest;
use App\Services\Profile\ProfileService;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService,
    ) {}

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        return $this->profileService->update(ProfileUpdateDTO::fromRequest($request->validated()));
    }

    public function updatePassword(ProfilePasswordRequest $request): JsonResponse
    {
        return $this->profileService->updatePassword($request->validated('password'));
    }

    public function updateAvatar(ProfileAvatarRequest $request): JsonResponse
    {
        return $this->profileService->updateAvatar($request->file('avatar'));
    }

    public function deleteAvatar(): JsonResponse
    {
        return $this->profileService->deleteAvatar();
    }
}
