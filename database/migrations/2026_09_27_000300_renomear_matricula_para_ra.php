<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A escola chama o número do aluno de RA, não de matrícula.
 *
 * O nome antigo também estava gravado dentro do JSON de
 * `campos_identificacao`, que decide o que a folha de prova imprime no
 * quadro de identificação — por isso a migration mexe nos dois lugares.
 */
return new class extends Migration
{
    /*
     * Renomear, e não apagar e recriar os índices.
     *
     * No MySQL, a chave estrangeira de `turma_id` precisa de um índice que
     * comece por `turma_id`, e usa o único `(turma_id, matricula)` para
     * isso: apagá-lo dá "Cannot drop index … needed in a foreign key
     * constraint". O SQLite não tem essa regra, e por isso a versão antiga
     * passava aqui e quebrava na produção.
     *
     * RENAME COLUMN leva os índices junto, nos dois bancos; depois só se
     * troca o nome deles para o que o Laravel daria à coluna nova.
     */
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->renameColumn('matricula', 'ra');
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->renameIndex('alunos_turma_id_matricula_unique', 'alunos_turma_id_ra_unique');
            $table->renameIndex('alunos_matricula_index', 'alunos_ra_index');
        });

        $this->trocarNoJson('matricula', 'ra');
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->renameColumn('ra', 'matricula');
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->renameIndex('alunos_turma_id_ra_unique', 'alunos_turma_id_matricula_unique');
            $table->renameIndex('alunos_ra_index', 'alunos_matricula_index');
        });

        $this->trocarNoJson('ra', 'matricula');
    }

    /** Troca a chave do campo dentro do JSON, linha a linha, sem depender do banco. */
    protected function trocarNoJson(string $de, string $para): void
    {
        DB::table('modelos_prova')
            ->whereNotNull('campos_identificacao')
            ->orderBy('id')
            ->each(function (object $modelo) use ($de, $para) {
                $campos = json_decode((string) $modelo->campos_identificacao, true);

                if (! is_array($campos) || ! in_array($de, $campos, true)) {
                    return;
                }

                $trocados = array_map(fn ($campo) => $campo === $de ? $para : $campo, $campos);

                DB::table('modelos_prova')
                    ->where('id', $modelo->id)
                    ->update(['campos_identificacao' => json_encode(array_values($trocados))]);
            });
    }
};
