<?php

/*
 * Telas do milestone 3, sempre com mais de um registro por coleção — é o
 * que ativa a proteção contra lazy loading do framework.
 */

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Enums\StatusSolicitacao;
use App\Livewire\Painel;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Livewire\Solicitacoes\DetalheSolicitacao;
use App\Livewire\Solicitacoes\FormularioSolicitacao;
use App\Livewire\Solicitacoes\ListaSolicitacoes;
use App\Models\Eixo;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['nome' => 'Tecnologia', 'codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->paeet->update(['nome' => 'Coordenação Tecnologia']);

    $this->professor = professor($this->eixo);
    $this->professor->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);

    $this->outroProfessor = professor($this->eixo);
    $this->outroProfessor->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [
        1 => ['Lógica de Programação', 'Redes de Computadores'],
        2 => ['Back-end', 'Front-end'],
    ], duracaoAnos: 2, autor: $this->paeet);

    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];
    $this->logica = $montagem['disciplinas']['Lógica de Programação'];
    $this->redes = $montagem['disciplinas']['Redes de Computadores'];

    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 1, 'nome' => '1 A', 'periodo_letivo' => '2026',
    ]);

    Turma::factory()->doCurso($this->curso, $this->grade)->create([
        'periodo' => 2, 'nome' => '2 A', 'periodo_letivo' => '2026',
    ]);

    $criar = app(CriarSolicitacaoAction::class);

    // Duas solicitações, uma no prazo e outra vencida.
    $this->noPrazo = $criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->logica,
        professor: $this->professor, quantidadeQuestoes: 3, quantidadeAlternativas: 4,
        prazo: now()->addWeek(), observacoes: 'Foco no segundo bimestre',
    );

    $this->vencida = $criar->executar(
        autor: $this->paeet, turma: $this->turma, disciplina: $this->redes,
        professor: $this->outroProfessor, quantidadeQuestoes: 2, quantidadeAlternativas: 4,
        prazo: now()->subDays(2),
    );
});

it('lista as solicitações para a gestão com mais de um registro', function () {
    expect(SolicitacaoProva::query()->count())->toBeGreaterThan(1);

    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.index'))
        ->assertOk()
        ->assertSee('Lógica de Programação')
        ->assertSee('Redes de Computadores')
        ->assertSee('Renato Lima')
        ->assertSee('Marta Reis')
        // A vencida aparece marcada como atrasada, sem estar bloqueada.
        ->assertSee('Atrasada');
});

it('o professor vê apenas as solicitações endereçadas a ele', function () {
    $this->actingAs($this->professor)
        ->get(route('solicitacoes.index'))
        ->assertOk()
        ->assertSee('Lógica de Programação')
        ->assertDontSee('Redes de Computadores');
});

it('filtra por atrasadas', function () {
    Livewire::actingAs($this->paeet)
        ->test(ListaSolicitacoes::class)
        ->set('filtroPrazo', 'atrasadas')
        ->assertSee('Redes de Computadores')
        ->assertDontSee('Lógica de Programação');
});

it('abre o detalhe com as questões pedidas', function () {
    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.show', $this->noPrazo))
        ->assertOk()
        ->assertSee('Lógica de Programação')
        ->assertSee('Foco no segundo bimestre')
        ->assertSee('0 de 3 preenchida(s)');
});

it('abre a tela de resposta do professor com um bloco por questão', function () {
    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->noPrazo))
        ->assertOk()
        ->assertSee('Questão 1')
        ->assertSee('Questão 2')
        ->assertSee('Questão 3')
        // O peso é campo do professor, não um rótulo fixo.
        ->assertSee('Peso da questão')
        ->assertSee('Faltam 3 questão(ões) para poder enviar');
});

it('avisa na tela de resposta que o prazo venceu mas o envio continua aberto', function () {
    $this->actingAs($this->outroProfessor)
        ->get(route('solicitacoes.responder', $this->vencida))
        ->assertOk()
        ->assertSee('O prazo venceu em')
        ->assertSee('Você continua podendo enviar');
});

