<?php

use App\Http\Controllers\Attachment\AttachmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BoardColumn\BoardColumnController;
use App\Http\Controllers\Card\CardController;
use App\Http\Controllers\Comment\CommentController;
use App\Http\Controllers\Flag\FlagController;
use App\Http\Controllers\Maintenance\MaintenanceController;
use App\Http\Controllers\Metric\MetricController;
use App\Http\Controllers\Module\ModuleController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Priority\PriorityController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Sprint\SprintController;
use App\Http\Controllers\User\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Públicas
|--------------------------------------------------------------------------
*/
Route::middleware('throttle:auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});

// Renovação do JWT — aceita token expirado (dentro do refresh_ttl); sem auth:api.
Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('throttle:api');

/*
|--------------------------------------------------------------------------
| Autenticadas (JWT)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:api', 'throttle:api'])->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Autorização de canais privados/presence do Reverb via JWT (sem sessão).
    Route::post('/broadcasting/auth', fn (Request $request) => Broadcast::auth($request));

    // Perfil próprio
    Route::put('/me', [ProfileController::class, 'update']);
    Route::put('/me/password', [ProfileController::class, 'updatePassword']);
    Route::post('/me/avatar', [ProfileController::class, 'updateAvatar']);
    Route::delete('/me/avatar', [ProfileController::class, 'deleteAvatar']);

    Route::get('/modules', [ModuleController::class, 'index']);

    // Notificações
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    /*
    |----------------------------------------------------------------------
    | Módulo: USUÁRIOS
    |----------------------------------------------------------------------
    */
    Route::middleware('module:users')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{uuid}', [UserController::class, 'show']);
        Route::put('/{uuid}', [UserController::class, 'update']);
        Route::delete('/{uuid}', [UserController::class, 'destroy']);
        Route::put('/{uuid}/modules', [UserController::class, 'syncModules']);
    });

    /*
    |----------------------------------------------------------------------
    | Módulo: CONFIGURAÇÕES (flags, prioridades e manutenção)
    |----------------------------------------------------------------------
    */
    Route::middleware('module:settings')->group(function () {
        Route::post('/flags', [FlagController::class, 'store']);
        Route::put('/flags/{uuid}', [FlagController::class, 'update']);
        Route::delete('/flags/{uuid}', [FlagController::class, 'destroy']);

        Route::put('/priorities/{uuid}', [PriorityController::class, 'update']);

        // Reset do banco (só admin — o FormRequest exige is_admin).
        Route::post('/maintenance/reset', [MaintenanceController::class, 'reset']);
    });

    /*
    |----------------------------------------------------------------------
    | Módulo: TAREFAS / KANBAN
    |----------------------------------------------------------------------
    */
    Route::middleware('module:tasks')->group(function () {
        Route::get('/assignable-users', [UserController::class, 'assignable']);
        Route::get('/flags', [FlagController::class, 'index']);
        Route::get('/priorities', [PriorityController::class, 'index']);

        // Projetos (boards)
        Route::prefix('projects')->group(function () {
            Route::get('/', [ProjectController::class, 'index']);
            Route::post('/', [ProjectController::class, 'store']);
            Route::get('/{uuid}', [ProjectController::class, 'show']);
            Route::put('/{uuid}', [ProjectController::class, 'update']);
            Route::delete('/{uuid}', [ProjectController::class, 'destroy']);
            Route::post('/{uuid}/logo', [ProjectController::class, 'uploadLogo']);
            Route::delete('/{uuid}/logo', [ProjectController::class, 'deleteLogo']);
            Route::get('/{uuid}/board', [ProjectController::class, 'board']);
            Route::get('/{uuid}/metrics', [MetricController::class, 'project']);

            // Colunas
            Route::get('/{projectUuid}/columns', [BoardColumnController::class, 'index']);
            Route::post('/{projectUuid}/columns', [BoardColumnController::class, 'store']);
            Route::patch('/{projectUuid}/columns/reorder', [BoardColumnController::class, 'reorder']);

            // Sprints
            Route::get('/{projectUuid}/sprints', [SprintController::class, 'index']);
            Route::post('/{projectUuid}/sprints', [SprintController::class, 'store']);
            Route::patch('/{projectUuid}/sprints/reorder', [SprintController::class, 'reorder']);

            // Cards
            Route::get('/{projectUuid}/cards', [CardController::class, 'index']);
            Route::post('/{projectUuid}/cards', [CardController::class, 'store']);
        });

        Route::put('/columns/{uuid}', [BoardColumnController::class, 'update']);
        Route::delete('/columns/{uuid}', [BoardColumnController::class, 'destroy']);

        Route::put('/sprints/{uuid}', [SprintController::class, 'update']);
        Route::delete('/sprints/{uuid}', [SprintController::class, 'destroy']);
        Route::post('/sprints/{uuid}/activate', [SprintController::class, 'activate']);
        Route::post('/sprints/{uuid}/complete', [SprintController::class, 'complete']);
        Route::post('/sprints/{uuid}/reopen', [SprintController::class, 'reopen']);
        Route::post('/sprints/{uuid}/assign-backlog', [SprintController::class, 'assignBacklog']);
        Route::get('/sprints/{uuid}/export', [SprintController::class, 'export']);

        Route::get('/cards/{uuid}', [CardController::class, 'show']);
        Route::put('/cards/{uuid}', [CardController::class, 'update']);
        Route::patch('/cards/{uuid}/move', [CardController::class, 'move']);
        Route::delete('/cards/{uuid}', [CardController::class, 'destroy']);

        // Comentários
        Route::get('/cards/{cardUuid}/comments', [CommentController::class, 'index']);
        Route::post('/cards/{cardUuid}/comments', [CommentController::class, 'store']);
        Route::put('/comments/{uuid}', [CommentController::class, 'update']);
        Route::delete('/comments/{uuid}', [CommentController::class, 'destroy']);

        // Anexos
        Route::get('/cards/{cardUuid}/attachments', [AttachmentController::class, 'index']);
        Route::post('/cards/{cardUuid}/attachments', [AttachmentController::class, 'store']);
        Route::delete('/attachments/{uuid}', [AttachmentController::class, 'destroy']);
    });
});
