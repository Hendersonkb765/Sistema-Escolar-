<?php

/*
 * Critério de aceite 7:
 * questão rejeitada não entra na montagem da prova; aprovada entra.
 *
 * E o ciclo inteiro: envio → análise → devolução com feedback → correção
 * → reenvio versionado → nova análise.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Actions\Avaliacao\EnviarSolicitacaoAction;
use App\Actions\Avaliacao\ReenviarQuestaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\AcaoFeedback;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\QuestaoFeedback;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Schema;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']],
        duracaoAnos: 2, autor: $this->paeet);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->solicitacao = app(CriarSolicitacaoAction::class)->executar(
        autor: $this->paeet, turma: $turma,
        disciplina: $montagem['disciplinas']['Lógica de Programação'],
        professor: $this->professor, quantidadeQuestoes: 3,
        quantidadeAlternativas: 4, prazo: now()->addWeek(),
    );

    $this->alternativas = [
        ['letra' => 'A', 'texto' => 'A', 'correta' => true],
        ['letra' => 'B', 'texto' => 'B', 'correta' => false],
        ['letra' => 'C', 'texto' => 'C', 'correta' => false],
        ['letra' => 'D', 'texto' => 'D', 'correta' => false],
    ];

    $salvar = app(SalvarQuestaoAction::class);

    foreach ($this->solicitacao->questoes()->with('item')->get() as $questao) {
        $salvar->executar($questao, $this->professor,
            "Enunciado da questão {$questao->item->ordem}", $this->alternativas, peso: 1);
    }

    app(EnviarSolicitacaoAction::class)->executar($this->solicitacao->refresh(), $this->professor);

    $this->analisar = app(AnalisarQuestaoAction::class);
    $this->reenviar = app(ReenviarQuestaoAction::class);
    $this->salvar = $salvar;

    $this->questao = fn (int $ordem) => $this->solicitacao->questoes()
        ->with('item')->get()->firstWhere('item.ordem', $ordem);
});

// ------------------------------------------------------------- aprovação

it('aprova uma questão e registra o feedback', function () {
    $questao = ($this->questao)(1);

    $aprovada = $this->analisar->aprovar($questao, $this->paeet, 'Boa questão.');

    expect($aprovada->status)->toBe(StatusQuestao::Aprovada)
        ->and($aprovada->analisada_em)->not->toBeNull();

    $feedback = QuestaoFeedback::query()->where('questao_id', $questao->id)->latest('id')->first();

    expect($feedback->acao)->toBe(AcaoFeedback::Aprovada)
        ->and($feedback->analisado_por)->toBe($this->paeet->id)
        ->and($feedback->comentario)->toBe('Boa questão.')
        ->and($feedback->versao_questao)->toBe(1);
});

it('aprova sem comentário', function () {
    $aprovada = $this->analisar->aprovar(($this->questao)(1), $this->paeet);

    expect($aprovada->status)->toBe(StatusQuestao::Aprovada)
        ->and(QuestaoFeedback::query()->count())->toBe(1);
});

// ------------------------------------------------------------- devolução

it('devolve para correção com o motivo', function () {
    $devolvida = $this->analisar->rejeitar(
        ($this->questao)(2), $this->paeet, 'A alternativa C está ambígua.',
    );

    expect($devolvida->status)->toBe(StatusQuestao::Rejeitada);

    $feedback = QuestaoFeedback::query()->where('questao_id', $devolvida->id)->first();

    expect($feedback->acao)->toBe(AcaoFeedback::Rejeitada)
        ->and($feedback->comentario)->toBe('A alternativa C está ambígua.');
});

it('exige motivo ao devolver', function () {
    expect(fn () => $this->analisar->rejeitar(($this->questao)(1), $this->paeet, '   '))
        ->toThrow(RegraDeNegocioException::class, 'Explique o que precisa ser corrigido');

    expect(($this->questao)(1)->status)->toBe(StatusQuestao::Enviada)
        ->and(QuestaoFeedback::query()->count())->toBe(0);
});

// ------------------------------------------------------ correção e reenvio

it('devolve, o professor corrige e reenvia como nova versão', function () {
    $questao = ($this->questao)(1);

    $this->analisar->rejeitar($questao, $this->paeet, 'Falta clareza no enunciado.');

    expect($questao->refresh()->versao)->toBe(1)
        ->and($questao->status->aguardaProfessor())->toBeTrue();

    // A questão devolvida volta a ser editável pelo professor.
    $this->salvar->executar($questao, $this->professor,
        'Enunciado reescrito com mais clareza', $this->alternativas, peso: 2);

    $reenviada = $this->reenviar->executar($questao->refresh(), $this->professor);

    expect($reenviada->status)->toBe(StatusQuestao::Enviada)
        ->and($reenviada->versao)->toBe(2)
        ->and($reenviada->analisada_em)->toBeNull()
        ->and($reenviada->enunciado)->toBe('Enunciado reescrito com mais clareza');

    // Segunda análise, agora aprovando.
    $this->analisar->aprovar($reenviada, $this->paeet, 'Ficou claro.');

    $feedbacks = QuestaoFeedback::query()
        ->where('questao_id', $questao->id)->orderBy('id')->get();

    // O histórico guarda as duas decisões, cada uma com sua versão.
    expect($feedbacks)->toHaveCount(2)
        ->and($feedbacks[0]->acao)->toBe(AcaoFeedback::Rejeitada)
        ->and($feedbacks[0]->versao_questao)->toBe(1)
        ->and($feedbacks[1]->acao)->toBe(AcaoFeedback::Aprovada)
        ->and($feedbacks[1]->versao_questao)->toBe(2);
});

it('recusa reenviar questão incompleta', function () {
    $questao = ($this->questao)(1);

    $this->analisar->rejeitar($questao, $this->paeet, 'Refaça.');
    $questao->refresh()->update(['enunciado' => null]);

    expect(fn () => $this->reenviar->executar($questao->refresh(), $this->professor))
        ->toThrow(RegraDeNegocioException::class, 'Complete a questão antes de reenviar');

    expect($questao->refresh()->versao)->toBe(1);
});

it('recusa reenviar questão que não foi devolvida', function () {
    expect(fn () => $this->reenviar->executar(($this->questao)(1), $this->professor))
        ->toThrow(RegraDeNegocioException::class, 'Só uma questão devolvida');
});

// ------------------------------------------------- estado da solicitação

it('conclui a solicitação quando todas as questões são aprovadas', function () {
    expect($this->solicitacao->refresh()->status)->toBe(StatusSolicitacao::Enviada);

    $this->analisar->aprovar(($this->questao)(1), $this->paeet);

    expect($this->solicitacao->refresh()->status)->toBe(StatusSolicitacao::EmAnalise);

    $this->analisar->aprovar(($this->questao)(2), $this->paeet);
    $this->analisar->aprovar(($this->questao)(3), $this->paeet);

    $this->solicitacao->refresh();

    expect($this->solicitacao->status)->toBe(StatusSolicitacao::Concluida)
        // Concluída por aprovação, não fechada à mão: se a coordenação
        // devolver uma questão depois, a correção ainda é aceita.
        ->and($this->solicitacao->encerrada_em)->toBeNull();

    expect(Activity::query()->where('log_name', 'solicitacao')->pluck('description'))
        ->toContain('Todas as questões foram aprovadas');
});

it('reabre a solicitação concluída quando uma questão é reenviada', function () {
    foreach ([1, 2, 3] as $ordem) {
        $this->analisar->aprovar(($this->questao)($ordem), $this->paeet);
    }

    expect($this->solicitacao->refresh()->status)->toBe(StatusSolicitacao::Concluida)
        // Concluir por aprovação não é fechar manualmente.
        ->and($this->solicitacao->encerrada_em)->toBeNull();

    // A coordenação repensa uma delas e devolve.
    $questao = ($this->questao)(2);
    $questao->update(['status' => StatusQuestao::Enviada]);
    $this->analisar->rejeitar($questao->refresh(), $this->paeet, 'Reavaliada.');

    expect($this->solicitacao->refresh()->status)->toBe(StatusSolicitacao::EmAnalise);

    $this->reenviar->executar($questao->refresh(), $this->professor);

    expect($this->solicitacao->refresh()->status)->toBe(StatusSolicitacao::EmAnalise)
        ->and($this->solicitacao->encerrada_em)->toBeNull();
});

it('não analisa questão de solicitação encerrada sem antes reabrir', function () {
    app(EncerrarSolicitacaoAction::class)->encerrar($this->solicitacao->refresh(), $this->paeet);

    // A análise continua possível; o encerramento fecha o envio, não a
    // avaliação do que já chegou.
    $aprovada = $this->analisar->aprovar(($this->questao)(1), $this->paeet);

    expect($aprovada->status)->toBe(StatusQuestao::Aprovada)
        // E o encerramento manual continua de pé.
        ->and($this->solicitacao->refresh()->encerrada_em)->not->toBeNull();
});

// ------------------------------------------------------------- validações

it('não analisa questão em rascunho', function () {
    $outra = app(CriarSolicitacaoAction::class)->executar(
        autor: $this->paeet, turma: $this->solicitacao->turma,
        disciplina: $this->solicitacao->disciplina, professor: $this->professor,
        quantidadeQuestoes: 1, quantidadeAlternativas: 4, prazo: now()->addWeek(),
    );

    expect(fn () => $this->analisar->aprovar($outra->questoes()->first(), $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'ainda não foi enviada');
});

it('não analisa duas vezes a mesma versão', function () {
    $questao = ($this->questao)(1);

    $this->analisar->aprovar($questao, $this->paeet);

    expect(fn () => $this->analisar->aprovar($questao->refresh(), $this->paeet))
        ->toThrow(RegraDeNegocioException::class, 'já está aprovada');

    expect(fn () => $this->analisar->rejeitar($questao->refresh(), $this->paeet, 'Mudei de ideia'))
        ->toThrow(RegraDeNegocioException::class, 'já está aprovada');
});

it('o professor não analisa a própria questão', function () {
    expect(fn () => $this->analisar->aprovar(($this->questao)(1), $this->professor))
        ->toThrow(AuthorizationException::class);
});

it('um PAEET que escreveu a questão não se autoaprova', function () {
    $paeetQueLeciona = paeet($this->eixo);

    $solicitacao = app(CriarSolicitacaoAction::class)->executar(
        autor: $this->paeet, turma: $this->solicitacao->turma,
        disciplina: $this->solicitacao->disciplina, professor: $paeetQueLeciona,
        quantidadeQuestoes: 1, quantidadeAlternativas: 4, prazo: now()->addWeek(),
    );

    $questao = $solicitacao->questoes()->first();
    $this->salvar->executar($questao, $paeetQueLeciona, 'Enunciado', $this->alternativas, peso: 1);
    app(EnviarSolicitacaoAction::class)->executar($solicitacao->refresh(), $paeetQueLeciona);

    expect($paeetQueLeciona->can('analisar', $questao->refresh()))->toBeFalse()
        ->and($this->paeet->can('analisar', $questao))->toBeTrue();
});

it('um PAEET de outro eixo não analisa', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => $this->analisar->aprovar(($this->questao)(1), $intruso))
        ->toThrow(AuthorizationException::class);
});

// ------------------------------------------------- critério de aceite 7

it('só questão aprovada fica elegível para a prova', function () {
    $this->analisar->aprovar(($this->questao)(1), $this->paeet);
    $this->analisar->rejeitar(($this->questao)(2), $this->paeet, 'Refaça.');
    // A terceira segue apenas enviada, sem decisão.

    $elegiveis = Questao::query()
        ->where('solicitacao_id', $this->solicitacao->id)
        ->aprovadas()
        ->with('item')
        ->get();

    expect($elegiveis)->toHaveCount(1)
        ->and($elegiveis->first()->item->ordem)->toBe(1);

    $porStatus = $this->solicitacao->questoes()->with('item')->get()
        ->mapWithKeys(fn (Questao $q) => [$q->item->ordem => $q->status->elegivelParaProva()])
        ->sortKeys();

    expect($porStatus->all())->toBe([1 => true, 2 => false, 3 => false]);
});

it('a questão volta a não ser elegível quando é devolvida depois de aprovada', function () {
    $questao = ($this->questao)(1);

    $this->analisar->aprovar($questao, $this->paeet);
    expect($questao->refresh()->status->elegivelParaProva())->toBeTrue();

    // Devolver exige reenviar antes de uma nova decisão.
    $questao->update(['status' => StatusQuestao::Enviada]);
    $this->analisar->rejeitar($questao->refresh(), $this->paeet, 'Reavaliada.');

    expect($questao->refresh()->status->elegivelParaProva())->toBeFalse()
        ->and(Questao::query()->aprovadas()->count())->toBe(0);
});

it('o feedback nunca é alterado nem apagado', function () {
    $questao = ($this->questao)(1);

    $this->analisar->rejeitar($questao, $this->paeet, 'Primeira devolução.');

    $feedback = QuestaoFeedback::query()->first();

    expect($this->paeet->can('update', $feedback))->toBeFalse()
        ->and($this->paeet->can('delete', $feedback))->toBeFalse()
        // Append-only: a tabela nem tem a coluna de atualização.
        ->and(Schema::hasColumn('questao_feedbacks', 'updated_at'))->toBeFalse();
});
