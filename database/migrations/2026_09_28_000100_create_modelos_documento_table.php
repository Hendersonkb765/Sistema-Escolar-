<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Modelos de documento do aluno: autorização dos pais, declaração,
 * ficha de entrega — o que a escola precisar imprimir com os dados de
 * cada aluno e espaços para preencher à mão.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modelos_documento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eixo_id')->constrained('eixos')->restrictOnDelete();
            $table->string('nome');
            $table->string('descricao')->nullable();

            // 'individual' rende uma via por aluno; 'coletivo' rende uma
            // via só, com a lista da turma dentro. O tipo decide quais
            // campos o corpo pode usar, por isso mora no modelo.
            $table->string('tipo', 20)->default('individual');

            $table->text('corpo');

            // Quantas vias cabem numa página, no documento individual.
            $table->unsignedTinyInteger('por_pagina')->default(1);

            $table->boolean('ativo')->default(true);

            $table->foreignId('criado_por')->nullable()->constrained('usuarios')->nullOnDelete();

            /*
             * Quando o modelo nasce de um compartilhamento aceito, guarda
             * de qual modelo veio. É o que permite dizer "cópia de um
             * modelo de Fulano" sem depender do registro de
             * compartilhamento, que fala de uma troca e não da autoria.
             */
            $table->foreignId('copia_de')->nullable()
                ->constrained('modelos_documento')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['eixo_id', 'ativo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modelos_documento');
    }
};
