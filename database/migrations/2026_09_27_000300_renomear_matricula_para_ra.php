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
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropUnique(['turma_id', 'matricula']);
            $table->dropIndex(['matricula']);
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->renameColumn('matricula', 'ra');
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->unique(['turma_id', 'ra']);
            $table->index('ra');
        });

        $this->trocarNoJson('matricula', 'ra');
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropUnique(['turma_id', 'ra']);
            $table->dropIndex(['ra']);
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->renameColumn('ra', 'matricula');
        });

        Schema::table('alunos', function (Blueprint $table) {
            $table->unique(['turma_id', 'matricula']);
            $table->index('matricula');
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
