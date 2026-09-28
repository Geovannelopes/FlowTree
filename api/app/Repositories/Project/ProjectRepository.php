<?php

namespace App\Repositories\Project;

use App\DTOs\Project\ProjectCreateDTO;
use App\DTOs\Project\ProjectUpdateDTO;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ProjectRepository
{
    public function list(bool $archived, ?string $search): Collection
    {
        return Project::query()
            ->with(['owner', 'activeSprint'])
            ->withCount('cards')
            ->where('is_archived', $archived)
            ->when($search, fn ($query, $term) => $query->where('name', 'like', "%{$term}%"))
            ->orderBy('name')
            ->get();
    }

    public function findByUuid(string $uuid): ?Project
    {
        return Project::query()->where('uuid', $uuid)->first();
    }

    /** Projeto com tudo que a tela de detalhe/edição precisa. */
    public function loadDetails(Project $project): Project
    {
        return $project->load(['owner', 'members', 'columns', 'sprints', 'activeSprint'])->loadCount('cards');
    }

    /**
     * Board: colunas com seus cards no recorte de sprint.
     * `$sprintId`: 'all' = todos, null = backlog, int = sprint específica.
     */
    public function loadBoard(Project $project, int|string|null $sprintId): Project
    {
        return $project->load([
            'columns.cards' => fn ($query) => $query
                ->when($sprintId !== 'all', fn ($q) => $q->when(
                    $sprintId === null,
                    fn ($s) => $s->whereNull('sprint_id'),
                    fn ($s) => $s->where('sprint_id', $sprintId),
                ))
                ->with(['assignee', 'flags', 'column', 'sprint'])
                ->withCount(['comments', 'attachments']),
            'sprints' => fn ($query) => $query->withCount(['cards', 'doneCards']),
        ]);
    }

    public function create(ProjectCreateDTO $dto, User $creator): Project
    {
        /** @var Project $project */
        $project = Project::create([...$dto->toArray(), 'created_by' => $creator->id]);

        $project->columns()->createMany(Project::DEFAULT_COLUMNS);

        $this->syncMembers($project, $dto->memberUuids, $creator);

        return $project;
    }

    public function update(Project $project, ProjectUpdateDTO $dto): Project
    {
        $project->update($dto->toArray());

        if ($dto->memberUuids !== null) {
            $this->syncMembers($project, $dto->memberUuids, $project->owner);
        }

        return $project;
    }

    /** Membros por uuid; o dono é sempre mantido com role `owner`. */
    public function syncMembers(Project $project, array $memberUuids, ?User $owner): void
    {
        $members = User::query()
            ->whereIn('uuid', $memberUuids)
            ->pluck('id')
            ->mapWithKeys(fn ($id) => [$id => ['role' => 'member']])
            ->all();

        if ($owner) {
            $members[$owner->id] = ['role' => 'owner'];
        }

        $project->members()->sync($members);
    }

    public function delete(Project $project): void
    {
        $project->delete();
    }
}
