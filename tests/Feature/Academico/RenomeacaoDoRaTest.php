<?php

use App\Models\Aluno;
use App\Models\ModeloProva;
use App\Models\Turma;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A migration que renomeia `matricula` para `ra` faz três coisas que
 * falham caladas: renomear a coluna, reconstruir a unicidade dentro da
 * turma e trocar a chave guardada no JSON de `campos_identificacao`.
 *
 * Só um banco com dados dos dois formatos prova que a conversão anda —
 * por isso aqui a migration é executada nos dois sentidos.
 */
function migrationDoRa(): Migration
{
    return require database_path('migrations/2026_09_27_000300_renomear_matricula_para_ra.php');
}

it('leva a coluna, o índice e o JSON do formato antigo para o novo', function () {
    $turma = Turma::factory()->create();
    Aluno::factory()->naTurma($turma)->create(['nome' => 'Marina Alves', 'ra' => '20260001']);
    $modelo = ModeloProva::factory()->create([
        'campos_identificacao' => ['aluno', 'ra', 'turma'],
    ]);

    migrationDoRa()->down();

    expect(Schema::hasColumn('alunos', 'matricula'))->toBeTrue()
        ->and(Schema::hasColumn('alunos', 'ra'))->toBeFalse()
        ->and(DB::table('alunos')->value('matricula'))->toBe('20260001')
        ->and(json_decode((string) DB::table('modelos_prova')->where('id', $modelo->id)
            ->value('campos_identificacao'), true))
        ->toBe(['aluno', 'matricula', 'turma']);

    migrationDoRa()->up();

    expect(Schema::hasColumn('alunos', 'ra'))->toBeTrue()
        ->and(Schema::hasColumn('alunos', 'matricula'))->toBeFalse()
        ->and(DB::table('alunos')->value('ra'))->toBe('20260001')
        ->and(json_decode((string) DB::table('modelos_prova')->where('id', $modelo->id)
            ->value('campos_identificacao'), true))
        ->toBe(['aluno', 'ra', 'turma']);
});

it('mantém o RA único dentro da turma depois de reconstruir o índice', function () {
    $turma = Turma::factory()->create();
    Aluno::factory()->naTurma($turma)->create(['ra' => '777']);

    migrationDoRa()->down();
    migrationDoRa()->up();

    expect(fn () => Aluno::factory()->naTurma($turma)->create(['ra' => '777']))
        ->toThrow(QueryException::class);
});

it('deixa quieto o modelo que não guardava o campo antigo', function () {
    $modelo = ModeloProva::factory()->create([
        'campos_identificacao' => ['aluno', 'turma', 'data'],
    ]);

    migrationDoRa()->down();

    expect(json_decode((string) DB::table('modelos_prova')->where('id', $modelo->id)
        ->value('campos_identificacao'), true))->toBe(['aluno', 'turma', 'data']);

    migrationDoRa()->up();
});
