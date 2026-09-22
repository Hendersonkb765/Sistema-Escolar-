<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Conteúdo do enunciado além do texto do comando: trechos de código
     * com a linguagem declarada, imagens e parágrafos intercalados, em
     * ordem.
     */
    public function up(): void
    {
        Schema::create('questao_blocos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questao_id')->constrained('questoes')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->string('tipo', 20);
            $table->text('conteudo')->nullable();
            // Só para blocos de código.
            $table->string('linguagem', 30)->nullable();
            // Só para blocos de imagem.
            $table->string('caminho')->nullable();
            $table->string('legenda')->nullable();
            $table->timestamps();

            $table->unique(['questao_id', 'ordem']);
            $table->index(['questao_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questao_blocos');
    }
};
