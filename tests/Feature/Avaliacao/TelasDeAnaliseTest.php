<?php

/*
 * Telas de análise: a coordenação aprova ou devolve cada questão, e o
 * professor vê o motivo e reenvia a correção.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Livewire\Questoes\ListaQuestoes;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Livewire\Solicitacoes\DetalheSolicitacao;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->paeet->update(['nome' => 'Coordenação Tecnologia']);

    $this->professor = professor($this->eixo);
    $this->professor->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']],
        duracaoAnos: 2, autor: $this->paeet);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->solicitacao = solicitacaoCom($this->paeet, $turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica de Programação'], 'professor' => $this->professor, 'questoes' => 3],
    ], alternativas: 4, prazo: now()->addWeek(),
    );

    $alternativas = [
        ['letra' => 'A', 'texto' => 'A', 'correta' => true],
        ['letra' => 'B', 'texto' => 'B', 'correta' => false],
        ['letra' => 'C', 'texto' => 'C', 'correta' => false],
        ['letra' => 'D', 'texto' => 'D', 'correta' => false],
    ];
    $this->alternativas = $alternativas;

    enviarParte($this->solicitacao->partes()->first(), $this->professor);

    $this->questao = fn (int $ordem) => $this->solicitacao->questoes()->where('ordem', $ordem)->first();
});

it('mostra os botões de aprovar e devolver na visão da coordenação', function () {
    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.show', $this->solicitacao))
        ->assertOk()
        ->assertSee('Aprovar')
        ->assertSee('Devolver')
        ->assertSee('Aprovar 3 pendente(s)')
        // Cada disciplina é um bloco, com o professor responsável.
        ->assertSee('Lógica de Programação')
        ->assertSee('Renato Lima');
});

it('não mostra os botões de análise ao professor', function () {
    $this->actingAs($this->professor)
        ->get(route('solicitacoes.show', $this->solicitacao))
        ->assertOk()
        ->assertDontSee('Devolver para correção')
        ->assertDontSee('Aprovar 3 pendente(s)');
});

it('aprova uma questão pela tela', function () {
    $questao = ($this->questao)(1);

    Livewire::actingAs($this->paeet)
        ->test(DetalheSolicitacao::class, ['solicitacao' => $this->solicitacao])
        ->set("comentarioDaAprovacao.{$questao->id}", 'Boa cobertura do conteúdo.')
        ->call('aprovarQuestao', $questao->id)
        ->assertDispatched('notificar', tipo: 'sucesso', titulo: 'Aprovada');

    $questao->refresh();

    expect($questao->status)->toBe(StatusQuestao::Aprovada)
        ->and($questao->feedbacks()->first()->comentario)->toBe('Boa cobertura do conteúdo.');
});

it('devolve uma questão com motivo pela tela', function () {
    $questao = ($this->questao)(2);

    Livewire::actingAs($this->paeet)
        ->test(DetalheSolicitacao::class, ['solicitacao' => $this->solicitacao])
        ->call('abrirDevolucao', $questao->id)
        ->assertSee('O que precisa ser corrigido?')
        ->set('comentarioDaDevolucao', 'A alternativa C está ambígua.')
        ->call('devolverQuestao')
        ->assertDispatched('notificar', titulo: 'Devolvida para correção');

    $questao->refresh();

    expect($questao->status)->toBe(StatusQuestao::Rejeitada)
        ->and($questao->feedbacks()->first()->comentario)->toBe('A alternativa C está ambígua.');
});

it('recusa devolver sem motivo pela tela', function () {
    $questao = ($this->questao)(1);

    Livewire::actingAs($this->paeet)
        ->test(DetalheSolicitacao::class, ['solicitacao' => $this->solicitacao])
        ->call('abrirDevolucao', $questao->id)
        ->set('comentarioDaDevolucao', '')
        ->call('devolverQuestao')
        ->assertDispatched('notificar', tipo: 'erro');

    expect($questao->refresh()->status)->toBe(StatusQuestao::Enviada);
});

it('aprova todas as pendentes de uma vez', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheSolicitacao::class, ['solicitacao' => $this->solicitacao])
        ->call('aprovarPendentes')
        ->assertDispatched('notificar', titulo: 'Análise concluída');

    expect($this->solicitacao->refresh()->status)->toBe(StatusSolicitacao::Concluida)
        ->and($this->solicitacao->questoes()->where('status', StatusQuestao::Aprovada)->count())->toBe(3);
});

it('o professor não aprova pela tela, mesmo chamando o método direto', function () {
    Livewire::actingAs($this->professor)
        ->test(DetalheSolicitacao::class, ['solicitacao' => $this->solicitacao])
        ->call('aprovarQuestao', ($this->questao)(1)->id)
        ->assertForbidden();

    expect(($this->questao)(1)->status)->toBe(StatusQuestao::Enviada);
});

it('um PAEET de outro eixo não alcança a tela de análise', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    $this->actingAs($intruso)
        ->get(route('solicitacoes.show', $this->solicitacao))
        ->assertForbidden();
});

it('mostra o histórico da análise na visão da coordenação', function () {
    $analisar = app(AnalisarQuestaoAction::class);
    $analisar->rejeitar(($this->questao)(1), $this->paeet, 'Reescreva o enunciado.');
    $analisar->aprovar(($this->questao)(2), $this->paeet, 'Perfeita.');

    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.show', $this->solicitacao))
        ->assertOk()
        ->assertSee('Histórico da análise')
        ->assertSee('Reescreva o enunciado.')
        ->assertSee('Perfeita.');
});

it('o professor vê o motivo da devolução e o botão de reenviar', function () {
    app(AnalisarQuestaoAction::class)->rejeitar(
        ($this->questao)(1), $this->paeet, 'A alternativa C está ambígua.',
    );

    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->solicitacao))
        ->assertOk()
        ->assertSee('O que a coordenação pediu')
        ->assertSee('A alternativa C está ambígua.')
        ->assertSee('Reenviar corrigida');
});

it('o professor corrige e reenvia pela tela', function () {
    $questao = ($this->questao)(1);

    app(AnalisarQuestaoAction::class)->rejeitar($questao, $this->paeet, 'Reescreva.');

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->solicitacao->refresh()])
        ->set("formulario.{$questao->id}.enunciado", 'Enunciado reescrito')
        ->call('reenviarQuestao', $questao->id)
        ->assertDispatched('notificar', titulo: 'Correção enviada');

    $questao->refresh();

    expect($questao->status)->toBe(StatusQuestao::Enviada)
        ->and($questao->versao)->toBe(2)
        ->and($questao->enunciado)->toBe('Enunciado reescrito');
});

it('lista a fila de análise para a coordenação', function () {
    $this->actingAs($this->paeet)
        ->get(route('questoes.index'))
        ->assertOk()
        ->assertSee('Análise de questões')
        ->assertSee('Renato Lima')
        ->assertSee('3 questão(ões) aguardando sua análise');
});

it('o professor vê as próprias questões na lista, com atalho para corrigir', function () {
    app(AnalisarQuestaoAction::class)->rejeitar(($this->questao)(1), $this->paeet, 'Refaça.');

    // O título vem do layout, então a página inteira é que o mostra.
    $this->actingAs($this->professor)
        ->get(route('questoes.index'))
        ->assertOk()
        ->assertSee('Minhas questões');

    Livewire::actingAs($this->professor)
        ->test(ListaQuestoes::class)
        ->assertSee('Corrigir')
        ->assertSee('Rejeitada');
});

it('a fila não vaza questões de outro eixo', function () {
    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);
    $outroPaeet = paeet($outroEixo);
    $outroProfessor = professor($outroEixo);
    $outroProfessor->update(['nome' => 'Docente Alheio']);

    $montagem = cursoComGrade($outroEixo, [1 => ['Contabilidade']], duracaoAnos: 2, autor: $outroPaeet);
    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 C',
    ]);

    $alheia = solicitacaoCom($outroPaeet, $turma, [[
        'disciplina' => $montagem['disciplinas']['Contabilidade'],
        'professor' => $outroProfessor,
        'questoes' => 2,
    ]]);

    enviarParte($alheia->partes()->first(), $outroProfessor);

    $this->actingAs($this->paeet)
        ->get(route('questoes.index'))
        ->assertOk()
        ->assertSee('Renato Lima')
        ->assertDontSee('Docente Alheio');

    expect(Questao::query()->visivelPara($this->paeet)->count())->toBe(3);
});

it('avisa quando a coordenação escreveu a própria questão', function () {
    $paeetQueLeciona = paeet($this->eixo);
    $paeetQueLeciona->update(['nome' => 'Paula Enes']);

    $disciplina = $this->solicitacao->partes()->with('disciplina')->first()->disciplina;

    $solicitacao = solicitacaoCom($this->paeet, $this->solicitacao->turma, [
        ['disciplina' => $disciplina, 'professor' => $paeetQueLeciona, 'questoes' => 2],
    ]);

    enviarParte($solicitacao->partes()->first(), $paeetQueLeciona);

    $this->actingAs($paeetQueLeciona)
        ->get(route('solicitacoes.show', $solicitacao))
        ->assertOk()
        ->assertSee('a análise cabe a outra pessoa da coordenação');
});
