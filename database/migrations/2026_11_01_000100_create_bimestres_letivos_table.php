<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * As datas de cada bimestre de um ano letivo.
 *
 * O escopo-sequência é escrito em bimestre e semana, sem datas; é esta
 * tabela que diz em qual semana cai um dia do calendário. Uma só para a
 * escola: o ano letivo não muda de um Eixo para outro.
 *
 * `periodo_letivo` casa com o de `turmas` — é por ele que a data de uma
 * aula encontra o bimestre da turma.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bimestres_letivos', function (Blueprint $table) {
            $table->id();
            $table->string('periodo_letivo', 20);
            $table->unsignedTinyInteger('bimestre');
            $table->date('inicio');
            $table->date('fim');
            $table->timestamps();

            $table->unique(['periodo_letivo', 'bimestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bimestres_letivos');
    }
};
