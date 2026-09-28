<?php

namespace App\Services\Maintenance;

use App\DTOs\Maintenance\MaintenanceResetDTO;
use App\Models\User;
use App\Repositories\Maintenance\MaintenanceRepository;
use Database\Seeders\DemoSeeder;
use Database\Seeders\EssentialsSeeder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MaintenanceService
{
    /** Pastas do disco público com arquivos enviados pelos usuários. */
    private const UPLOAD_DIRECTORIES = ['attachments', 'project-logos', 'avatars'];

    public function __construct(
        private MaintenanceRepository $maintenanceRepository,
    ) {}

    /**
     * Reset do banco: apaga tudo, menos o admin que executou, e recria os
     * essenciais. No modo `demo` semeia também os dados de demonstração.
     */
    public function reset(User $admin, MaintenanceResetDTO $dto): JsonResponse
    {
        DB::transaction(fn () => $this->maintenanceRepository->wipeExcept($admin));

        // Antes de semear: o DemoSeeder gera anexos novos no disco.
        $this->purgeUploads($admin);

        DB::transaction(function () use ($dto) {
            Artisan::call('db:seed', ['--class' => EssentialsSeeder::class, '--force' => true]);

            if ($dto->mode === MaintenanceResetDTO::MODE_DEMO) {
                Artisan::call('db:seed', ['--class' => DemoSeeder::class, '--force' => true]);
            }
        });

        return response()->json(['data' => [
            'mode' => $dto->mode,
            'summary' => $this->maintenanceRepository->summary(),
        ]]);
    }

    /** Remove os arquivos enviados, preservando o avatar do admin mantido. */
    private function purgeUploads(User $admin): void
    {
        $disk = Storage::disk(config('filesystems.default'));

        foreach (self::UPLOAD_DIRECTORIES as $directory) {
            foreach ($disk->directories($directory) as $subdirectory) {
                if ($admin->avatar && str_starts_with($admin->avatar, $subdirectory.'/')) {
                    continue;
                }
                $disk->deleteDirectory($subdirectory);
            }
        }
    }
}
