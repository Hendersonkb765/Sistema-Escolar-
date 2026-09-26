<?php

/*
 * Avisos flutuantes e envio para análise.
 *
 * Salvar rascunho não recarrega a página, então a confirmação precisa vir
 * por evento (`notificar`) e não por flash de sessão, que só apareceria na
 * próxima navegação.
 */

use App\Actions\Avaliacao\EncerrarSolicitacaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Models\Eixo;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']],
        duracaoAnos: 2, autor: $this->paeet);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->abrir = fn (int $questoes = 2, ?string $prazo = null) => solicitacaoCom(
        $this->paeet,
        $this->turma,
        [[
            'disciplina' => $montagem['disciplinas']['Lógica de Programação'],
            'professor' => $this->professor,
            'questoes' => $questoes,
        ]],
        prazo: $prazo ? now()->parse($prazo) : now()->addWeek(),
    );

    $this->alternativas = [
        ['letra' => 'A', 'texto' => 'A', 'correta' => true],
        ['letra' => 'B', 'texto' => 'B', 'correta' => false],
        ['letra' => 'C', 'texto' => 'C', 'correta' => false],
        ['letra' => 'D', 'texto' => 'D', 'correta' => false],
    ];

    $this->completar = function (SolicitacaoProva $solicitacao) {
        foreach ($solicitacao->questoes()->get() as $questao) {
            completarQuestao($questao, $this->professor);
        }
    };

    /** A única parte da solicitação, nos testes de fluxo simples. */
    $this->parteDe = fn (SolicitacaoProva $solicitacao) => $solicitacao->partes()
        ->with(['disciplina', 'solicitacao'])
        ->first();
});

// ---------------------------------------------------------------- avisos

it('avisa que o rascunho foi salvo, sem recarregar a página', function () {
    $solicitacao = ($this->abrir)();
    $questao = $solicitacao->questoes()->with('parte')->first();

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao])
        ->set("formulario.{$questao->id}.enunciado", 'Um enunciado qualquer')
        ->call('salvarQuestao', $questao->id)
        ->assertDispatched('notificar',
            tipo: 'sucesso',
            titulo: 'Rascunho salvo',
        );
});

it('cita o número da questão no aviso de rascunho', function () {
    $solicitacao = ($this->abrir)();
    $segunda = $solicitacao->questoes()->with('parte')->get()->firstWhere('ordem', 2);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao])
        ->set("formulario.{$segunda->id}.enunciado", 'Enunciado da segunda')
        ->call('salvarQuestao', $segunda->id)
        ->assertDispatched('notificar', function (string $evento, array $dados) {
            return str_contains($dados['mensagem'], 'Questão 2')
                && str_contains($dados['mensagem'], 'Nada foi enviado ainda');
        });
});

it('avisa ao salvar todas as questões de uma vez', function () {
    $solicitacao = ($this->abrir)(3);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao])
        ->call('salvarTudo')
        ->assertDispatched('notificar', function (string $evento, array $dados) {
            return $dados['tipo'] === 'sucesso'
                && str_contains($dados['mensagem'], 'As 3 questões foram salvas');
        });
});

it('avisa por erro quando a regra impede salvar', function () {
    $solicitacao = ($this->abrir)();
    $questao = $solicitacao->questoes()->with('parte')->first();

    app(EncerrarSolicitacaoAction::class)->encerrar($solicitacao, $this->paeet);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao->refresh()])
        ->set("formulario.{$questao->id}.enunciado", 'Tarde demais')
        ->call('salvarQuestao', $questao->id)
        ->assertDispatched('notificar', tipo: 'erro');
});

it('o layout monta o painel de avisos', function () {
    $html = $this->actingAs($this->professor)->get(route('painel'))->getContent();

    expect($html)->toContain('x-on:notificar.window');
});

// ----------------------------------------------------------------- envio

it('envia as questões para análise pela tela', function () {
    $solicitacao = ($this->abrir)(2);
    ($this->completar)($solicitacao);

    // O envio é por disciplina e mantém o professor na tela: ele pode ter
    // outra parte para entregar.
    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao->refresh()])
        ->call('enviarParte', ($this->parteDe)($solicitacao)->id)
        ->assertDispatched('notificar', tipo: 'sucesso', titulo: 'Enviado para análise');

    $solicitacao->refresh();

    expect($solicitacao->status)->toBe(StatusSolicitacao::Enviada)
        ->and($solicitacao->partes()->first()->enviada_em)->not->toBeNull()
        ->and($solicitacao->questoes()->get()->every(fn (Questao $q) => $q->status === StatusQuestao::Enviada))
        ->toBeTrue();
});

