<?php

namespace App\Repositories\Maintenance;

use App\Models\Card;
use App\Models\Flag;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaintenanceRepository
{
    /**
     * Apaga todos os dados de trabalho, mantendo apenas o usuário informado.
     * Projetos levam junto (cascade) colunas, sprints, cards, movimentos,
     * comentários, anexos e membros; usuários levam os vínculos com módulos.
     * Módulos e prioridades ficam (o EssentialsSeeder restaura os padrões).
     */
    public function wipeExcept(User $keep): void
    {
        DB::table('notifications')->delete();
        Project::query()->delete();
        Flag::query()->delete();
        User::where('id', '!=', $keep->id)->delete();
        DB::table('password_reset_tokens')->delete();
    }

    /** @return array{users: int, projects: int, cards: int} */
    public function summary(): array
    {
        return [
            'users' => User::count(),
            'projects' => Project::count(),
            'cards' => Card::count(),
        ];
    }
}
