<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('respostas_alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resultado_aluno_id')->constrained('resultados_alunos')->cascadeOnDelete();
            $table->foreignId('prova_questao_id')->constrained('prova_questoes')->restrictOnDelete();
            $table->char('alternativa_marcada', 1)->nullable();
            $table->boolean('acertou')->default(false);
            $table->decimal('peso', 5, 2);
            $table->decimal('pontuacao', 6, 2)->default(0);
            $table->timestamps();

            $table->unique(['resultado_aluno_id', 'prova_questao_id'], 'respostas_resultado_questao_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('respostas_alunos');
    }
};
