<?php

/*
 * Critério de aceite 6:
 * o envio após o prazo é aceito e marcado como "Enviada em atraso".
 *
 * O prazo é da prova inteira, mas o envio é por disciplina: cada
 * professor entrega a parte dele quando termina.
 */

use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Actions\Avaliacao\EnviarParteAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professorA = professor($this->eixo);
    $this->professorA->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->professorB = professor($this->eixo);
    $this->professorB->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->logica = $montagem['disciplinas']['Lógica de Programação'];
    $this->redes = $montagem['disciplinas']['Redes de Computadores'];

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->abrir = fn (?string $prazo = null) => solicitacaoCom(
        $this->paeet,
        $this->turma,
        [
            ['disciplina' => $this->logica, 'professor' => $this->professorA, 'questoes' => 5],
            ['disciplina' => $this->redes, 'professor' => $this->professorB, 'questoes' => 2],
        ],
        prazo: $prazo ? now()->parse($prazo) : now()->addWeek(),
    );

    $this->enviar = app(EnviarParteAction::class);
    $this->encerrar = app(EncerrarSolicitacaoAction::class);
    $this->salvar = app(SalvarQuestaoAction::class);
});

it('aceita o envio depois do prazo e marca a parte como enviada em atraso', function () {
    $solicitacao = ($this->abrir)(now()->subDays(3)->toDateTimeString());
    $parte = $solicitacao->partes()->first();

    expect($parte->estaAtrasada())->toBeTrue()
        ->and($parte->rotuloDePrazo())->toBe('Atrasada')
        // Prazo vencido não fecha o envio.
        ->and($parte->aceitaEnvio())->toBeTrue()
        ->and($solicitacao->rotuloDePrazo())->toBe('Atrasada');

    $enviada = enviarParte($parte, $this->professorA);

    expect($enviada->status)->toBe(StatusSolicitacao::Enviada)
        ->and($enviada->enviada_em)->not->toBeNull()
        ->and($enviada->enviada_em_atraso)->toBeTrue()
        ->and($enviada->rotuloDePrazo())->toBe('Enviada em atraso')
        // O prazo original é preservado tal como foi combinado.
        ->and($solicitacao->refresh()->prazo->isPast())->toBeTrue();
});

it('não marca atraso quando o envio acontece dentro do prazo', function () {
    $solicitacao = ($this->abrir)();
    $parte = $solicitacao->partes()->first();

    expect($parte->estaAtrasada())->toBeFalse();

    $enviada = enviarParte($parte, $this->professorA);

    expect($enviada->enviada_em_atraso)->toBeFalse()
        ->and($enviada->rotuloDePrazo())->toBeNull();
});

it('cada professor entrega a sua parte sem esperar pelo outro', function () {
    $solicitacao = ($this->abrir)();
    [$deLogica, $deRedes] = $solicitacao->partes()->get()->all();

    enviarParte($deLogica, $this->professorA);

    expect($deLogica->refresh()->status)->toBe(StatusSolicitacao::Enviada)
        ->and($deRedes->refresh()->status)->toBe(StatusSolicitacao::Aberta)
        // A prova só fica "enviada" quando todas as partes chegam.
        ->and($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Aberta);

    enviarParte($deRedes, $this->professorB);

    expect($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Enviada);
});

it('uma parte atrasada não contamina a outra entregue no prazo', function () {
    $solicitacao = ($this->abrir)(now()->subDay()->toDateTimeString());
    [$deLogica, $deRedes] = $solicitacao->partes()->get()->all();

    enviarParte($deLogica, $this->professorA);

    expect($deLogica->refresh()->enviada_em_atraso)->toBeTrue()
        ->and($deRedes->refresh()->enviada_em_atraso)->toBeFalse();
});

it('registra o atraso na auditoria com prazo e data real', function () {
    $solicitacao = ($this->abrir)(now()->subDay()->toDateTimeString());

    enviarParte($solicitacao->partes()->first(), $this->professorA);

    $registro = Activity::query()->where('log_name', 'solicitacao')->latest('id')->first();

    expect($registro->description)->toBe('Questões enviadas em atraso')
        ->and($registro->getProperty('em_atraso'))->toBeTrue()
        ->and($registro->getProperty('disciplina'))->toBe('Lógica de Programação')
        ->and($registro->getProperty('prazo'))->not->toBeNull()
        ->and($registro->getProperty('enviada_em'))->not->toBeNull();
});

