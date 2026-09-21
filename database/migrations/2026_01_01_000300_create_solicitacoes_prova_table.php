<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacoes_prova', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('professor_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('criado_por')->constrained('usuarios')->restrictOnDelete();
            $table->unsignedSmallInteger('quantidade_questoes');
            $table->unsignedTinyInteger('quantidade_alternativas');
            $table->dateTime('prazo');
            $table->dateTime('enviada_em')->nullable();
            $table->boolean('enviada_em_atraso')->default(false);
            $table->dateTime('encerrada_em')->nullable();
            $table->dateTime('cancelada_em')->nullable();
            $table->string('status', 20)->default('aberta')->index();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['professor_id', 'status']);
            $table->index(['turma_id', 'disciplina_id']);
            $table->index('prazo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_prova');
    }
};
