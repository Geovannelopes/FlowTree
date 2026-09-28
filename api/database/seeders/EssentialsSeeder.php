<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Priority;
use Illuminate\Database\Seeder;

/**
 * Módulos e prioridades: o app precisa deles em qualquer ambiente. Idempotente e
 * restaura os valores padrão (usado também no reset do banco pela tela de configurações).
 */
class EssentialsSeeder extends Seeder
{
    public const MODULES = [
        ['name' => 'Tarefas (Kanban)', 'slug' => 'tasks', 'description' => 'Projetos, sprints e cards.'],
        ['name' => 'Usuários', 'slug' => 'users', 'description' => 'Gestão de usuários e acessos.'],
        ['name' => 'Configurações', 'slug' => 'settings', 'description' => 'Flags e prioridades.'],
    ];

    public const PRIORITIES = [
        ['slug' => 'low', 'name' => 'Baixa', 'color' => '#22c55e', 'position' => 0],
        ['slug' => 'medium', 'name' => 'Média', 'color' => '#0ea5e9', 'position' => 1],
        ['slug' => 'high', 'name' => 'Alta', 'color' => '#f59e0b', 'position' => 2],
        ['slug' => 'urgent', 'name' => 'Urgente', 'color' => '#ef4444', 'position' => 3],
    ];

    public function run(): void
    {
        foreach (self::MODULES as $module) {
            Module::updateOrCreate(['slug' => $module['slug']], $module);
        }

        foreach (self::PRIORITIES as $priority) {
            Priority::updateOrCreate(['slug' => $priority['slug']], $priority);
        }
    }
}
