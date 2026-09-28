<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_sem_o_modulo_recebe_403(): void
    {
        $this->actingAsUserWith(['tasks']);

        $this->getJson('/api/users')->assertStatus(403);
        $this->postJson('/api/flags', ['name' => 'X'])->assertStatus(403);
        $this->getJson('/api/projects')->assertOk();
    }

    public function test_admin_passa_em_todos_os_modulos(): void
    {
        $this->actingAsUserWith([], ['is_admin' => true]);

        $this->getJson('/api/users')->assertOk();
        $this->getJson('/api/projects')->assertOk();
        $this->postJson('/api/flags', ['name' => 'Bug', 'color' => '#a24d3f'])->assertCreated()->assertJsonPath('data.slug', 'bug');
    }

    public function test_usuario_inativo_recebe_401_nas_rotas_de_modulo(): void
    {
        $this->seedEssentials();
        $user = User::factory()->inactive()->create();
        $this->actingAs($user, 'api');

        $this->getJson('/api/projects')->assertStatus(401);
    }

    public function test_admin_gerencia_usuarios_e_modulos(): void
    {
        $admin = $this->actingAsUserWith([], ['is_admin' => true]);

        $created = $this->postJson('/api/users', [
            'name' => 'Novo', 'email' => 'novo@x.com', 'password' => 'secret123', 'module_slugs' => ['tasks'],
        ])->assertCreated()->assertJsonPath('data.modules.0.slug', 'tasks');

        $uuid = $created->json('data.uuid');

        $this->putJson("/api/users/{$uuid}/modules", ['module_slugs' => ['tasks', 'settings']])
            ->assertOk()->assertJsonCount(2, 'data.modules');

        $this->deleteJson("/api/users/{$admin->uuid}")->assertStatus(422);
        $this->deleteJson("/api/users/{$uuid}")->assertNoContent();
    }
}
