<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Resultados, importações e compartilhamentos passam a ir para a Lixeira
 * junto com o aluno, a prova ou o modelo de que dependem.
 *
 * Os três são lidos por conta própria — a análise soma resultados, a
 * lista de importações e a de compartilhamentos não passam pelo registro
 * de cima —, e por isso precisam sair de verdade das consultas, e não só
 * ficar órfãos.
 */
return new class extends Migration
{
    private const TABELAS = ['resultados_alunos', 'importacoes', 'compartilhamentos_de_modelo'];

    public function up(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (self::TABELAS as $tabela) {
            Schema::table($tabela, fn (Blueprint $table) => $table->dropSoftDeletes());
        }
    }
};
