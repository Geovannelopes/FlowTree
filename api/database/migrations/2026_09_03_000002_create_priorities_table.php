<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Prioridades como tabela de lookup (rótulo + cor + ordem) dos 4 níveis fixos.
// `cards.priority` guarda o slug; esta tabela só guarda a apresentação.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('priorities', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('color', 7)->default('#64748b');
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['slug' => 'low', 'name' => 'Baixa', 'color' => '#22c55e', 'position' => 0],
            ['slug' => 'medium', 'name' => 'Média', 'color' => '#0ea5e9', 'position' => 1],
            ['slug' => 'high', 'name' => 'Alta', 'color' => '#f59e0b', 'position' => 2],
            ['slug' => 'urgent', 'name' => 'Urgente', 'color' => '#ef4444', 'position' => 3],
        ] as $priority) {
            DB::table('priorities')->insert([
                ...$priority,
                'uuid' => (string) Str::uuid(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('priorities');
    }
};
