<?php

/*
 * As telas de documento, com dados de verdade e mais de uma linha em cada
 * coleção — modelos, turmas, alunos e compartilhamentos.
 */

use App\Actions\Documento\CompartilharModeloAction;
use App\Actions\Documento\ResponderCompartilhamentoAction;
use App\Enums\TipoDeDocumento;
use App\Livewire\Documentos\FormularioModeloDocumento;
use App\Livewire\Documentos\GerarDocumentos;
use App\Livewire\Documentos\ListaCompartilhamentos;
use App\Livewire\Documentos\ListaModelosDocumento;
use App\Models\Aluno;
use App\Models\CompartilhamentoDeModelo;
use App\Models\Eixo;
use App\Models\ModeloDocumento;
use App\Models\Turma;
use Livewire\Livewire;

beforeEach(function () {
    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->admin = paeetAdmin($this->eixo);
    $this->admin->update(['nome' => 'Helena Dias', 'email' => 'helena@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica']], autor: $this->admin);
    $this->curso = $montagem['curso'];
    $this->grade = $montagem['grade'];

    $this->turma = Turma::factory()->doCurso($this->curso, $this->grade)
        ->create(['periodo' => 1, 'nome' => '1 A']);
    Turma::factory()->doCurso($this->curso, $this->grade)
        ->create(['periodo' => 1, 'nome' => '1 B']);

    foreach ([['Marina Alves', '20261001'], ['Caio Prado', '20261002'], ['Rita Souza', '20261003']] as [$nome, $ra]) {
        Aluno::factory()->naTurma($this->turma)->create(['nome' => $nome, 'ra' => $ra]);
    }

    $this->modelo = ModeloDocumento::factory()->noEixo($this->eixo)->create([
        'nome' => 'Autorização de visita', 'criado_por' => $this->admin->id,
    ]);
    ModeloDocumento::factory()->noEixo($this->eixo)->coletivo()->create([
        'nome' => 'Lista de entrega de material', 'criado_por' => $this->admin->id,
    ]);
});

it('abre a lista de modelos com mais de um registro', function () {
    $this->actingAs($this->admin)->get(route('documentos.index'))
        ->assertOk()
        ->assertSee('Autorização de visita')
        ->assertSee('Lista de entrega de material')
        ->assertSee('Helena Dias');
});

it('abre a tela de gerar com turmas, modelos e alunos', function () {
    $this->actingAs($this->admin)->get(route('documentos.gerar'))
        ->assertOk()
        ->assertSee('Marina Alves')
        ->assertSee('Caio Prado')
        ->assertSee('Rita Souza');
});

it('começa com todos os alunos da turma marcados', function () {
    Livewire::actingAs($this->admin)
        ->test(GerarDocumentos::class, ['turma' => $this->turma->id])
        ->assertCount('escolhidos', 3);
});

it('troca a lista de alunos ao trocar de turma', function () {
    $outra = Turma::query()->where('nome', '1 B')->sole();
    Aluno::factory()->naTurma($outra)->create(['nome' => 'Bruno Lima']);

    Livewire::actingAs($this->admin)
        ->test(GerarDocumentos::class)
        ->set('turma_id', $outra->id)
        ->assertCount('escolhidos', 1)
        ->assertSee('Bruno Lima')
        ->assertDontSee('Marina Alves');
});

it('baixa o PDF dos alunos marcados', function () {
    $resposta = Livewire::actingAs($this->admin)
        ->test(GerarDocumentos::class)
        ->set('modelo_id', $this->modelo->id)
        ->set('turma_id', $this->turma->id)
        ->set('escolhidos', Aluno::query()->where('turma_id', $this->turma->id)->pluck('id')->take(2)->all())
        ->call('gerar')
        ->assertFileDownloaded('autorizacao-de-visita-1-a.pdf');

    expect($resposta)->not->toBeNull();
});

/*
 * Botão bloqueado sem explicação vira chamado de suporte: a tela diz o
 * que falta, e o motivo é o mesmo que a Action usaria.
 */
it('diz o que falta em vez de só bloquear o botão', function () {
    Livewire::actingAs($this->admin)
        ->test(GerarDocumentos::class)
        ->set('turma_id', $this->turma->id)
        ->call('desmarcarTodos')
        ->assertSee('Marque pelo menos um aluno');
});

