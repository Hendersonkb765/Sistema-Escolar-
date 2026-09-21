<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_disciplina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resultado_aluno_id')->constrained('resultados_alunos')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->decimal('soma_pesos_acertos', 8, 2)->default(0);
            $table->decimal('soma_pesos_total', 8, 2)->default(0);
            $table->decimal('nota', 4, 2)->default(0);
            $table->timestamps();

            $table->unique(['resultado_aluno_id', 'disciplina_id'], 'notas_resultado_disciplina_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_disciplina');
    }
};
