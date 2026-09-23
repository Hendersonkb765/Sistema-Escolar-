<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes_prova')->restrictOnDelete();
            $table->foreignId('solicitacao_parte_id')->constrained('solicitacao_partes')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('professor_id')->constrained('usuarios')->restrictOnDelete();
            $table->text('enunciado')->nullable();
            $table->decimal('peso', 5, 2);
            $table->string('status', 20)->default('rascunho')->index();
            $table->unsignedInteger('versao')->default(1);
            $table->dateTime('enviada_em')->nullable();
            $table->dateTime('analisada_em')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['solicitacao_parte_id', 'ordem']);
            $table->index(['solicitacao_id', 'status']);
            $table->index(['disciplina_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questoes');
    }
};