it('não gera quando ninguém está marcado', function () {
    Livewire::actingAs($this->admin)
        ->test(GerarDocumentos::class)
        ->set('modelo_id', $this->modelo->id)
        ->set('turma_id', $this->turma->id)
        ->call('desmarcarTodos')
        ->call('gerar')
        ->assertNoFileDownloaded()
        ->assertDispatched('notificar');
});

it('salva um modelo novo pelo formulário', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('nome', 'Declaração de matrícula')
        ->set('tipo', 'individual')
        ->set('corpo', 'Declaro que {{ aluno.nome }}, RA {{ aluno.ra }}, está matriculado.')
        ->set('por_pagina', 3)
        ->call('salvar')
        ->assertRedirect(route('documentos.index'));

    $salvo = ModeloDocumento::query()->where('nome', 'Declaração de matrícula')->sole();

    expect($salvo->por_pagina)->toBe(3)
        ->and($salvo->criado_por)->toBe($this->admin->id)
        ->and($salvo->tipo)->toBe(TipoDeDocumento::Individual);
});

it('recusa salvar com campo que não existe, e diz qual é', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('nome', 'Com erro')
        ->set('corpo', 'Olá {{ aluno.nomee }} e {{ inventado }}.')
        ->call('salvar')
        ->assertHasErrors('corpo');

    expect(ModeloDocumento::query()->where('nome', 'Com erro')->exists())->toBeFalse();
});

it('recusa salvar o campo do aluno num documento coletivo', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('nome', 'Coletivo errado')
        ->set('tipo', 'coletivo')
        ->set('corpo', 'Autorizo {{ aluno.nome }}.')
        ->call('salvar')
        ->assertHasErrors('corpo');
});

it('força uma via por página no documento coletivo', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('nome', 'Coletivo certo')
        ->set('tipo', 'coletivo')
        ->set('por_pagina', 3)
        ->set('corpo', 'Turma {{ turma.nome }}: {{ lista_de_alunos }}')
        ->call('salvar');

    expect(ModeloDocumento::query()->where('nome', 'Coletivo certo')->sole()->por_pagina)->toBe(1);
});

it('mostra a prévia com os campos já trocados', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('corpo', 'Aluno: **{{ aluno.nome }}** da turma {{ turma.nome }}')
        // O negrito atravessa o campo. Procurar a tag, e não a ausência
        // de asteriscos: a caixa de edição mostra o texto-fonte, com as
        // marcas, e é assim que tem de ser.
        ->assertSeeHtml('<strong>Marina Alves de Souza</strong>')
        ->assertSee('1 A');
});

it('compartilha pela lista e avisa que depende do aceite', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));
    $colega->update(['nome' => 'Rui Barros']);

    Livewire::actingAs($this->admin)
        ->test(ListaModelosDocumento::class)
        ->call('abrirCompartilhamento', $this->modelo->id)
        ->set('destinatario_id', $colega->id)
        ->set('mensagem', 'Serve para você?')
        ->call('compartilhar')
        ->assertDispatched('notificar');

    expect(CompartilhamentoDeModelo::query()->count())->toBe(1)
        ->and(ModeloDocumento::query()->visivelPara($colega)->count())->toBe(0);
});

it('mostra na lista com quantos o modelo foi compartilhado', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));
    $outro = paeet(Eixo::factory()->create(['codigo' => 'SAU']));

    $compartilhar = app(CompartilharModeloAction::class);
    $aceita = $compartilhar->executar($this->modelo, $this->admin, $colega);
    $compartilhar->executar($this->modelo, $this->admin, $outro);

    app(ResponderCompartilhamentoAction::class)->aceitar($aceita, $colega);

    $this->actingAs($this->admin)->get(route('documentos.index'))
        ->assertOk()
        ->assertSee('1 de 2 aceitaram');
});

it('abre a caixa de recebidos com duas ofertas', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));
    $colega->update(['nome' => 'Rui Barros']);

    $compartilhar = app(CompartilharModeloAction::class);
    $compartilhar->executar($this->modelo, $this->admin, $colega);
    $compartilhar->executar(
        ModeloDocumento::query()->where('nome', 'Lista de entrega de material')->sole(),
        $this->admin,
        $colega,
    );

    $this->actingAs($colega)->get(route('documentos.compartilhados'))
        ->assertOk()
        ->assertSee('Autorização de visita')
        ->assertSee('Lista de entrega de material')
        ->assertSee('Helena Dias');
});

