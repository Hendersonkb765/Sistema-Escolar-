<?php

/*
 * Uma questão devolvida precisa ser impossível de não notar: o professor
 * tem que saber que algo voltou antes de abrir solicitação por
 * solicitação.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Enums\StatusQuestao;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Livewire\Solicitacoes\ListaSolicitacoes;
use App\Models\Eixo;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);
    $this->professor->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação', 'Redes']],
        duracaoAnos: 2, autor: $this->paeet);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $abrirEEnviar = function (string $disciplina, int $questoes) use ($montagem, $turma) {
        $solicitacao = solicitacaoCom($this->paeet, $turma, [[
            'disciplina' => $montagem['disciplinas'][$disciplina],
            'professor' => $this->professor,
            'questoes' => $questoes,
        ]]);

        enviarParte($solicitacao->partes()->first(), $this->professor);

        return $solicitacao->refresh();
    };

    // Duas solicitações: mais de um registro por coleção.
    $this->comDevolucao = $abrirEEnviar('Lógica de Programação', 3);
    $this->semDevolucao = $abrirEEnviar('Redes', 2);

    $this->devolver = function (int $ordem, string $motivo) {
        $questao = $this->comDevolucao->questoes()->where('ordem', $ordem)->first();

        return app(AnalisarQuestaoAction::class)->rejeitar($questao, $this->paeet, $motivo);
    };
});

it('mostra no menu quantas questões voltaram para o professor', function () {
    ($this->devolver)(1, 'A alternativa C está ambígua.');
    ($this->devolver)(2, 'Reescreva o enunciado.');

    $html = $this->actingAs($this->professor)->get(route('painel'))->getContent();

    // Selo no item "Minhas questões" do menu lateral.
    expect($html)->toContain('2 questões devolvidas para correção');
});

it('não mostra selo quando nada foi devolvido', function () {
    $html = $this->actingAs($this->professor)->get(route('painel'))->getContent();

    expect($html)->not->toContain('devolvidas para correção');
});

it('mostra o indicador de devolvidas no painel do professor', function () {
    ($this->devolver)(1, 'Refaça.');

    $this->actingAs($this->professor)
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('Questões devolvidas')
        ->assertSee('Precisam da sua correção');
});

it('destaca na lista de solicitações qual tem questão devolvida', function () {
    ($this->devolver)(1, 'Refaça.');
    ($this->devolver)(3, 'Também refaça.');

    $this->actingAs($this->professor)
        ->get(route('solicitacoes.index'))
        ->assertOk()
        ->assertSee('2 devolvida(s)')
        // A ação muda de "Responder" para "Corrigir", com a contagem.
        ->assertSee('Corrigir 2');
});

it('filtra as solicitações com questão devolvida', function () {
    ($this->devolver)(1, 'Refaça.');

    Livewire::actingAs($this->professor)
        ->test(ListaSolicitacoes::class)
        ->set('filtroPrazo', 'devolvidas')
        ->assertSee('Lógica de Programação')
        ->assertDontSee('Redes');
});

it('abre a tela de resposta com o aviso no topo e o motivo de cada uma', function () {
    ($this->devolver)(1, 'A alternativa C está ambígua.');
    ($this->devolver)(3, 'Falta contexto no enunciado.');

    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->comDevolucao))
        ->assertOk()
        ->assertSee('A coordenação devolveu 2 questões para correção')
        ->assertSee('Reenviar corrigida')
        // O motivo de cada uma aparece já no topo, sem precisar rolar.
        ->assertSee('A alternativa C está ambígua.')
        ->assertSee('Falta contexto no enunciado.')
        // E a questão devolvida se identifica no próprio título.
        ->assertSee('devolvida para correção');
});

it('usa o singular quando só uma questão volta', function () {
    ($this->devolver)(2, 'Ajuste a redação.');

    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->comDevolucao))
        ->assertOk()
        ->assertSee('A coordenação devolveu 1 questão para correção');
});

it('não mostra o aviso quando nada foi devolvido', function () {
    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->semDevolucao))
        ->assertOk()
        ->assertDontSee('devolveu')
        ->assertDontSee('Reenviar corrigida');
});

it('o aviso some depois do reenvio', function () {
    $questao = ($this->devolver)(1, 'Refaça.');

    expect($this->professor->refresh()->esquecerEscopo()->questoesDevolvidas())->toBe(1);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->comDevolucao->refresh()])
        ->call('reenviarQuestao', $questao->id);

    expect($this->professor->refresh()->esquecerEscopo()->questoesDevolvidas())->toBe(0);

    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->comDevolucao->refresh()))
        ->assertOk()
        ->assertDontSee('A coordenação devolveu');
});

it('a coordenação também vê quantas devolveu', function () {
    ($this->devolver)(1, 'Refaça.');

    $this->actingAs($this->paeet)
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('Devolvidas aos professores')
        ->assertSee('Aguardando correção');

    $html = $this->actingAs($this->paeet)->get(route('painel'))->getContent();

    expect($html)->toContain('questões aguardando análise');
});

it('conta apenas as questões do próprio professor', function () {
    $outro = professor($this->eixo);
    $outro->update(['nome' => 'Marta Reis']);

    ($this->devolver)(1, 'Refaça.');

    expect($this->professor->refresh()->esquecerEscopo()->questoesDevolvidas())->toBe(1)
        ->and($outro->questoesDevolvidas())->toBe(0);

    $this->actingAs($outro)
        ->get(route('painel'))
        ->assertOk()
        ->assertDontSee('Questões devolvidas');
});

it('a solicitação sabe quais disciplinas foram devolvidas', function () {
    ($this->devolver)(2, 'Refaça.');
    ($this->devolver)(3, 'Refaça também.');

    expect($this->comDevolucao->refresh()->questoesDevolvidas())->toBe(2)
        ->and($this->comDevolucao->disciplinasDevolvidas()->all())->toBe(['Lógica de Programação']);
});

it('a questão devolvida volta a ser editável', function () {
    $questao = ($this->devolver)(1, 'Refaça.');

    expect($questao->status)->toBe(StatusQuestao::Rejeitada)
        ->and($questao->status->aguardaProfessor())->toBeTrue()
        ->and($questao->status->editavelPeloProfessor())->toBeTrue()
        ->and($this->professor->can('update', $questao))->toBeTrue();
});
