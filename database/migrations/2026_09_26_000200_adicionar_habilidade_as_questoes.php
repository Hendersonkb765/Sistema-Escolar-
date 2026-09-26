<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A habilidade que a questão avalia.
 *
 * Fica na questão, preenchida pelo professor, e é **congelada** na
 * montagem como o resto: a análise de desempenho de uma prova antiga
 * precisa continuar dizendo o que aquela questão avaliava, mesmo que a
 * questão original mude de habilidade depois.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questoes', function (Blueprint $table) {
            $table->string('habilidade')->nullable()->after('enunciado');
        });

        Schema::table('prova_questoes', function (Blueprint $table) {
            $table->string('habilidade_snapshot')->nullable()->after('enunciado_snapshot');
            // A análise agrupa por habilidade dentro de uma prova.
            $table->index(['prova_id', 'habilidade_snapshot'], 'prova_questoes_habilidade_index');
        });
    }

    public function down(): void
    {
        Schema::table('prova_questoes', function (Blueprint $table) {
            $table->dropIndex('prova_questoes_habilidade_index');
            $table->dropColumn('habilidade_snapshot');
        });

        Schema::table('questoes', fn (Blueprint $table) => $table->dropColumn('habilidade'));
    }
};
