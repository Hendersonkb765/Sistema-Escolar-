<?php

/*
 * Depois de devolvida, a questão precisa voltar a ser editável — mesmo
 * que a parte já tenha sido entregue. E ao abrir uma questão específica,
 * a tela mostra só aquela, não a disciplina inteira.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Enums\StatusQuestao;
use App\Livewire\Questoes\ListaQuestoes;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);
    $this->professor->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']],
        duracaoAnos: 2, autor: $this->paeet);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->prova = solicitacaoCom($this->paeet, $turma, [[
        'disciplina' => $montagem['disciplinas']['Lógica de Programação'],
        'professor' => $this->professor,
        'questoes' => 4,
    ]]);

    $this->parte = $this->prova->partes()->with(['disciplina', 'solicitacao'])->first();

    enviarParte($this->parte, $this->professor);

    // A coordenação devolve a questão 2.
    $this->devolvida = app(AnalisarQuestaoAction::class)->rejeitar(
        $this->prova->questoes()->where('ordem', 2)->first(),
        $this->paeet,
        'A alternativa C está ambígua.',
    );

    /** Conta os campos habilitados de uma questão no HTML. */
    $this->camposHabilitados = function (string $html, int $questaoId): int {
        preg_match_all('/<(?:input|textarea)\b[^>]*>/', $html, $tags);

        return collect($tags[0])
            ->filter(fn (string $tag) => str_contains($tag, "formulario.{$questaoId}."))
            ->reject(fn (string $tag) => preg_match('/\sdisabled(?=[\s>])/', $tag) === 1)
            ->count();
    };
});

// ------------------------------------------------- questão editável

it('a questão devolvida volta a ser editável mesmo com a parte já entregue', function () {
    expect($this->parte->refresh()->enviada_em)->not->toBeNull()
        ->and($this->devolvida->status)->toBe(StatusQuestao::Rejeitada)
        ->and($this->devolvida->status->editavelPeloProfessor())->toBeTrue()
        ->and($this->professor->can('update', $this->devolvida))->toBeTrue();

    $html = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->prova])
        ->html();

    // Enunciado, peso e as 4 alternativas da questão devolvida.
    expect(($this->camposHabilitados)($html, $this->devolvida->id))->toBeGreaterThan(0);
});

it('as questões que seguem em análise continuam bloqueadas', function () {
    $emAnalise = $this->prova->questoes()->where('ordem', 1)->first();

    $html = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->prova])
        ->html();

    expect($emAnalise->status)->toBe(StatusQuestao::Enviada)
        ->and(($this->camposHabilitados)($html, $emAnalise->id))->toBe(0);
});

it('salva e reenvia a correção pela tela', function () {
    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->prova])
        ->set("formulario.{$this->devolvida->id}.enunciado", 'Enunciado reescrito')
        ->call('salvarQuestao', $this->devolvida->id)
        ->assertDispatched('notificar', titulo: 'Rascunho salvo')
        ->call('reenviarQuestao', $this->devolvida->id)
        ->assertDispatched('notificar', titulo: 'Correção enviada');

    $this->devolvida->refresh();

    expect($this->devolvida->enunciado)->toBe('Enunciado reescrito')
        ->and($this->devolvida->status)->toBe(StatusQuestao::Enviada)
        ->and($this->devolvida->versao)->toBe(2);
});

// ------------------------------------------- foco em uma questão

it('abre apenas a questão escolhida quando vem da lista', function () {
    $html = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, [
            'solicitacao' => $this->prova,
            'questao' => $this->devolvida->id,
        ])
        ->html();

    $outras = $this->prova->questoes()->whereKeyNot($this->devolvida->id)->get();

    expect($html)->toContain("formulario.{$this->devolvida->id}.enunciado");

    foreach ($outras as $outra) {
        expect($html)->not->toContain("formulario.{$outra->id}.enunciado");
    }
});

it('oferece o caminho de volta para a disciplina inteira', function () {
    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, [
            'solicitacao' => $this->prova,
            'questao' => $this->devolvida->id,
        ])
        ->assertSee('Ver todas as questões')
        ->call('limparFoco')
        ->assertSee('Questão 1');
});

it('o link de corrigir leva direto à questão devolvida', function () {
    $html = Livewire::actingAs($this->professor)
        ->test(ListaQuestoes::class)
        ->set('filtroStatus', StatusQuestao::Rejeitada->value)
        ->html();

    $esperado = route('solicitacoes.responder', [
        'solicitacao' => $this->prova,
        'questao' => $this->devolvida->id,
    ]);

    expect($html)->toContain(e($esperado))
        ->and($html)->toContain('Corrigir');
});

it('ignora o foco em questão de outro professor', function () {
    $outroProfessor = professor($this->eixo);

    $alheia = Questao::query()->whereKeyNot($this->devolvida->id)->first();

    // Um id válido, mas de quem não é dono, não abre nada indevido.
    $this->actingAs($outroProfessor)
        ->get(route('solicitacoes.responder', [
            'solicitacao' => $this->prova,
            'questao' => $alheia->id,
        ]))
        ->assertForbidden();
});
