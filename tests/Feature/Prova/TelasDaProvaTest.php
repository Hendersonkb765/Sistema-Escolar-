<?php

/*
 * As telas de prova: montagem, listagem, pré-visualização e downloads.
 *
 * Toda coleção exibida aqui é testada com no mínimo dois registros — duas
 * turmas, dois modelos, duas disciplinas, duas provas —, porque é dentro
 * dos `@foreach` populados que as relações são acessadas.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\MontarProvaAction;
use App\Enums\StatusProva;
use App\Livewire\Provas\DetalheProva;
use App\Livewire\Provas\ListaProvas;
use App\Livewire\Provas\MontarProva;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->profRedes = professor($this->eixo);
    $this->profRedes->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->paeet);
    $this->curso = $montagem['curso'];

    // Duas turmas: a listagem e o select precisam de mais de uma linha.
    $this->turma = Turma::factory()->doCurso($this->curso, $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);
    $this->outraTurma = Turma::factory()->doCurso($this->curso, $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 B']);

    // Dois modelos, idem.
    $this->modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'nome' => 'Padrão institucional',
        'criado_por' => $this->paeet->id,
    ]);
    ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'nome' => 'Recuperação',
        'criado_por' => $this->paeet->id,
    ]);

    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 3],
        ['disciplina' => $montagem['disciplinas']['Redes'], 'professor' => $this->profRedes, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');

    $professores = [$this->profLogica, $this->profRedes];

    foreach ($solicitacao->partes()->orderBy('ordem')->get() as $indice => $parte) {
        enviarParte($parte, $professores[$indice]);
    }

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($solicitacao->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    $this->montarProva = fn (?string $titulo = null) => app(MontarProvaAction::class)->executar(
        autor: $this->paeet,
        turma: $this->turma,
        modelo: $this->modelo,
        titulo: $titulo ?? 'Avaliação bimestral',
    );
});

/*
|--------------------------------------------------------------------------
| Montagem
|--------------------------------------------------------------------------
*/

it('lista as questões aprovadas agrupadas por disciplina, com as duas turmas no select', function () {
    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->assertSee('1 A')
        ->assertSee('1 B')
        ->assertSee('Padrão institucional')
        ->assertSee('Recuperação')
        ->assertSee('Lógica')
        ->assertSee('Redes')
        ->assertSee('Renato Lima')
        ->assertSee('Marta Reis')
        ->assertSee('5 selecionada(s)');
});

it('monta a prova e leva para a tela dela', function () {
    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->set('modelo_prova_id', $this->modelo->id)
        ->set('titulo', 'Avaliação bimestral')
        ->set('colunas', 2)
        ->call('montar')
        ->assertHasNoErrors()
        ->assertRedirect(route('provas.show', Prova::query()->latest('id')->first()));

    $prova = Prova::query()->latest('id')->first();

    expect($prova->questoes()->count())->toBe(5)
        ->and($prova->colunas())->toBe(2);
});

it('oferece os campos de cabeçalho com o modelo como marca-d\'água', function () {
    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->set('modelo_prova_id', $this->modelo->id)
        ->assertSee('Nome no topo da folha')
        ->assertSee('Nome da avaliação')
        // O valor do modelo entra como sugestão, não preenchido: em
        // branco é o modelo que vale.
        ->assertSet('instituicao', '')
        ->assertSet('nome_avaliacao', '');
});

it('monta a prova com o cabeçalho escolhido na tela', function () {
    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->set('modelo_prova_id', $this->modelo->id)
        ->set('titulo', 'Avaliação bimestral')
        ->set('instituicao', 'Colégio Parceiro Dom Pedro')
        ->set('nome_avaliacao', 'Avaliação de Recuperação')
        ->call('montar')
        ->assertHasNoErrors();

    $prova = Prova::query()->latest('id')->first();

    expect($prova->instituicao)->toBe('Colégio Parceiro Dom Pedro')
        ->and($prova->nomeDaAvaliacao())->toBe('Avaliação de Recuperação');
});

it('recusa montar sem questão nenhuma marcada', function () {
    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->set('questoesEscolhidas', [])
        ->call('montar')
        ->assertHasErrors('questoesEscolhidas');

    expect(Prova::query()->count())->toBe(0);
});

