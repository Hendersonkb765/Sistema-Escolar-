<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resultados_alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('provas')->restrictOnDelete();
            $table->foreignId('aluno_id')->constrained('alunos')->restrictOnDelete();
            $table->foreignId('importacao_id')->constrained('importacoes')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['prova_id', 'aluno_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resultados_alunos');
    }
};
