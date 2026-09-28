<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Entre requests do mesmo teste o guard mantém o usuário/token resolvidos
     * (a app não é recriada). Limpa para o próximo request reautenticar do zero.
     */
    private function forgetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        // O guard usa `tymon.jwt` e a facade `tymon.jwt.auth`: instâncias distintas.
        $this->app['tymon.jwt']->unsetToken();
        $this->app['tymon.jwt.auth']->unsetToken();
    }

    public function test_login_retorna_token_e_usuario(): void
    {
        $this->seedEssentials();
        $user = User::factory()->create(['email' => 'a@b.com', 'password' => 'secret123']);

        $response = $this->postJson('/api/login', ['email' => 'a@b.com', 'password' => 'secret123'])
            ->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'expires_in', 'user' => ['uuid', 'name', 'email', 'modules']]);

        $this->assertSame($user->uuid, $response->json('user.uuid'));
        $this->assertSame('Bearer', $response->json('token_type'));
    }

    public function test_login_com_senha_errada_retorna_422(): void
    {
        User::factory()->create(['email' => 'a@b.com', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'a@b.com', 'password' => 'errada'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('email');
    }

    public function test_usuario_inativo_nao_loga(): void
    {
        User::factory()->inactive()->create(['email' => 'a@b.com', 'password' => 'secret123']);

        $this->postJson('/api/login', ['email' => 'a@b.com', 'password' => 'secret123'])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('email');
    }

    public function test_me_exige_token_e_devolve_o_usuario(): void
    {
        $this->getJson('/api/me')->assertStatus(401);

        $user = $this->actingAsUserWith(['tasks']);

        $this->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.uuid', $user->uuid)
            ->assertJsonPath('data.modules.0.slug', 'tasks');
    }

    public function test_refresh_emite_novo_token_e_logout_invalida(): void
    {
        User::factory()->create(['email' => 'a@b.com', 'password' => 'secret123']);

        $token = $this->postJson('/api/login', ['email' => 'a@b.com', 'password' => 'secret123'])->json('token');

        $refreshed = $this->withToken($token)->postJson('/api/refresh')->assertOk()->json('token');
        $this->assertNotSame($token, $refreshed);

        // O token antigo entrou na blacklist.
        $this->forgetAuth();
        $this->withToken($token)->getJson('/api/me')->assertStatus(401);

        $this->forgetAuth();
        $this->withToken($refreshed)->postJson('/api/logout')->assertNoContent();
        $this->forgetAuth();
        $this->withToken($refreshed)->getJson('/api/me')->assertStatus(401);
    }
}