it('aceita pela tela e o modelo entra na lista de quem aceitou', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    $oferta = app(CompartilharModeloAction::class)->executar($this->modelo, $this->admin, $colega);

    Livewire::actingAs($colega)
        ->test(ListaCompartilhamentos::class)
        ->call('aceitar', $oferta->id)
        ->assertDispatched('notificar');

    $this->actingAs($colega)->get(route('documentos.index'))
        ->assertOk()
        ->assertSee('Autorização de visita');
});

it('recusa pela tela e o modelo não entra na lista', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    $oferta = app(CompartilharModeloAction::class)->executar($this->modelo, $this->admin, $colega);

    Livewire::actingAs($colega)
        ->test(ListaCompartilhamentos::class)
        ->call('recusar', $oferta->id)
        ->assertDispatched('notificar');

    $this->actingAs($colega)->get(route('documentos.index'))
        ->assertOk()
        ->assertDontSee('Autorização de visita');
});

/*
 * Aceitar às cegas um documento que vai para a família de um aluno não é
 * decisão nenhuma: o texto precisa estar visível antes.
 */
it('deixa quem recebeu ler o texto antes de decidir', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    $this->modelo->update(['corpo' => 'Autorizo a ida ao Museu da Língua Portuguesa.']);

    $oferta = app(CompartilharModeloAction::class)->executar($this->modelo, $this->admin, $colega);

    Livewire::actingAs($colega)
        ->test(ListaCompartilhamentos::class)
        ->call('espiar', $oferta->id)
        ->assertSee('Museu da Língua Portuguesa');
});

it('não deixa um terceiro espiar compartilhamento alheio', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'SAU']));

    $oferta = app(CompartilharModeloAction::class)->executar($this->modelo, $this->admin, $colega);

    Livewire::actingAs($intruso)
        ->test(ListaCompartilhamentos::class)
        ->call('espiar', $oferta->id)
        ->assertForbidden();
});

it('não vaza compartilhamento alheio na caixa de ninguém', function () {
    $colega = paeet(Eixo::factory()->create(['codigo' => 'ADM']));
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'SAU']));

    app(CompartilharModeloAction::class)->executar($this->modelo, $this->admin, $colega);

    $this->actingAs($intruso)->get(route('documentos.compartilhados'))
        ->assertOk()
        ->assertDontSee('Autorização de visita');
});

it('nega ao professor todas as telas de documento', function () {
    $professor = professor($this->eixo);

    foreach (['documentos.index', 'documentos.gerar', 'documentos.criar', 'documentos.compartilhados'] as $rota) {
        $this->actingAs($professor)->get(route($rota))->assertForbidden();
    }
});

it('não vaza modelo de outro Eixo na listagem', function () {
    $outro = Eixo::factory()->create(['codigo' => 'ADM']);
    ModeloDocumento::factory()->noEixo($outro)->create(['nome' => 'Modelo Escondido']);

    $this->actingAs($this->admin)->get(route('documentos.index'))
        ->assertOk()
        ->assertDontSee('Modelo Escondido');
});

/*
 * "Clique para acrescentar" precisa acrescentar. O botão que parece
 * clicável e não faz nada é pior que texto estático — a pessoa clica,
 * nada acontece, e ela não sabe se errou ou se o sistema quebrou.
 */
it('acrescenta ao texto o campo em que se clica', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('corpo', 'Primeira linha.')
        ->call('inserirCampo', 'aluno.nome')
        ->assertSet('corpo', "Primeira linha.\n{{ aluno.nome }}");
});

it('já escreve o rótulo nos campos que aceitam um', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('corpo', 'Texto.')
        ->call('inserirCampo', 'assinatura')
        ->assertSet('corpo', "Texto.\n{{ assinatura: rótulo }}");
});

it('não insere campo que este tipo de documento não aceita', function () {
    Livewire::actingAs($this->admin)
        ->test(FormularioModeloDocumento::class)
        ->set('tipo', 'coletivo')
        ->set('corpo', 'Texto.')
        ->call('inserirCampo', 'aluno.nome')
        ->assertSet('corpo', 'Texto.')
        ->call('inserirCampo', 'inventado')
        ->assertSet('corpo', 'Texto.');
});