it('grava o que está na tela antes de enviar', function () {
    $solicitacao = ($this->abrir)(1);
    $questao = $solicitacao->questoes()->first();

    $componente = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao])
        ->set("formulario.{$questao->id}.enunciado", 'Escrito e enviado sem salvar antes')
        ->set("formulario.{$questao->id}.habilidade", 'Interpretar estruturas de repetição')
        ->set("formulario.{$questao->id}.peso", '3')
        ->set("formulario.{$questao->id}.alternativas.0.texto", 'Alternativa A')
        ->set("formulario.{$questao->id}.alternativas.1.texto", 'Alternativa B')
        ->set("formulario.{$questao->id}.alternativas.2.texto", 'Alternativa C')
        ->set("formulario.{$questao->id}.alternativas.3.texto", 'Alternativa D')
        ->call('marcarCorreta', $questao->id, 'C');

    $componente->call('enviarParte', ($this->parteDe)($solicitacao)->id);

    $questao->refresh();

    expect($questao->enunciado)->toBe('Escrito e enviado sem salvar antes')
        ->and($questao->habilidade)->toBe('Interpretar estruturas de repetição')
        ->and((float) $questao->peso)->toBe(3.0)
        ->and($questao->status)->toBe(StatusQuestao::Enviada)
        ->and($questao->alternativas()->where('correta', true)->value('letra'))->toBe('C');
});

it('não avisa duas vezes ao enviar', function () {
    $solicitacao = ($this->abrir)(1);
    ($this->completar)($solicitacao);

    // Um aviso só: o do envio. O de rascunho é suprimido.
    $eventos = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao->refresh()])
        ->call('enviarParte', ($this->parteDe)($solicitacao)->id)
        ->effects['dispatches'] ?? [];

    expect(collect($eventos)->where('name', 'notificar'))->toHaveCount(1);
});

it('recusa o envio com questões incompletas e explica quais', function () {
    $solicitacao = ($this->abrir)(3);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao])
        ->call('enviarParte', ($this->parteDe)($solicitacao)->id)
        ->assertDispatched('notificar', function (string $evento, array $dados) {
            return $dados['tipo'] === 'erro'
                && str_contains($dados['mensagem'], 'As questões 1, 2, 3');
        })
        ->assertNoRedirect();

    expect($solicitacao->refresh()->status)->toBe(StatusSolicitacao::Aberta);
});

it('avisa que o envio foi aceito em atraso', function () {
    $solicitacao = ($this->abrir)(1, now()->subDays(2)->toDateTimeString());
    ($this->completar)($solicitacao);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao->refresh()])
        ->call('enviarParte', ($this->parteDe)($solicitacao)->id)
        ->assertDispatched('notificar', function (string $evento, array $dados) {
            return str_contains($dados['mensagem'], 'em atraso');
        });

    expect($solicitacao->partes()->first()->enviada_em_atraso)->toBeTrue();
});

it('mostra o resumo antes de confirmar o envio', function () {
    $solicitacao = ($this->abrir)(2);
    ($this->completar)($solicitacao);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao->refresh()])
        ->call('confirmarEnvio', ($this->parteDe)($solicitacao)->id)
        ->assertSee('para análise?')
        ->assertSee('ficam bloqueadas para edição até a coordenação')
        // A entrega é por disciplina; as outras seguem independentes.
        ->assertSee('As outras disciplinas desta prova seguem');
});

it('a solicitação enviada aparece como aguardando análise para a gestão', function () {
    $solicitacao = ($this->abrir)(1);
    ($this->completar)($solicitacao);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao->refresh()])
        ->call('enviarParte', ($this->parteDe)($solicitacao)->id);

    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.show', $solicitacao))
        ->assertOk()
        ->assertSee('Enviada');

    $this->actingAs($this->paeet)
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('Questões aguardando análise');
});

it('bloqueia a edição depois do envio', function () {
    $solicitacao = ($this->abrir)(1);
    ($this->completar)($solicitacao);

    Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $solicitacao->refresh()])
        ->call('enviarParte', ($this->parteDe)($solicitacao)->id);

    $questao = $solicitacao->questoes()->first();

    expect(fn () => app(SalvarQuestaoAction::class)->executar(
        $questao->refresh(), $this->professor, 'Mudei de ideia', $this->alternativas,
    ))->toThrow(RegraDeNegocioException::class, 'aguardando análise');
});
