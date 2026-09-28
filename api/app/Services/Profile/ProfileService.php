<?php

namespace App\Services\Profile;

use App\DTOs\Profile\ProfileUpdateDTO;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    private const DIRECTORY = 'avatars';

    public function update(ProfileUpdateDTO $dto): JsonResponse
    {
        $user = $this->user();
        $user->update($dto->toArray());

        return $this->respond($user);
    }

    public function updatePassword(string $password): JsonResponse
    {
        $this->user()->update(['password' => $password]);

        return response()->json(['message' => 'Senha alterada com sucesso.']);
    }

    public function updateAvatar(UploadedFile $file): JsonResponse
    {
        $user = $this->user();
        $disk = config('filesystems.default');

        if ($user->avatar) {
            Storage::disk($disk)->delete($user->avatar);
        }

        $user->update(['avatar' => $file->store(self::DIRECTORY.'/'.$user->id, $disk)]);

        return $this->respond($user);
    }

    public function deleteAvatar(): JsonResponse
    {
        $user = $this->user();

        if ($user->avatar) {
            Storage::disk(config('filesystems.default'))->delete($user->avatar);
            $user->update(['avatar' => null]);
        }

        return $this->respond($user);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    private function respond(User $user): JsonResponse
    {
        return response()->json(['data' => new UserResource($user->load('modules'))]);
    }
}
