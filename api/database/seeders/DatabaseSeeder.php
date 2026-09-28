<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(EssentialsSeeder::class);
        $admin = $this->seedAdminUser();

        if (app()->environment('production')) {
            $this->command?->info("Seed de produção: admin {$admin->email} pronto (sem dados de exemplo).");

            return;
        }

        $this->call(DemoSeeder::class);

        $this->command?->info("Seed completo. Admin: {$admin->email} / equipe: dev@flowtree.test, ana@, bruno@, carla@, diego@, elisa@, felipe@ (senha: password)");
    }

    /** Admin com credenciais do .env; não sobrescreve a senha em re-seeds. */
    private function seedAdminUser(): User
    {
        return User::firstOrCreate(
            ['email' => config('admin.email')],
            [
                'name' => config('admin.name'),
                'password' => Hash::make(config('admin.password')),
                'position' => 'Gestor',
                'is_admin' => true,
                'is_active' => true,
            ],
        );
    }
}
