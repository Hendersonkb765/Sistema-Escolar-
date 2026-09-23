<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prova_questoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('provas')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->foreignId('questao_id')->constrained('questoes')->restrictOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('professor_id')->constrained('usuarios')->restrictOnDelete();
            $table->decimal('peso', 5, 2);
            $table->text('enunciado_snapshot');
            // Código, imagens e parágrafos do enunciado, congelados junto:
            // mexer na questão depois não pode mudar a prova aplicada.
            $table->json('blocos_snapshot')->nullable();
            $table->json('alternativas_snapshot');
            $table->char('letra_correta', 1);
            $table->unsignedInteger('versao_questao')->default(1);
            $table->timestamps();

            $table->unique(['prova_id', 'numero']);
            $table->unique(['prova_id', 'questao_id']);
            $table->index(['prova_id', 'disciplina_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prova_questoes');
    }
};
