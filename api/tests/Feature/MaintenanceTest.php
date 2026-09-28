<?php

namespace Tests\Feature;

use App\Models\Card;
use App\Models\Flag;
use App\Models\Priority;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        return $this->actingAsUserWith([], ['is_admin' => true]);
    }

    /** Cenário com dados de trabalho de outro usuário para o reset apagar. */
    private function seedWorkData(User $admin): void
    {
        $other = User::factory()->create();
        $project = $this->makeProject($other, 'Para apagar');
        $project->cards()->create([
            'board_column_id' => $this->column($project, 'Backlog')->id,
            'created_by' => $other->id,
            'title' => 'Card antigo',
            'priority' => 'low',
            'position' => 0,
        ]);
        Flag::create(['name' => 'Bug', 'slug' => 'bug', 'color' => '#ef4444']);
        Priority::where('slug', 'high')->update(['name' => 'Renomeada', 'color' => '#000000']);
        $admin->notifications()->create(['id' => 'n1', 'type' => 'x', 'data' => []]);
    }

    public function test_usuario_com_modulo_settings_mas_nao_admin_recebe_403(): void
    {
        $this->actingAsUserWith(['settings']);

        $this->postJson('/api/maintenance/reset', ['mode' => 'clean'])->assertStatus(403);
    }

    public function test_exige_modo_valido(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/maintenance/reset', ['mode' => 'tudo'])
            ->assertStatus(422)->assertJsonValidationErrors(['mode']);
    }

    public function test_modo_clean_deixa_apenas_o_admin_e_restaura_os_padroes(): void
    {
        Storage::fake(config('filesystems.default'));
        $admin = $this->actingAsAdmin();
        $this->seedWorkData($admin);
        Storage::disk(config('filesystems.default'))->put('attachments/1/arquivo.txt', 'x');

        $this->postJson('/api/maintenance/reset', ['mode' => 'clean'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'clean')
            ->assertJsonPath('data.summary', ['users' => 1, 'projects' => 0, 'cards' => 0]);

        $this->assertSame([$admin->id], User::pluck('id')->all());
        $this->assertSame(0, Project::count());
        $this->assertSame(0, Card::count());
        $this->assertSame(0, Flag::count());
        $this->assertSame(0, $admin->notifications()->count());
        $this->assertSame('Alta', Priority::where('slug', 'high')->value('name'));
        Storage::disk(config('filesystems.default'))->assertMissing('attachments/1/arquivo.txt');

        // O admin continua autenticado e com acesso.
        $this->getJson('/api/me')->assertOk()->assertJsonPath('data.uuid', $admin->uuid);
    }

    public function test_modo_demo_recria_os_dados_de_demonstracao_com_o_admin_atual(): void
    {
        Storage::fake(config('filesystems.default'));
        $admin = $this->actingAsAdmin();
        $this->seedWorkData($admin);

        $response = $this->postJson('/api/maintenance/reset', ['mode' => 'demo'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'demo')
            ->assertJsonPath('data.summary.projects', 5);

        $this->assertGreaterThan(1, $response->json('data.summary.users'));
        $this->assertGreaterThan(0, Card::count());
        $this->assertSame(0, Project::where('name', 'Para apagar')->count());
        $this->assertSame($admin->id, Project::where('name', 'FlowTree Demo')->value('created_by'));
        $this->assertTrue(User::where('email', 'dev@flowtree.test')->exists());
    }
}
