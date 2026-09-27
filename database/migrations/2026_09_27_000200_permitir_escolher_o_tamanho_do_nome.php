<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O corpo do nome da instituição no cabeçalho.
 *
 * Um nome curto cabe em qualquer tamanho; "E. E. Prof. Francisco
 * Pereira de Souza Filho" quebra em duas linhas no mesmo corpo que
 * servia a "PAEET". Quem escolhe o nome precisa poder escolher o
 * tamanho junto.
 *
 * Nulo mantém o cálculo de antes — o corpo do texto mais um ponto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provas', function (Blueprint $table) {
            $table->unsignedTinyInteger('tamanho_instituicao')->nullable()->after('nome_avaliacao');
        });
    }

    public function down(): void
    {
        Schema::table('provas', fn (Blueprint $table) => $table->dropColumn('tamanho_instituicao'));
    }
};