it('marca e desmarca a disciplina inteira de uma vez', function () {
    $logica = $this->turma->curso->disciplinas()->where('nome', 'Lógica')->sole();

    $componente = Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id)
        ->call('alternarDisciplina', $logica->id);

    expect($componente->get('questoesEscolhidas'))->toHaveCount(2);

    $componente->call('alternarDisciplina', $logica->id);

    expect($componente->get('questoesEscolhidas'))->toHaveCount(5);
});

it('desmarca a disciplina inteira mesmo com os ids vindos como string', function () {
    // É assim que o navegador devolve o valor de um checkbox: texto.
    // Com comparação estrita contra int, o "desmarcar todas" virava
    // "marcar todas" e a prova saía com questão repetida na tela.
    $logica = $this->turma->curso->disciplinas()->where('nome', 'Lógica')->sole();

    $componente = Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->turma->id);

    $componente->set('questoesEscolhidas', array_map(
        strval(...),
        $componente->get('questoesEscolhidas'),
    ));

    $componente->call('alternarDisciplina', $logica->id);

    expect($componente->get('questoesEscolhidas'))->toHaveCount(2);

    $componente->assertSee('2 selecionada(s)');
});

it('avisa quando a turma ainda não tem questão aprovada', function () {
    Livewire::actingAs($this->paeet)
        ->test(MontarProva::class)
        ->set('turma_id', $this->outraTurma->id)
        ->assertSee('Nenhuma questão aprovada nesta turma');
});

/*
|--------------------------------------------------------------------------
| Listagem
|--------------------------------------------------------------------------
*/

it('lista as provas montadas com duas linhas', function () {
    ($this->montarProva)('Avaliação bimestral');
    ($this->montarProva)('Segunda chamada');

    Livewire::actingAs($this->paeet)
        ->test(ListaProvas::class)
        ->assertOk()
        ->assertSee('Avaliação bimestral')
        ->assertSee('Segunda chamada')
        ->assertSee('1 A');
});

it('não vaza prova de outro Eixo na listagem', function () {
    ($this->montarProva)('Avaliação bimestral');

    $outroEixo = Eixo::factory()->create(['codigo' => 'ADM']);
    $forasteiro = paeet($outroEixo);

    Livewire::actingAs($forasteiro)
        ->test(ListaProvas::class)
        ->assertOk()
        ->assertDontSee('Avaliação bimestral');
});

