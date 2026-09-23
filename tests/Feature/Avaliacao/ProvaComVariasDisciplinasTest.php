<?php

/*
 * Uma prova reúne várias disciplinas, cada uma com seu professor.
 * Cada um responde e entrega só a parte dele, sem depender dos outros.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Enums\StatusSolicitacao;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Livewire\Solicitacoes\DetalheSolicitacao;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->paeet->update(['nome' => 'Coordenação']);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);

    $this->profRedes = professor($this->eixo);
    $this->profRedes->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->logica = $montagem['disciplinas']['Lógica de Programação'];
    $this->redes = $montagem['disciplinas']['Redes de Computadores'];

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->prova = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->profLogica, 'questoes' => 3],
        ['disciplina' => $this->redes, 'professor' => $this->profRedes, 'questoes' => 2],
    ], titulo: 'Avaliação do 2º bimestre');

    [$this->parteLogica, $this->parteRedes] = $this->prova->partes()->with(['disciplina', 'solicitacao'])->get()->all();
});

it('cada professor vê apenas a disciplina dele na tela de resposta', function () {
    $this->actingAs($this->profLogica)
        ->get(route('solicitacoes.responder', $this->prova))
        ->assertOk()
        ->assertSee('Lógica de Programação')
        ->assertDontSee('Redes de Computadores');

    $this->actingAs($this->profRedes)
        ->get(route('solicitacoes.responder', $this->prova))
        ->assertOk()
        ->assertSee('Redes de Computadores')
        ->assertDontSee('Lógica de Programação');
});

it('o professor só recebe no formulário as questões da parte dele', function () {
    $componente = Livewire::actingAs($this->profLogica)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->prova]);

    $idsNoFormulario = array_keys($componente->get('formulario'));
    $idsDaParte = $this->parteLogica->questoes()->pluck('id')->all();

    expect($idsNoFormulario)->toBe($idsDaParte)
        ->and($idsNoFormulario)->toHaveCount(3);
});

it('um professor não envia nem edita a parte do outro', function () {
    expect($this->profRedes->can('responder', $this->parteLogica))->toBeFalse()
        ->and($this->profLogica->can('responder', $this->parteLogica))->toBeTrue();

    $questaoDeLogica = $this->parteLogica->questoes()->first();

    expect($this->profRedes->can('update', $questaoDeLogica))->toBeFalse()
        ->and($this->profLogica->can('update', $questaoDeLogica))->toBeTrue();
});

it('uma disciplina pode ser entregue enquanto a outra ainda está em rascunho', function () {
    enviarParte($this->parteLogica, $this->profLogica);

    expect($this->parteLogica->refresh()->status)->toBe(StatusSolicitacao::Enviada)
        ->and($this->parteRedes->refresh()->status)->toBe(StatusSolicitacao::Aberta)
        ->and($this->prova->refresh()->status)->toBe(StatusSolicitacao::Aberta);

    enviarParte($this->parteRedes, $this->profRedes);

    expect($this->prova->refresh()->status)->toBe(StatusSolicitacao::Enviada);
});

it('a coordenação vê a prova inteira agrupada por disciplina', function () {
    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.show', $this->prova))
        ->assertOk()
        ->assertSee('Avaliação do 2º bimestre')
        ->assertSee('Lógica de Programação')
        ->assertSee('Redes de Computadores')
        ->assertSee('Renato Lima')
        ->assertSee('Marta Reis')
        ->assertSee('3 questão(ões) pedida(s)')
        ->assertSee('2 questão(ões) pedida(s)');
});

it('a coordenação analisa questões das duas disciplinas na mesma tela', function () {
    enviarParte($this->parteLogica, $this->profLogica);
    enviarParte($this->parteRedes, $this->profRedes);

    $daLogica = $this->parteLogica->questoes()->first();
    $deRedes = $this->parteRedes->questoes()->first();

    Livewire::actingAs($this->paeet)
        ->test(DetalheSolicitacao::class, ['solicitacao' => $this->prova])
        ->call('aprovarQuestao', $daLogica->id)
        ->assertDispatched('notificar', function (string $evento, array $dados) {
            return str_contains($dados['mensagem'], 'Lógica de Programação');
        })
        ->call('abrirDevolucao', $deRedes->id)
        ->set('comentarioDaDevolucao', 'Reescreva o enunciado.')
        ->call('devolverQuestao')
        ->assertDispatched('notificar', function (string $evento, array $dados) {
            return str_contains($dados['mensagem'], 'Redes de Computadores');
        });

    expect($daLogica->refresh()->status->elegivelParaProva())->toBeTrue()
        ->and($deRedes->refresh()->status->elegivelParaProva())->toBeFalse();
});

it('a devolução alcança só o professor da disciplina devolvida', function () {
    enviarParte($this->parteLogica, $this->profLogica);
    enviarParte($this->parteRedes, $this->profRedes);

    app(AnalisarQuestaoAction::class)->rejeitar(
        $this->parteRedes->questoes()->first(), $this->paeet, 'A alternativa C está ambígua.',
    );

    expect($this->profRedes->refresh()->esquecerEscopo()->questoesDevolvidas())->toBe(1)
        ->and($this->profLogica->refresh()->esquecerEscopo()->questoesDevolvidas())->toBe(0);

    $this->actingAs($this->profRedes)
        ->get(route('solicitacoes.responder', $this->prova))
        ->assertOk()
        ->assertSee('A coordenação devolveu 1 questão para correção')
        ->assertSee('Redes de Computadores · questão 1');

    $this->actingAs($this->profLogica)
        ->get(route('solicitacoes.responder', $this->prova))
        ->assertOk()
        ->assertDontSee('A coordenação devolveu');
});

it('a prova só conclui quando as duas disciplinas são aprovadas', function () {
    enviarParte($this->parteLogica, $this->profLogica);
    enviarParte($this->parteRedes, $this->profRedes);

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($this->parteLogica->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    expect($this->prova->refresh()->status)->toBe(StatusSolicitacao::EmAnalise);

    foreach ($this->parteRedes->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    expect($this->prova->refresh()->status)->toBe(StatusSolicitacao::Concluida)
        ->and(Questao::query()->aprovadas()->count())->toBe(5);
});

it('a soma dos pesos cobre a prova inteira', function () {
    foreach ($this->parteLogica->questoes()->get() as $questao) {
        completarQuestao($questao, $this->profLogica, peso: 1.5);
    }

    foreach ($this->parteRedes->questoes()->get() as $questao) {
        completarQuestao($questao, $this->profRedes, peso: 2);
    }

    expect((float) $this->parteLogica->somaDosPesos())->toBe(4.5)
        ->and((float) $this->parteRedes->somaDosPesos())->toBe(4.0)
        ->and((float) $this->prova->refresh()->somaDosPesos())->toBe(8.5);
});

it('a listagem mostra as disciplinas e os professores da prova', function () {
    // Uma segunda prova, para a coleção ter mais de uma linha.
    solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->redes, 'professor' => $this->profRedes, 'questoes' => 1],
    ], titulo: 'Recuperação');

    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.index'))
        ->assertOk()
        ->assertSee('Avaliação do 2º bimestre')
        ->assertSee('Recuperação')
        ->assertSee('Lógica de Programação, Redes de Computadores')
        ->assertSee('Renato Lima, Marta Reis');
});

it('o professor vê a prova na listagem dele, com a parte que lhe cabe', function () {
    $this->actingAs($this->profRedes)
        ->get(route('solicitacoes.index'))
        ->assertOk()
        ->assertSee('Avaliação do 2º bimestre')
        ->assertSee('Responder');
});

it('o mesmo professor pode responder duas disciplinas da mesma prova', function () {
    $prova = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $this->logica, 'professor' => $this->profLogica, 'questoes' => 1],
        ['disciplina' => $this->redes, 'professor' => $this->profLogica, 'questoes' => 1],
    ], titulo: 'Prova dupla');

    $this->actingAs($this->profLogica)
        ->get(route('solicitacoes.responder', $prova))
        ->assertOk()
        ->assertSee('Você responde 2 disciplinas nesta prova')
        ->assertSee('Lógica de Programação')
        ->assertSee('Redes de Computadores');

    $componente = Livewire::actingAs($this->profLogica)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $prova]);

    expect($componente->get('formulario'))->toHaveCount(2);
});