it('preenche e envia as questões pela tela', function () {
    $componente = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->noPrazo]);

    foreach ($this->noPrazo->questoes()->get() as $questao) {
        $componente
            ->set("formulario.{$questao->id}.enunciado", "Enunciado {$questao->id}")
            ->set("formulario.{$questao->id}.alternativas.0.texto", 'Alternativa A')
            ->set("formulario.{$questao->id}.alternativas.1.texto", 'Alternativa B')
            ->set("formulario.{$questao->id}.alternativas.2.texto", 'Alternativa C')
            ->set("formulario.{$questao->id}.alternativas.3.texto", 'Alternativa D')
            ->call('marcarCorreta', $questao->id, 'B');
    }

    $componente->call('enviar');

    $this->noPrazo->refresh();

    expect($this->noPrazo->status)->toBe(StatusSolicitacao::Enviada)
        ->and($this->noPrazo->enviada_em_atraso)->toBeFalse()
        ->and($this->noPrazo->questoes()->whereNotNull('enunciado')->count())->toBe(3);

    $primeira = $this->noPrazo->questoes()->with('alternativas')->first();

    expect($primeira->alternativas)->toHaveCount(4)
        ->and($primeira->alternativaCorreta()->letra)->toBe('B');
});

it('marcar uma correta desmarca as outras', function () {
    $questao = $this->noPrazo->questoes()->first();

    $componente = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->noPrazo])
        ->call('marcarCorreta', $questao->id, 'A')
        ->call('marcarCorreta', $questao->id, 'C');

    $alternativas = collect($componente->get("formulario.{$questao->id}.alternativas"));

    expect($alternativas->where('correta', true)->pluck('letra')->all())->toBe(['C']);
});

it('encerra a solicitação pela tela e bloqueia o envio', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheSolicitacao::class, ['solicitacao' => $this->noPrazo])
        ->call('confirmar', 'encerrar')
        ->set('motivo', 'Prova já montada')
        ->call('executarAcao');

    expect($this->noPrazo->refresh()->status)->toBe(StatusSolicitacao::Concluida);

    $this->actingAs($this->professor)
        ->get(route('solicitacoes.responder', $this->noPrazo))
        ->assertOk()
        ->assertSee('Esta solicitação está fechada');
});

it('o formulário oferece só as disciplinas do período da turma', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->set('turma_id', $this->turma->id)
        ->assertSee('Lógica de Programação')
        ->assertSee('Redes de Computadores')
        ->assertDontSee('Back-end');
});

it('o formulário do PAEET não pede peso', function () {
    $html = Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->html();

    expect($html)->toContain('definido pelo professor ao escrevê-la')
        ->and($html)->not->toContain('wire:model.live.debounce.500ms="pesos');
});

it('abre uma solicitação pelo formulário', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->set('turma_id', $this->turma->id)
        ->set('disciplina_id', $this->logica->id)
        ->set('professor_id', $this->professor->id)
        ->set('quantidade_questoes', 2)
        ->set('quantidade_alternativas', 5)
        ->set('prazo', now()->addDays(10)->format('Y-m-d\TH:i'))
        ->call('salvar')
        ->assertHasNoErrors();

    $nova = SolicitacaoProva::query()->latest('id')->first();

    expect($nova->quantidade_questoes)->toBe(2)
        ->and($nova->quantidade_alternativas)->toBe(5)
        ->and($nova->questoes()->count())->toBe(2)
        // Peso 1 de partida, para o professor ajustar.
        ->and((float) $nova->somaDosPesos())->toBe(2.0);
});

it('recusa disciplina fora do período da turma no formulário', function () {
    $backend = $this->curso->disciplinas()->where('nome', 'Back-end')->first();

    Livewire::actingAs($this->paeet)
        ->test(FormularioSolicitacao::class)
        ->set('turma_id', $this->turma->id)
        ->set('disciplina_id', $backend->id)
        ->set('professor_id', $this->professor->id)
        ->set('prazo', now()->addWeek()->format('Y-m-d\TH:i'))
        ->call('salvar')
        ->assertHasErrors('disciplina_id');
});

it('mostra ao professor os indicadores de prazo no painel', function () {
    $this->actingAs($this->outroProfessor)
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('A responder')
        ->assertSee('Com prazo vencido')
        ->assertSee('Você ainda pode enviar')
        ->assertSee('Já enviadas');
});

it('mostra à gestão os indicadores de solicitação no painel', function () {
    $this->actingAs($this->paeet)
        ->get(route('painel'))
        ->assertOk()
        ->assertSee('Solicitações aguardando envio')
        ->assertSee('Atrasadas')
        ->assertSee('Prazo vencido não bloqueia o envio')
        ->assertSee('Questões aguardando análise');
});

it('conta corretamente as pendências de cada lado', function () {
    // Duas solicitações abertas, uma delas vencida.
    $this->actingAs($this->paeet)->get(route('painel'))->assertOk();

    Livewire::actingAs($this->paeet)
        ->test(Painel::class)
        ->assertOk();

    expect(SolicitacaoProva::query()->visivelPara($this->paeet)->count())->toBe(2);

    // O professor enxerga só a dele.
    expect(SolicitacaoProva::query()->visivelPara($this->professor)->count())->toBe(1);
});