it('mostra ao professor só as provas que têm questão dele', function () {
    $prova = ($this->montarProva)('Avaliação bimestral');

    // Um professor do mesmo Eixo, mas sem nenhuma questão nesta prova.
    $estranho = professor($this->eixo);
    $estranho->update(['nome' => 'Iara Melo', 'email' => 'iara@exemplo.test']);

    Livewire::actingAs($this->profLogica)
        ->test(ListaProvas::class)
        ->assertSee('Avaliação bimestral');

    Livewire::actingAs($estranho)
        ->test(ListaProvas::class)
        ->assertDontSee('Avaliação bimestral');

    expect($prova->questoes()->where('professor_id', $estranho->id)->exists())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Pré-visualização e downloads
|--------------------------------------------------------------------------
*/

it('pré-visualiza a folha em duas colunas, com as duas disciplinas', function () {
    $prova = ($this->montarProva)();

    Livewire::actingAs($this->paeet)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->assertOk()
        ->assertSee('column-count: 2', escape: false)
        ->assertSee('Lógica')
        ->assertSee('Redes')
        ->assertSee('Enunciado da questão 1')
        ->assertSee('Baixar PDF')
        ->assertSee('Baixar Word');
});

it('mostra a composição por disciplina com a faixa de números', function () {
    $prova = ($this->montarProva)();

    Livewire::actingAs($this->paeet)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->assertSeeInOrder(['Lógica', '1 a 3', 'Redes', '4 a 5']);
});

it('oferece o gabarito à gestão e esconde do professor', function () {
    $prova = ($this->montarProva)();

    Livewire::actingAs($this->paeet)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->assertSee('Gerar gabarito')
        ->assertDontSee('Ver com gabarito');

    Livewire::actingAs($this->profLogica)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->assertOk()
        ->assertDontSee('Gerar gabarito');
});

it('explica na tela o que conferir antes de importar o gabarito', function () {
    $prova = ($this->montarProva)();

    // Uma letra fora do A–D da folha de referência.
    $prova->questoes()->where('numero', 1)->sole()->update([
        'alternativas_snapshot' => [['letra' => 'E', 'texto' => 'Quinta', 'correta' => true]],
    ]);

    Livewire::actingAs($this->paeet)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->assertSee('Confira antes de importar o gabarito')
        ->assertSee('A, B, C, D');

    // O professor não vê o gabarito, e portanto não vê os avisos dele.
    Livewire::actingAs($this->profLogica)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->assertDontSee('Confira antes de importar o gabarito');
});

it('não mostra aviso nenhum quando não há o que conferir', function () {
    Livewire::actingAs($this->paeet)
        ->test(DetalheProva::class, ['prova' => ($this->montarProva)()])
        ->assertDontSee('Confira antes de importar o gabarito');
});

it('baixa o gabarito em CSV', function () {
    $prova = ($this->montarProva)();

    $resposta = $this->actingAs($this->paeet)->get(route('provas.gabarito', $prova));

    $resposta->assertOk();

    expect($resposta->headers->get('content-type'))->toContain('text/csv')
        ->and($resposta->headers->get('content-disposition'))->toContain('.csv');
});

it('nega o gabarito ao professor, mesmo pela URL', function () {
    $prova = ($this->montarProva)();

    $this->actingAs($this->profLogica)->get(route('provas.gabarito', $prova))->assertForbidden();
});

it('baixa o PDF e o Word pela rota', function () {
    $prova = ($this->montarProva)();

    $pdf = $this->actingAs($this->paeet)->get(route('provas.pdf', $prova));

    expect($pdf->getStatusCode())->toBe(200)
        ->and(substr($pdf->getContent(), 0, 4))->toBe('%PDF');

    $docx = $this->actingAs($this->paeet)->get(route('provas.docx', $prova));

    expect($docx->getStatusCode())->toBe(200)
        ->and(substr($docx->getContent(), 0, 2))->toBe('PK');
});

it('ignora ?gabarito=1 para quem não pode ver o gabarito', function () {
    $prova = ($this->montarProva)();

    $comoGestao = $this->actingAs($this->paeet)
        ->get(route('provas.pdf', ['prova' => $prova, 'gabarito' => 1]));
    $comoProfessor = $this->actingAs($this->profLogica)
        ->get(route('provas.pdf', ['prova' => $prova, 'gabarito' => 1]));

    expect($comoGestao->headers->get('content-disposition'))->toContain('gabarito')
        ->and($comoProfessor->headers->get('content-disposition'))->not->toContain('gabarito');
});

it('marca a prova como aplicada', function () {
    $prova = ($this->montarProva)();

    Livewire::actingAs($this->paeet)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->call('marcarComoAplicada')
        ->assertDispatched('notificar');

    expect($prova->refresh()->status)->toBe(StatusProva::Aplicada);
});

it('explica por que a prova já aplicada não se aplica de novo', function () {
    $prova = ($this->montarProva)();

    Livewire::actingAs($this->paeet)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->call('marcarComoAplicada')
        ->call('marcarComoAplicada')
        ->assertDispatched(
            'notificar',
            fn (string $evento, array $dados) => str_contains(
                $dados['mensagem'] ?? '', 'já foi marcada como aplicada'
            )
        )
        ->assertSee('Esta prova já foi marcada como aplicada.');

    expect($prova->refresh()->status)->toBe(StatusProva::Aplicada);
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega ao professor a montagem de provas', function () {
    Livewire::actingAs($this->profLogica)
        ->test(MontarProva::class)
        ->assertForbidden();
});

it('nega por id na URL a prova de outro Eixo', function () {
    $prova = ($this->montarProva)();

    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    Livewire::actingAs($forasteiro)
        ->test(DetalheProva::class, ['prova' => $prova])
        ->assertForbidden();

    $this->actingAs($forasteiro)->get(route('provas.pdf', $prova))->assertForbidden();
    $this->actingAs($forasteiro)->get(route('provas.docx', $prova))->assertForbidden();
});

it('nega o download ao professor sem questão na prova', function () {
    $prova = ($this->montarProva)();

    $estranho = professor($this->eixo);

    $this->actingAs($estranho)->get(route('provas.pdf', $prova))->assertForbidden();
});
