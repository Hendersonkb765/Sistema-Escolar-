<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('importacoes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prova_id')->constrained('provas')->restrictOnDelete();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->string('arquivo');
            $table->string('nome_original')->nullable();
            $table->string('hash', 64)->index();
            $table->string('status', 20)->default('pendente')->index();
            $table->json('relatorio')->nullable();
            $table->unsignedInteger('total_linhas')->default(0);
            $table->unsignedInteger('total_erros')->default(0);
            $table->dateTime('confirmada_em')->nullable();
            $table->timestamps();

            $table->index(['prova_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('importacoes');
    }
};
