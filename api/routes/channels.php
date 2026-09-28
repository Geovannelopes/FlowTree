<?php

use Illuminate\Support\Facades\Broadcast;

/*
 | Canal por projeto (board). Serve como privado (eventos) e como presence
 | (quem está no board): o retorno em array habilita a presença. Mesmo
 | critério das rotas de tarefas: precisa do módulo `tasks` (admin passa).
 */
Broadcast::channel('project.{projectUuid}', function ($user, string $projectUuid) {
    if (! $user->is_active || ! $user->hasModule('tasks')) {
        return false;
    }

    return [
        'id' => $user->id,
        'uuid' => $user->uuid,
        'name' => $user->name,
        'avatar_url' => $user->avatar_url,
    ];
});

/*
 | Canal privado de notificações por usuário (chaveado pelo uuid).
 */
Broadcast::channel('notifications.{userUuid}', function ($user, string $userUuid) {
    return $user->uuid === $userUuid;
});
