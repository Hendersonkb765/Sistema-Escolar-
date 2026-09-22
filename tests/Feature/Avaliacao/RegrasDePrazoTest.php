<?php

/*
 * Critério de aceite 6:
 * o envio após o prazo é aceito e marcado como "Enviada em atraso".
 *
 * Prazo vencido não bloqueia nada. O que fecha o envio é o encerramento
 * ou o cancelamento manual pelo PAEET.
 */

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Actions\Avaliacao\EnviarSolicitacaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->curso = $montagem['curso'];
    $this->logica = $montagem['disciplinas']['Lógica de Programação'];

    $this->turma = Turma::factory()->doCurso($this->curso, $montagem['grade'])->create([
        'periodo' => 1,
        'nome' => '1 A',
        'periodo_letivo' => '2026',
    ]);

    $this->criar = app(CriarSolicitacaoAction::class);
    $this->salvar = app(SalvarQuestaoAction::class);
    $this->enviar = app(EnviarSolicitacaoAction::class);
    $this->encerrar = app(EncerrarSolicitacaoAction::class);

    // Os pesos do exemplo do enunciado.
    $this->abrir = fn (?string $prazo = null) => $this->criar->executar(
        autor: $this->paeet,
        turma: $this->turma,
        disciplina: $this->logica,
        professor: $this->professor,
        pesos: [1, 1, 0.5, 0.5, 0.75],
        quantidadeAlternativas: 4,
        prazo: $prazo ? now()->parse($prazo) : now()->addWeek(),
    );

    $this->responderTudo = function (SolicitacaoProva $solicitacao) {
        foreach ($solicitacao->questoes()->with('item')->get() as $questao) {
            $this->salvar->executar(
                questao: $questao,
                autor: $this->professor,
                enunciado: "Enunciado da questão {$questao->item->ordem}",
                alternativas: [
                    ['letra' => 'A', 'texto' => 'Alternativa A', 'correta' => true],
                    ['letra' => 'B', 'texto' => 'Alternativa B', 'correta' => false],
                    ['letra' => 'C', 'texto' => 'Alternativa C', 'correta' => false],
                    ['letra' => 'D', 'texto' => 'Alternativa D', 'correta' => false],
                ],
            );
        }
    };
});

it('aceita o envio depois do prazo e marca como enviada em atraso', function () {
    $solicitacao = ($this->abrir)(now()->subDays(3)->toDateTimeString());

    expect($solicitacao->estaAtrasada())->toBeTrue()
        ->and($solicitacao->rotuloDePrazo())->toBe('Atrasada')
        // Prazo vencido não fecha o envio.
        ->and($solicitacao->aceitaEnvio())->toBeTrue();

    ($this->responderTudo)($solicitacao);

    $enviada = $this->enviar->executar($solicitacao->refresh(), $this->professor);

    expect($enviada->status)->toBe(StatusSolicitacao::Enviada)
        ->and($enviada->enviada_em)->not->toBeNull()
        ->and($enviada->enviada_em_atraso)->toBeTrue()
        ->and($enviada->rotuloDePrazo())->toBe('Enviada em atraso')
        // O prazo original é preservado tal como foi combinado.
        ->and($enviada->prazo->isPast())->toBeTrue();
});

it('não marca atraso quando o envio acontece dentro do prazo', function () {
    $solicitacao = ($this->abrir)();

    expect($solicitacao->estaAtrasada())->toBeFalse()
        ->and($solicitacao->rotuloDePrazo())->toBeNull();

    ($this->responderTudo)($solicitacao);

    $enviada = $this->enviar->executar($solicitacao->refresh(), $this->professor);

    expect($enviada->enviada_em_atraso)->toBeFalse()
        ->and($enviada->rotuloDePrazo())->toBeNull();
});

it('registra o atraso na auditoria com prazo e data real', function () {
    $solicitacao = ($this->abrir)(now()->subDay()->toDateTimeString());
    ($this->responderTudo)($solicitacao);

    $this->enviar->executar($solicitacao->refresh(), $this->professor);

    $registro = Activity::query()->where('log_name', 'solicitacao')->latest('id')->first();

    expect($registro->description)->toBe('Questões enviadas em atraso')
        ->and($registro->getProperty('em_atraso'))->toBeTrue()
        ->and($registro->getProperty('prazo'))->not->toBeNull()
        ->and($registro->getProperty('enviada_em'))->not->toBeNull();
});