it('só o encerramento manual bloqueia o envio', function () {
    $solicitacao = ($this->abrir)(now()->subWeek()->toDateTimeString());
    $parte = $solicitacao->partes()->first();

    // Vencida, mas ainda aberta.
    expect($parte->aceitaEnvio())->toBeTrue();

    $this->encerrar->encerrar($solicitacao, $this->paeet, 'Prova já montada');

    expect($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Concluida)
        ->and($solicitacao->encerrada_em)->not->toBeNull()
        ->and($parte->refresh()->aceitaEnvio())->toBeFalse();

    expect(fn () => $this->enviar->executar($parte, $this->professorA))
        ->toThrow(RegraDeNegocioException::class, 'encerrada pela coordenação');
});

it('o cancelamento também bloqueia o envio', function () {
    $solicitacao = ($this->abrir)();

    $this->encerrar->cancelar($solicitacao, $this->paeet, 'Disciplina saiu da prova');

    expect($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Cancelada)
        ->and($solicitacao->cancelada_em)->not->toBeNull()
        ->and($solicitacao->partes()->first()->aceitaEnvio())->toBeFalse();
});

it('não deixa salvar questão em solicitação encerrada', function () {
    $solicitacao = ($this->abrir)();
    $questao = $solicitacao->questoes()->first();

    $this->encerrar->encerrar($solicitacao, $this->paeet);

    expect(fn () => completarQuestao($questao->refresh(), $this->professorA))
        ->toThrow(RegraDeNegocioException::class, 'não aceita mais alterações');
});

it('reabre uma solicitação fechada por engano', function () {
    $solicitacao = ($this->abrir)();
    $this->encerrar->encerrar($solicitacao, $this->paeet);

    $reaberta = $this->encerrar->reabrir($solicitacao->refresh(), $this->paeet, 'Fechada sem querer');

    expect($reaberta->status)->toBe(StatusSolicitacao::Aberta)
        ->and($reaberta->encerrada_em)->toBeNull()
        ->and($reaberta->partes()->first()->aceitaEnvio())->toBeTrue();
});

it('recusa o envio com questões incompletas, dizendo quais', function () {
    $solicitacao = ($this->abrir)();
    $parte = $solicitacao->partes()->first();

    // Responde só as duas primeiras das cinco.
    $parte->questoes()->get()
        ->filter(fn (Questao $q) => $q->ordem <= 2)
        ->each(fn (Questao $q) => completarQuestao($q, $this->professorA));

    expect(fn () => $this->enviar->executar($parte->refresh(), $this->professorA))
        ->toThrow(RegraDeNegocioException::class, 'As questões 3, 4, 5');

    expect($parte->refresh()->status)->toBe(StatusSolicitacao::Aberta)
        ->and($parte->enviada_em)->toBeNull();
});

it('marca as questões da parte como enviadas', function () {
    $solicitacao = ($this->abrir)();
    [$deLogica, $deRedes] = $solicitacao->partes()->get()->all();

    enviarParte($deLogica, $this->professorA);

    expect($deLogica->questoes()->get()->every(fn (Questao $q) => $q->status === StatusQuestao::Enviada))
        ->toBeTrue()
        // As da outra disciplina seguem em rascunho.
        ->and($deRedes->questoes()->get()->every(fn (Questao $q) => $q->status === StatusQuestao::Rascunho))
        ->toBeTrue();
});

it('recusa enviar a mesma parte duas vezes', function () {
    $solicitacao = ($this->abrir)();
    $parte = $solicitacao->partes()->first();

    enviarParte($parte, $this->professorA);

    expect(fn () => $this->enviar->executar($parte->refresh(), $this->professorA))
        ->toThrow(RegraDeNegocioException::class, 'já foram enviadas em');
});

it('um professor não envia a parte do outro', function () {
    $solicitacao = ($this->abrir)();
    $deLogica = $solicitacao->partes()->first();

    expect(fn () => $this->enviar->executar($deLogica, $this->professorB))
        ->toThrow(AuthorizationException::class);

    expect($this->professorB->can('responder', $deLogica))->toBeFalse()
        ->and($this->professorA->can('responder', $deLogica))->toBeTrue();
});
