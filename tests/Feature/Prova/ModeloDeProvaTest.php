<?php

/*
 * O modelo é a moldura da folha. Sem ao menos um, não há como montar
 * prova — e alterá-lo depois não pode mexer em prova já montada, que
 * congelou o próprio snapshot.
 */

use App\Actions\Prova\RenderizarProvaAction;
use App\Enums\NormaDaFolha;
use App\Livewire\ModelosProva\FormularioModeloProva;
use App\Livewire\ModelosProva\ListaModelosProva;
use App\Models\Eixo;
use App\Models\ModeloProva;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC', 'nome' => 'Tecnologia']);
    $this->paeet = paeet($this->eixo);

    // Duas linhas: é dentro do @foreach populado que as relações são lidas.
    $this->modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'nome' => 'Padrão institucional',
        'instituicao' => 'Escola Técnica Estadual',
        'criado_por' => $this->paeet->id,
    ]);

    $this->segundo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'nome' => 'Recuperação paralela',
        'instituicao' => 'Escola Técnica Estadual',
        'criado_por' => $this->paeet->id,
    ]);
});

it('lista os modelos com o eixo de cada um', function () {
    Livewire::actingAs($this->paeet)
        ->test(ListaModelosProva::class)
        ->assertOk()
        ->assertSee('Padrão institucional')
        ->assertSee('Recuperação paralela')
        ->assertSee('Tecnologia');
});

it('cria um modelo com os campos de identificação escolhidos', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Modelo do curso técnico')
        ->set('eixo_id', $this->eixo->id)
        ->set('instituicao', 'Escola Técnica Estadual')
        ->set('nome_avaliacao', 'Avaliação Bimestral')
        ->set('campos_identificacao', ['aluno', 'turma', 'nota'])
        ->set('instrucoes', 'Prova sem consulta.')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertRedirect(route('modelos-prova.index'));

    $criado = ModeloProva::query()->where('nome', 'Modelo do curso técnico')->sole();

    expect($criado->campos_identificacao)->toBe(['aluno', 'turma', 'nota'])
        ->and($criado->criado_por)->toBe($this->paeet->id);
});

it('guarda as duas logos do cabeçalho', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Com duas logos')
        ->set('eixo_id', $this->eixo->id)
        ->set('logoEsquerda', UploadedFile::fake()->image('brasao-escola.png'))
        ->set('logoDireita', UploadedFile::fake()->image('brasao-estado.png'))
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = ModeloProva::query()->where('nome', 'Com duas logos')->sole();

    expect($criado->logo_esquerda_path)->not->toBeNull()
        ->and($criado->logo_direita_path)->not->toBeNull()
        ->and($criado->logo_direita_path)->not->toBe($criado->logo_esquerda_path);

    Storage::disk('public')->assertExists($criado->logo_esquerda_path);
    Storage::disk('public')->assertExists($criado->logo_direita_path);
});

it('aceita uma logo só, sem exigir a outra', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Com uma logo')
        ->set('eixo_id', $this->eixo->id)
        ->set('logoDireita', UploadedFile::fake()->image('brasao.png'))
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = ModeloProva::query()->where('nome', 'Com uma logo')->sole();

    expect($criado->logo_esquerda_path)->toBeNull()
        ->and($criado->logo_direita_path)->not->toBeNull();
});

it('remove uma das logos sem mexer na outra', function () {
    $this->modelo->update([
        'logo_esquerda_path' => 'modelos-prova/esquerda.png',
        'logo_direita_path' => 'modelos-prova/direita.png',
    ]);

    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class, ['modelo' => $this->modelo])
        ->set('removerLogoEsquerda', true)
        ->call('salvar')
        ->assertHasNoErrors();

    expect($this->modelo->refresh()->logo_esquerda_path)->toBeNull()
        ->and($this->modelo->logo_direita_path)->toBe('modelos-prova/direita.png');
});

it('mostra a amostra da folha com a moldura que está sendo editada', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class, ['modelo' => $this->modelo])
        ->set('instituicao', 'Instituto Federal do Exemplo')
        ->set('rodape', 'Assinado pela coordenação')
        ->assertSee('Instituto Federal do Exemplo')
        ->assertSee('Assinado pela coordenação')
        ->assertSee('column-count: 2', escape: false);
});

it('desativa o modelo sem apagá-lo', function () {
    Livewire::actingAs($this->paeet)
        ->test(ListaModelosProva::class)
        ->call('alternarAtivo', $this->modelo->id)
        ->assertDispatched('notificar');

    expect($this->modelo->refresh()->ativo)->toBeFalse()
        ->and(ModeloProva::query()->whereKey($this->modelo->id)->exists())->toBeTrue();

    Livewire::actingAs($this->paeet)
        ->test(ListaModelosProva::class)
        ->assertDontSee('Padrão institucional')
        ->set('mostrarInativos', true)
        ->assertSee('Padrão institucional');
});

