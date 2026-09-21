<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('professor_disciplina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('turma_id')->nullable()->constrained('turmas')->restrictOnDelete();
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['usuario_id', 'disciplina_id', 'turma_id'], 'prof_disc_turma_unique');
            $table->index(['disciplina_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('professor_disciplina');
    }
};
