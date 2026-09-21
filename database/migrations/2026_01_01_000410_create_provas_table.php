<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('modelo_prova_id')->constrained('modelos_prova')->restrictOnDelete();
            $table->string('titulo');
            $table->date('data_aplicacao')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->string('status', 20)->default('rascunho')->index();
            $table->string('pdf_path')->nullable();
            $table->foreignId('gerada_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->dateTime('gerada_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['turma_id', 'titulo', 'versao'], 'provas_turma_titulo_versao_unique');
            $table->index(['turma_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provas');
    }
};
