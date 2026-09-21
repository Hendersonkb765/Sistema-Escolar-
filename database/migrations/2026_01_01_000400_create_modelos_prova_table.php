<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelos_prova', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eixo_id')->constrained('eixos')->restrictOnDelete();
            $table->string('nome');
            $table->string('nome_avaliacao')->nullable();
            $table->text('cabecalho')->nullable();
            $table->string('logo_path')->nullable();
            $table->json('campos_identificacao')->nullable();
            $table->json('layout')->nullable();
            $table->text('rodape')->nullable();
            $table->unsignedInteger('versao')->default(1);
            $table->boolean('ativo')->default(true);
            $table->foreignId('criado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['eixo_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos_prova');
    }
};
