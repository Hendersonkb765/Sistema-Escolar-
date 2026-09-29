<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A solicitação e a prova passam a dizer de que bimestre são.
 *
 * O `default(1)` existe só para as linhas que já estavam no banco: no
 * código o campo é obrigatório em toda criação, e as telas não deixam
 * salvar sem ele.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('solicitacoes_prova', function (Blueprint $table) {
            $table->unsignedTinyInteger('bimestre')->default(1)->after('titulo');
        });

        Schema::table('provas', function (Blueprint $table) {
            $table->unsignedTinyInteger('bimestre')->default(1)->after('titulo');
        });
    }

    public function down(): void
    {
        Schema::table('solicitacoes_prova', fn (Blueprint $table) => $table->dropColumn('bimestre'));
        Schema::table('provas', fn (Blueprint $table) => $table->dropColumn('bimestre'));
    }
};