it('só o encerramento manual bloqueia o envio', function () {
    $solicitacao = ($this->abrir)(now()->subWeek()->toDateTimeString());

    // Vencida, mas ainda aberta.
    expect($solicitacao->aceitaEnvio())->toBeTrue();

    $this->encerrar->encerrar($solicitacao, $this->paeet, 'Prova já montada');

    $solicitacao->refresh();

    expect($solicitacao->status)->toBe(StatusSolicitacao::Concluida)
        ->and($solicitacao->encerrada_em)->not->toBeNull()
        ->and($solicitacao->aceitaEnvio())->toBeFalse();

    // Recusa com a razão explicada, não com um 403 mudo.
    expect(fn () => $this->enviar->executar($solicitacao, $this->professor))
        ->toThrow(RegraDeNegocioException::class, 'encerrada pela coordenação');
});

it('o cancelamento também bloqueia o envio', function () {
    $solicitacao = ($this->abrir)();

    $this->encerrar->cancelar($solicitacao, $this->paeet, 'Disciplina saiu da prova');

    expect($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Cancelada)
        ->and($solicitacao->cancelada_em)->not->toBeNull()
        ->and($solicitacao->aceitaEnvio())->toBeFalse();
});

it('não deixa salvar questão em solicitação encerrada', function () {
    $solicitacao = ($this->abrir)();
    $questao = $solicitacao->questoes()->first();

    $this->encerrar->encerrar($solicitacao, $this->paeet);

    expect(fn () => $this->salvar->executar(
        questao: $questao->refresh(),
        autor: $this->professor,
        enunciado: 'Tarde demais',
        alternativas: [
            ['letra' => 'A', 'texto' => 'A', 'correta' => true],
            ['letra' => 'B', 'texto' => 'B', 'correta' => false],
            ['letra' => 'C', 'texto' => 'C', 'correta' => false],
            ['letra' => 'D', 'texto' => 'D', 'correta' => false],
        ],
    ))->toThrow(RegraDeNegocioException::class, 'não aceita mais alterações');
});

it('reabre uma solicitação fechada por engano', function () {
    $solicitacao = ($this->abrir)();
    $this->encerrar->encerrar($solicitacao, $this->paeet);

    $reaberta = $this->encerrar->reabrir($solicitacao->refresh(), $this->paeet, 'Fechada sem querer');

    expect($reaberta->status)->toBe(StatusSolicitacao::Aberta)
        ->and($reaberta->encerrada_em)->toBeNull()
        ->and($reaberta->aceitaEnvio())->toBeTrue();
});

it('recusa o envio com questões incompletas, dizendo quais', function () {
    $solicitacao = ($this->abrir)();

    // Responde só as duas primeiras.
    $solicitacao->questoes()->with('item')->get()
        ->filter(fn (Questao $q) => $q->item->ordem <= 2)
        ->each(fn (Questao $q) => $this->salvar->executar(
            questao: $q,
            autor: $this->professor,
            enunciado: 'Enunciado',
            alternativas: [
                ['letra' => 'A', 'texto' => 'A', 'correta' => true],
                ['letra' => 'B', 'texto' => 'B', 'correta' => false],
                ['letra' => 'C', 'texto' => 'C', 'correta' => false],
                ['letra' => 'D', 'texto' => 'D', 'correta' => false],
            ],
        ));

    expect(fn () => $this->enviar->executar($solicitacao->refresh(), $this->professor))
        ->toThrow(RegraDeNegocioException::class, 'As questões 3, 4, 5');

    expect($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Aberta)
        ->and($solicitacao->enviada_em)->toBeNull();
});

it('marca as questões como enviadas junto com a solicitação', function () {
    $solicitacao = ($this->abrir)();
    ($this->responderTudo)($solicitacao);

    $this->enviar->executar($solicitacao->refresh(), $this->professor);

    $questoes = $solicitacao->questoes()->get();

    expect($questoes)->toHaveCount(5)
        ->and($questoes->every(fn (Questao $q) => $q->status === StatusQuestao::Enviada))->toBeTrue()
        ->and($questoes->every(fn (Questao $q) => $q->enviada_em !== null))->toBeTrue();
});
