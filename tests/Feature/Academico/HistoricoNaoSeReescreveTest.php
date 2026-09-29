<?php

/*
 * Histórico não se reescreve.
 *
 * As três tabelas de histórico são append-only: cada mudança grava o
 * estado que existia antes dela, e nenhuma linha é alterada depois. A
 * regra mora em duas decisões pequenas e fáceis de desfazer sem querer —
 * a tabela não tem `updated_at` e o model declara `UPDATED_AT = null`.
 *
 * Quem acrescentar `$table->timestamps()` numa migration nova, ou tirar a
 * constante ao refatorar um model, não vê erro nenhum: o sistema segue
 * funcionando e o histórico passa a ser reescrevível em silêncio. Estes
 * testes existem para esse dia.
 */

use App\Enums\EventoHistorico;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Eixo;
use App\Models\QuestaoFeedback;
use App\Models\Turma;
use App\Models\TurmaHistorico;
use Illuminate\Support\Facades\Schema;

/** tabela => model */
const TABELAS_DE_HISTORICO = [
    'turma_historicos' => TurmaHistorico::class,
    'aluno_historicos' => AlunoHistorico::class,
    'questao_feedbacks' => QuestaoFeedback::class,
];

it('não dá coluna de alteração a tabela de histórico nenhuma', function () {
    foreach (TABELAS_DE_HISTORICO as $tabela => $model) {
        expect(Schema::hasColumn($tabela, 'created_at'))
            ->toBeTrue("{$tabela} precisa registrar quando a linha nasceu.")
            ->and(Schema::hasColumn($tabela, 'updated_at'))
            ->toBeFalse("{$tabela} não pode ter updated_at: histórico não se reescreve.");
    }
});

it('mantém os models sem carimbo de alteração', function () {
    foreach (TABELAS_DE_HISTORICO as $tabela => $model) {
        expect($model::UPDATED_AT)
            ->toBeNull("{$model} precisa de UPDATED_AT = null.");
    }
});

/*
 * O teste acima olha o esquema; este olha o comportamento. Sem
 * `UPDATED_AT = null`, salvar de novo estoura por causa da coluna que não
 * existe — e é justamente esse estouro que o esquecimento produz em
 * produção, na hora errada.
 */
it('grava linha nova em vez de mexer na anterior', function () {
    $eixo = Eixo::factory()->create();
    $registrador = paeetAdmin($eixo);
    $montagem = cursoComGrade($eixo, [1 => ['Contabilidade']], autor: $registrador);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);
    $destino = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 B']);

    $aluno = Aluno::factory()->naTurma($turma)->create();

    $primeiro = AlunoHistorico::query()->create([
        'aluno_id' => $aluno->id,
        'evento' => EventoHistorico::AlunoMatriculado,
        'turma_nova_id' => $turma->id,
        'status_novo' => $aluno->status,
        'registrado_por' => $registrador->id,
    ]);

    $nascimento = $primeiro->created_at;

    AlunoHistorico::query()->create([
        'aluno_id' => $aluno->id,
        'evento' => EventoHistorico::AlunoTrocaTurma,
        'turma_anterior_id' => $turma->id,
        'turma_nova_id' => $destino->id,
        'status_anterior' => $aluno->status,
        'status_novo' => $aluno->status,
        'registrado_por' => $registrador->id,
    ]);

    expect(AlunoHistorico::query()->where('aluno_id', $aluno->id)->count())->toBe(2)
        ->and($primeiro->refresh()->created_at->equalTo($nascimento))->toBeTrue()
        ->and($primeiro->evento)->toBe(EventoHistorico::AlunoMatriculado)
        ->and($primeiro->turma_anterior_id)->toBeNull();
});
