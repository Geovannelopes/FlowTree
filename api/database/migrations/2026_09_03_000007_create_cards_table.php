<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cards', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('board_column_id')->constrained('board_columns')->cascadeOnDelete();
            $table->foreignId('sprint_id')->nullable()->constrained()->nullOnDelete(); // null = Backlog
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority', 20)->default('medium'); // slug de priorities
            $table->date('due_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0); // por célula (coluna × sprint)
            $table->timestamps();
            $table->softDeletes();

            $table->index(['board_column_id', 'sprint_id', 'position']);
            $table->index(['project_id', 'sprint_id']);
        });

        Schema::create('card_flag', function (Blueprint $table) {
            $table->id();
            $table->foreignId('card_id')->constrained()->cascadeOnDelete();
            $table->foreignId('flag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['card_id', 'flag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('card_flag');
        Schema::dropIfExists('cards');
    }
};
