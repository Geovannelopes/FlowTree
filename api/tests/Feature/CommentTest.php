<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Module;
use App\Models\User;
use App\Notifications\CardAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_comenta_notifica_e_so_o_autor_edita(): void
    {
        $author = $this->actingAsUserWith(['tasks']);
        $assignee = User::factory()->create();
        $project = $this->makeProject($author);
        $card = $project->cards()->create([
            'board_column_id' => $this->column($project, 'Backlog')->id,
            'title' => 'Card', 'position' => 0, 'assignee_id' => $assignee->id, 'created_by' => $author->id,
        ]);

        $comment = $this->postJson("/api/cards/{$card->uuid}/comments", ['body' => 'Olá'])
            ->assertCreated()->assertJsonPath('data.user.uuid', $author->uuid)->json('data');

        // Responsável avisado; o próprio autor (criador do card) não.
        $this->assertSame(1, $assignee->notifications()->count());
        $this->assertSame('card_commented', $assignee->notifications()->first()->data['type']);
        $this->assertSame(0, $author->notifications()->count());

        $this->getJson("/api/cards/{$card->uuid}/comments")->assertOk()->assertJsonCount(1, 'data');
        $this->putJson("/api/comments/{$comment['uuid']}", ['body' => 'Editado'])->assertOk()->assertJsonPath('data.body', 'Editado');

        // Outro usuário (não admin) não pode editar nem excluir.
        $other = User::factory()->create();
        $other->modules()->sync(Module::where('slug', 'tasks')->pluck('id'));
        $this->actingAs($other, 'api');
        $this->putJson("/api/comments/{$comment['uuid']}", ['body' => 'Hack'])->assertStatus(403);
        $this->deleteJson("/api/comments/{$comment['uuid']}")->assertStatus(403);

        // Admin pode.
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin, 'api');
        $this->deleteJson("/api/comments/{$comment['uuid']}")->assertNoContent();
    }

    public function test_anexos_upload_e_remocao(): void
    {
        Storage::fake('public');
        $user = $this->actingAsUserWith(['tasks']);
        $project = $this->makeProject($user);
        $card = $project->cards()->create(['board_column_id' => $this->column($project, 'Backlog')->id, 'title' => 'Card', 'position' => 0]);

        $attachment = $this->post("/api/cards/{$card->uuid}/attachments", ['file' => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.filename', 'doc.pdf')
            ->json('data');

        Storage::disk('public')->assertExists("attachments/{$card->id}/".basename($attachment['url']));
        $this->getJson("/api/cards/{$card->uuid}")->assertOk()->assertJsonPath('data.attachments_count', 1);

        $this->deleteJson("/api/attachments/{$attachment['uuid']}")->assertNoContent();
        $this->assertSame(0, $card->attachments()->count());
    }

    public function test_notificacoes_listam_e_marcam_lidas(): void
    {
        $user = $this->actingAsUserWith(['tasks']);
        $user->notify(new CardAssigned(
            $this->makeProject($user)->cards()->create(['board_column_id' => BoardColumn::first()->id, 'title' => 'C', 'position' => 0]),
            User::factory()->create(),
        ));

        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 1);
        $id = $this->getJson('/api/notifications')->assertOk()->assertJsonCount(1, 'data')->json('data.0.id');
        $this->postJson("/api/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
        $this->getJson('/api/notifications/unread-count')->assertOk()->assertJsonPath('count', 0);
        $this->deleteJson("/api/notifications/{$id}")->assertNoContent();
    }
}
