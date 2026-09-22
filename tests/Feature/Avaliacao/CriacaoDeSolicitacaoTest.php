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
use Illuminate\Support\Facades\Schema;
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

it('abre a solicitação com um item e uma questão em rascunho por questão pedida', function () {
    $solicitacao = $this->criar->executar(
        autor: $this->paeet,
        turma: $this->turma,
        disciplina: $this->logica,
        professor: $this->professor,
        quantidadeQuestoes: 5,
        quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    );

    expect($solicitacao->status)->toBe(StatusSolicitacao::Aberta)
        ->and($solicitacao->quantidade_questoes)->toBe(5)
        ->and($solicitacao->curso_id)->toBe($this->curso->id)
        ->and($solicitacao->criado_por)->toBe($this->paeet->id)
        ->and($solicitacao->itens()->count())->toBe(5)
        ->and($solicitacao->itens()->orderBy('ordem')->pluck('ordem')->all())->toBe([1, 2, 3, 4, 5])
        ->and($solicitacao->questoes()->count())->toBe(5)
        ->and($solicitacao->questoes()->where('status', StatusQuestao::Rascunho)->count())->toBe(5);

    $terceira = $solicitacao->questoes()->with('item')->get()->firstWhere('item.ordem', 3);

    // Peso 1 é só o ponto de partida; quem decide é o professor.
    expect((float) $terceira->peso)->toBe(1.0)
        ->and($terceira->professor_id)->toBe($this->professor->id)
        ->and($terceira->disciplina_id)->toBe($this->logica->id);
});

it('o professor define o peso de cada questão', function () {
    $solicitacao = $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 5, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    );

    $salvar = app(SalvarQuestaoAction::class);

    // Os pesos do exemplo do enunciado: 1; 1; 0,5; 0,5; 0,75 — soma 3,75.
    $pesos = [1 => 1, 2 => 1, 3 => 0.5, 4 => 0.5, 5 => 0.75];

    foreach ($solicitacao->questoes()->with('item')->get() as $questao) {
        $salvar->executar(
            questao: $questao,
            autor: $this->professor,
            enunciado: 'Enunciado',
            alternativas: [
                ['letra' => 'A', 'texto' => 'A', 'correta' => true],
                ['letra' => 'B', 'texto' => 'B', 'correta' => false],
                ['letra' => 'C', 'texto' => 'C', 'correta' => false],
                ['letra' => 'D', 'texto' => 'D', 'correta' => false],
            ],
            peso: $pesos[$questao->item->ordem],
        );
    }

    expect((float) $solicitacao->refresh()->somaDosPesos())->toBe(3.75)
        ->and($solicitacao->questoes()->with('item')->get()
            ->sortBy(fn ($q) => $q->item->ordem)
            ->map(fn ($q) => (float) $q->peso)->values()->all())
        ->toBe([1.0, 1.0, 0.5, 0.5, 0.75]);
});

it('o PAEET não define peso ao abrir a solicitação', function () {
    // A tela do PAEET não tem campo de peso, e o item nem guarda a coluna.
    expect(Schema::hasColumn('solicitacao_itens', 'peso'))->toBeFalse()
        ->and(in_array('peso', (new Questao)->getFillable(), true))->toBeTrue();
});

it('cria o vínculo docente ao designar o professor', function () {
    expect($this->professor->lecionaDisciplina($this->logica->id))->toBeFalse();

    $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 1, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    );

    expect($this->professor->refresh()->esquecerEscopo()->lecionaDisciplina($this->logica->id))->toBeTrue();
});

it('recusa disciplina que a turma não cursa no período dela', function () {
    // Back-end é do 2º período; a turma está no 1º.
    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->backend,
        professor: $this->professor, quantidadeQuestoes: 1, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'não cursa Back-end no 1º período');

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('recusa disciplina de outro curso', function () {
    $outroCurso = Curso::factory()->noEixo($this->eixo)->create();
    $alheia = Disciplina::factory()->doCurso($outroCurso)->noPeriodo(1)->create(['nome' => 'Armazenagem']);

    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $alheia,
        professor: $this->professor, quantidadeQuestoes: 1, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'não pertence ao curso desta turma');
});

it('recusa quantidade de alternativas fora da faixa', function (int $quantidade) {
    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 1, quantidadeAlternativas: $quantidade,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'de 2 a 6 alternativas');
})->with([1, 7]);

it('recusa designar professor inativo', function () {
    $this->professor->update(['ativo' => false]);

    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor->refresh(), quantidadeQuestoes: 1, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'conta do professor está inativa');
});

it('recusa solicitação sem nenhuma questão', function () {
    expect(fn () => $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 0, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(RegraDeNegocioException::class, 'ao menos uma questão');
});

it('a soma dos pesos vem das questões, não dos itens', function () {
    $solicitacao = $this->criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 3, quantidadeAlternativas: 2,
        prazo: now()->addWeek(),
    );

    // Recém-abertas, todas em 1.
    expect((float) $solicitacao->somaDosPesos())->toBe(3.0);

    $solicitacao->questoes()->get()->first()->update(['peso' => 4]);

    expect((float) $solicitacao->refresh()->somaDosPesos())->toBe(6.0);
});

it('nega a abertura ao professor', function () {
    expect(fn () => $this->criar->executar(
        autor: $this->professor, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 1, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(AuthorizationException::class);
});

it('nega a abertura a um PAEET de outro eixo', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->criar->executar(
        autor: $intruso, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 1, quantidadeAlternativas: 4,
        prazo: now()->addWeek(),
    ))->toThrow(AuthorizationException::class);

    expect(SolicitacaoProva::query()->count())->toBe(0);
});

it('não deixa a solicitação pela metade quando a regra é violada', function () {
    try {
        $this->criar->executar(
            autor: $this->paeet, turma: $this->turma, disciplina: $this->backend,
            professor: $this->professor, quantidadeQuestoes: 2, quantidadeAlternativas: 4,
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
        professor: $this->professor, quantidadeQuestoes: 2, quantidadeAlternativas: 5,
        prazo: now()->addWeek(),
    );

    $registro = Activity::query()
        ->where('log_name', 'solicitacao')->latest('id')->first();

    expect($registro->description)->toBe('Solicitação de 2 questão(ões) aberta')
        ->and($registro->causer_id)->toBe($this->paeet->id)
        ->and($registro->getProperty('questoes'))->toBe(2)
        ->and($registro->getProperty('disciplina'))->toBe('Lógica de Programação');
});
