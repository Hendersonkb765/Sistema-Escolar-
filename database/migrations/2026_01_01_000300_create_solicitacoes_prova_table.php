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
            $table->foreignId('criado_por')->constrained('usuarios')->restrictOnDelete();
            $table->string('titulo')->nullable();
            // Uma solicitação cobre a prova inteira; cada disciplina e seu
            // professor entram como uma parte (solicitacao_partes).
            $table->unsignedTinyInteger('quantidade_alternativas');
            $table->dateTime('prazo');
            $table->dateTime('encerrada_em')->nullable();
            $table->dateTime('cancelada_em')->nullable();
            $table->string('status', 20)->default('aberta')->index();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['turma_id', 'status']);
            $table->index('prazo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacoes_prova');
    }
};
