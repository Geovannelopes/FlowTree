<?php

namespace Tests;

use App\Models\BoardColumn;
use App\Models\Module;
use App\Models\Priority;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /** Módulos e prioridades essenciais (o seeder faz o mesmo em produção). */
    protected function seedEssentials(): void
    {
        foreach (['tasks', 'users', 'settings'] as $slug) {
            Module::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)]);
        }

        foreach ([
            ['slug' => 'low', 'name' => 'Baixa', 'color' => '#6f8a63', 'position' => 0],
            ['slug' => 'medium', 'name' => 'Média', 'color' => '#8e744e', 'position' => 1],
            ['slug' => 'high', 'name' => 'Alta', 'color' => '#4c7570', 'position' => 2],
            ['slug' => 'urgent', 'name' => 'Urgente', 'color' => '#a24d3f', 'position' => 3],
        ] as $priority) {
            Priority::firstOrCreate(['slug' => $priority['slug']], $priority);
        }
    }

    /** Usuário com os módulos informados, já autenticado no guard `api`. */
    protected function actingAsUserWith(array $modules = ['tasks'], array $attributes = []): User
    {
        $this->seedEssentials();

        $user = User::factory()->create($attributes);
        $user->modules()->sync(Module::whereIn('slug', $modules)->pluck('id'));

        $this->actingAs($user, 'api');

        return $user;
    }

    /** Projeto com as colunas padrão (Backlog … Concluído). */
    protected function makeProject(User $owner, string $name = 'Demo'): Project
    {
        $project = Project::create(['name' => $name, 'created_by' => $owner->id]);
        $project->columns()->createMany(Project::DEFAULT_COLUMNS);
        $project->members()->sync([$owner->id => ['role' => 'owner']]);

        return $project;
    }

    protected function column(Project $project, string $name): BoardColumn
    {
        return $project->columns()->where('name', $name)->firstOrFail();
    }
}
