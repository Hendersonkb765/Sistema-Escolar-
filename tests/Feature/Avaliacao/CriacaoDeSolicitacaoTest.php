<?php

/*
 * Abertura de solicitações: pesos por questão, coerência entre turma,
 * disciplina e período, e o vínculo docente que nasce da designação.
 */

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
        2 => ['Back-end', 'Front-end'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];
    $this->logica = $montagem['disciplinas']['Lógica de Programação'];
    $this->backend = $montagem['disciplinas']['Back-end'];

    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026',
    ]);

    $this->criar = app(CriarSolicitacaoAction::class);
});

it('abre a solicitação com um item e uma questão em rascunho por peso', function () {
    // O exemplo do enunciado: 5 questões de Lógica com pesos 1; 1; 0,5; 0,5; 0,75.
    $solicitacao = $this->criar->executar(
        autor: $this->paeet,
        turma: $this->turma,
        disciplina: $this->logica,
        professor: $this->professor,
        pesos: [1, 1, 0.5, 0.5, 0.75],
        quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    );

    expect($solicitacao->status)->toBe(StatusSolicitacao::Aberta)
        ->and($solicitacao->quantidade_questoes)->toBe(5)
        ->and($solicitacao->curso_id)->toBe($this->curso->id)
        ->and($solicitacao->criado_por)->toBe($this->paeet->id)
        ->and($solicitacao->itens()->count())->toBe(5)
        ->and($solicitacao->itens()->orderBy('ordem')->pluck('peso')->map(fn ($p) => (float) $p)->all())
        ->toBe([1.0, 1.0, 0.5, 0.5, 0.75])
        ->and((float) $solicitacao->somaDosPesos())->toBe(3.75)
        // Uma questão em rascunho por item, já com o peso copiado.
        ->and($solicitacao->questoes()->count())->toBe(5)
        ->and($solicitacao->questoes()->where('status', StatusQuestao::Rascunho)->count())->toBe(5);

    $primeira = $solicitacao->questoes()->with('item')->get()->firstWhere('item.ordem', 3);

    expect((float) $primeira->peso)->toBe(0.5)
        ->and($primeira->professor_id)->toBe($this->professor->id)
        ->and($primeira->disciplina_id)->toBe($this->logica->id);
});

it('o peso não é alterável por atribuição em massa', function () {
    $solicitacao = $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [2.5], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    );

    $questao = $solicitacao->questoes()->first();

    // Fora do fillable de propósito: é o que impede o professor de mexer
    // no peso que o PAEET definiu. Sob shouldBeStrict a tentativa estoura;
    // em produção seria descartada em silêncio — nos dois casos, ignorada.
    expect(in_array('peso', $questao->getFillable(), true))->toBeFalse();

    expect(fn () => $questao->fill(['peso' => 99]))
        ->toThrow(MassAssignmentException::class);

    expect((float) $questao->refresh()->peso)->toBe(2.5);
});

it('o peso enviado pelo formulário do professor é ignorado', function () {
    $solicitacao = $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [2.5], quantidadeAlternativas: 2,
        prazo: now()->addWeek(),
    );

    $questao = $solicitacao->questoes()->first();

    app(SalvarQuestaoAction::class)->executar(
        questao: $questao,
        autor: $this->professor,
        enunciado: 'Enunciado qualquer',
        alternativas: [
            ['letra' => 'A', 'texto' => 'A', 'correta' => true],
            ['letra' => 'B', 'texto' => 'B', 'correta' => false],
        ],
    );

    expect((float) $questao->refresh()->peso)->toBe(2.5);
});

it('cria o vínculo docente ao designar o professor', function () {
    expect($this->professor->lecionaDisciplina($this->logica->id))->toBeFalse();

    $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [1], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    );

    expect($this->professor->refresh()->esquecerEscopo()->lecionaDisciplina($this->logica->id))->toBeTrue();
});

it('recusa disciplina que a turma não cursa no período dela', function () {
    // Back-end é do 2º período; a turma está no 1º.
    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->backend,
        professor: $this->professor, pesos: [1], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'não cursa Back-end no 1º período');

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('recusa disciplina de outro curso', function () {
    $outroCurso = Curso::factory()->noEixo($this->eixo)->create();
    $alheia = Disciplina::factory()->doCurso($outroCurso)->noPeriodo(1)->create(['nome' => 'Armazenagem']);

    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $alheia,
        professor: $this->professor, pesos: [1], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'não pertence ao curso desta turma');
});

it('recusa peso zero ou negativo', function (float $peso) {
    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [1, $peso], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'maior que zero');
})->with([0, -1, -0.5]);

it('recusa quantidade de alternativas fora da faixa', function (int $quantidade) {
    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [1], quantidadeAlternativas: $quantidade,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'de 2 a 6 alternativas');
})->with([1, 7]);

it('recusa designar professor inativo', function () {
    $this->professor->update(['ativo' => false]);

    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor->refresh(), pesos: [1], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'conta do professor está inativa');
});

it('recusa solicitação sem nenhuma questão', function () {
    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'ao menos uma questão');
});

it('nega a abertura ao professor', function () {
    expect(fn () => $this->criar->executar(
        autor: $this->professor, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [1], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(AuthorizationException::class);
});

it('nega a abertura a um PAEET de outro eixo', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->criar->executar(
        autor: $intruso, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [1], quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(AuthorizationException::class);

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('não deixa a solicitação pela metade quando a regra é violada', function () {
    try {
        $this->criar->executar(
            autor: $this->paeet, turma: $this->turma, disciplina: $this->backend,
            professor: $this->professor, pesos: [1, 1], quantidadeAlternativas: 4,
            prazo: now()->addWeek(),
        );
    } catch (RegraDeNegocioException) {
        // esperado
    }

    expect(SolicitacaoProva::query()->count())->toBe(0)
        ->and(Questao::query()->count())->toBe(0);
});

it('registra a abertura na auditoria', function () {
    $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, pesos: [1, 0.5], quantidadeAlternativas: 5,
        prazo: now()->addWeek(),
    );

    $registro = Activity::query()
        ->where('log_name', 'solicitacao')->latest('id')->first();

    expect($registro->description)->toBe('Solicitação de 2 questão(ões) aberta')
        ->and($registro->causer_id)->toBe($this->paeet->id)
        ->and($registro->getProperty('soma_dos_pesos'))->toBe(1.5)
        ->and($registro->getProperty('disciplina'))->toBe('Lógica de Programação');
});
