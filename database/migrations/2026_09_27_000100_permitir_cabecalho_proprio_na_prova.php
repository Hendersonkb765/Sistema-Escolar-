<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cabeçalho próprio de uma prova.
 *
 * As duas primeiras linhas da folha vêm do modelo, que é compartilhado.
 * Estas colunas deixam uma prova em particular dizer outra coisa —
 * "Avaliação de Recuperação", o nome de uma escola parceira — sem
 * precisar criar um modelo só para ela.
 *
 * Nulo mantém o comportamento de antes: vale o que o modelo diz, e
 * corrigir o modelo corrige a reimpressão de todas as provas que não
 * escolheram um texto próprio.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provas', function (Blueprint $table) {
            $table->string('instituicao')->nullable()->after('titulo');
            $table->string('nome_avaliacao')->nullable()->after('instituicao');
        });
    }

    public function down(): void
    {
        Schema::table('provas', fn (Blueprint $table) => $table->dropColumn(['instituicao', 'nome_avaliacao']));
    }
};
