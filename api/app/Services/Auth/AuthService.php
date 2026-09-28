<?php

namespace App\Services\Auth;

use App\DTOs\Auth\AuthLoginDTO;
use App\DTOs\Auth\AuthResetPasswordDTO;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Repositories\User\UserRepository;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    public function login(AuthLoginDTO $dto): JsonResponse
    {
        $user = $this->userRepository->findByEmail($dto->email);

        if (! $user || ! Hash::check($dto->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['As credenciais informadas estão incorretas.'],
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => ['Usuário inativo. Contate o administrador.'],
            ]);
        }

        return $this->tokenResponse(JWTAuth::fromUser($user), $user);
    }

    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = Auth::user();

        return response()->json(['data' => new UserResource($user->load('modules'))]);
    }

    /** Renova o token: blacklista o atual e emite um novo, dentro do refresh_ttl. */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWTAuth::parseToken()->refresh();
            $user = JWTAuth::setToken($token)->toUser();
        } catch (JWTException) {
            return response()->json(['message' => 'Não foi possível renovar a sessão.'], 401);
        }

        if (! $user || ! $user->is_active) {
            return response()->json(['message' => 'Usuário inativo.'], 401);
        }

        return $this->tokenResponse($token, $user);
    }

    public function logout(): JsonResponse
    {
        JWTAuth::invalidate(JWTAuth::getToken());

        return response()->json(null, 204);
    }

    public function forgotPassword(string $email): JsonResponse
    {
        Password::sendResetLink(['email' => $email]);

        // Resposta genérica — não revela se o e-mail existe.
        return response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link para redefinir a senha.',
        ]);
    }

    public function resetPassword(AuthResetPasswordDTO $dto): JsonResponse
    {
        $status = Password::reset(
            $dto->toArray(),
            function (User $user, string $password) {
                $user->forceFill(['password' => $password])->save();
                event(new PasswordReset($user));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => [__($status)]]);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }

    private function tokenResponse(string $token, User $user): JsonResponse
    {
        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl') * 60,
            'user' => new UserResource($user->load('modules')),
        ]);
    }
}