it('mantém o modelo desativado disponível para as provas que o usaram', function () {
    $this->modelo->update(['ativo' => false]);

    expect($this->modelo->refresh()->ativo)->toBeFalse()
        ->and($this->modelo->trashed())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Formatação da folha
|--------------------------------------------------------------------------
*/

it('nasce com a ABNT marcada', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->assertSet('norma', NormaDaFolha::Abnt->value)
        ->assertSet('tamanho', 12)
        ->assertSet('espacamento', 1.5);
});

it('trava tamanho, espaçamento e margens sob a ABNT, dizendo por quê', function () {
    // Procura o atributo booleano, nunca a palavra solta: as classes
    // utilitárias `disabled:` estão presentes sempre.
    $numerosDesabilitados = function (string $html): int {
        preg_match_all('/<input\b[^>]*type="number"[^>]*>/', $html, $encontrados);

        return collect($encontrados[0])
            ->filter(fn (string $tag) => preg_match('/\sdisabled(?=[\s>])/', $tag) === 1)
            ->count();
    };

    $componente = Livewire::actingAs($this->paeet)->test(FormularioModeloProva::class);

    // Corpo, entrelinhas e as quatro margens.
    expect($numerosDesabilitados($componente->html()))->toBe(6);

    $componente->assertSee('Os valores abaixo são da norma')
        ->assertSee('A norma fixa o corpo em 12 pt.');

    $componente->set('norma', NormaDaFolha::Livre->value);

    expect($numerosDesabilitados($componente->html()))->toBe(0);

    $componente->assertDontSee('Os valores abaixo são da norma');
});

it('devolve os valores da norma ao voltar para a ABNT', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('norma', NormaDaFolha::Livre->value)
        ->set('tamanho', 9)
        ->set('espacamento', 1.0)
        ->set('margem_esquerda', 10)
        ->set('norma', NormaDaFolha::Abnt->value)
        ->assertSet('tamanho', 12)
        ->assertSet('espacamento', 1.5)
        ->assertSet('margem_esquerda', 30.0);
});

it('grava os valores da norma, e não o que sobrou do formulário', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Modelo pela norma')
        ->set('eixo_id', $this->eixo->id)
        ->set('norma', NormaDaFolha::Livre->value)
        ->set('tamanho', 9)
        ->set('norma', NormaDaFolha::Abnt->value)
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = ModeloProva::query()->where('nome', 'Modelo pela norma')->sole();

    expect($criado->layout['tamanho'])->toBe(12)
        ->and($criado->layoutDaFolha()->norma)->toBe(NormaDaFolha::Abnt);
});

it('guarda a formatação livre quando ela é escolhida', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Modelo livre')
        ->set('eixo_id', $this->eixo->id)
        ->set('norma', NormaDaFolha::Livre->value)
        ->set('tamanho', 10)
        ->set('espacamento', 1.2)
        ->set('margem_esquerda', 15)
        ->call('salvar')
        ->assertHasNoErrors();

    $layout = ModeloProva::query()->where('nome', 'Modelo livre')->sole()->layoutDaFolha();

    expect($layout->norma)->toBe(NormaDaFolha::Livre)
        ->and($layout->tamanho)->toBe(10)
        ->and($layout->espacamento)->toBe(1.2)
        ->and($layout->margens['esquerda'])->toBe(15.0);
});

it('renderiza a amostra mesmo sem nenhuma prova no banco', function () {
    $html = app(RenderizarProvaAction::class)->amostraDoModelo($this->modelo);

    expect($html)->toContain('Escola Técnica Estadual')
        ->toContain('column-count: 2');
});

/*
|--------------------------------------------------------------------------
| Escopo e autorização
|--------------------------------------------------------------------------
*/

it('nega ao professor o acesso aos modelos', function () {
    $professor = professor($this->eixo);

    Livewire::actingAs($professor)->test(ListaModelosProva::class)->assertForbidden();
    Livewire::actingAs($professor)->test(FormularioModeloProva::class)->assertForbidden();
});

it('nega por id na URL o modelo de outro Eixo', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    Livewire::actingAs($forasteiro)
        ->test(FormularioModeloProva::class, ['modelo' => $this->modelo])
        ->assertForbidden();
});

it('não vaza modelo de outro Eixo na listagem', function () {
    $forasteiro = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    Livewire::actingAs($forasteiro)
        ->test(ListaModelosProva::class)
        ->assertOk()
        ->assertDontSee('Padrão institucional')
        ->assertDontSee('Recuperação paralela');
});
