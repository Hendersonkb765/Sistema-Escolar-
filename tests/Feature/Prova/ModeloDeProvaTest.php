<?php

/*
 * O modelo é a moldura da folha. Sem ao menos um, não há como montar
 * prova — e alterá-lo depois não pode mexer em prova já montada, que
 * congelou o próprio snapshot.
 */

use App\Actions\Prova\RenderizarProvaAction;
use App\Enums\NormaDaFolha;
use App\Enums\OrigemDaLogo;
use App\Livewire\ModelosProva\FormularioModeloProva;
use App\Livewire\ModelosProva\ListaModelosProva;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Support\LogoDaFolha;
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

it('nasce usando as duas logos que acompanham o sistema', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->assertSet('origem_logo_esquerda', OrigemDaLogo::Padrao->value)
        ->assertSet('origem_logo_direita', OrigemDaLogo::Padrao->value)
        ->set('nome', 'Sem enviar nada')
        ->set('eixo_id', $this->eixo->id)
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = ModeloProva::query()->where('nome', 'Sem enviar nada')->sole();

    expect($criado->origem_logo_esquerda)->toBe(OrigemDaLogo::Padrao)
        ->and($criado->logo_esquerda_path)->toBeNull()
        ->and($criado->logo('esquerda')->url())->toBe('/'.LogoDaFolha::PADRAO['esquerda'])
        ->and($criado->logo('direita')->url())->toBe('/'.LogoDaFolha::PADRAO['direita']);
});

it('troca um dos lados por uma imagem enviada, sem mexer no outro', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Com logo própria')
        ->set('eixo_id', $this->eixo->id)
        ->set('origem_logo_direita', OrigemDaLogo::Enviada->value)
        ->set('logoDireita', UploadedFile::fake()->image('brasao-escola.png'))
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = ModeloProva::query()->where('nome', 'Com logo própria')->sole();

    expect($criado->origem_logo_esquerda)->toBe(OrigemDaLogo::Padrao)
        ->and($criado->origem_logo_direita)->toBe(OrigemDaLogo::Enviada)
        ->and($criado->logo_direita_path)->not->toBeNull();

    Storage::disk('public')->assertExists($criado->logo_direita_path);
});

it('cobra a imagem de quem escolheu enviar uma e não enviou', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Prometeu e não entregou')
        ->set('eixo_id', $this->eixo->id)
        ->set('origem_logo_esquerda', OrigemDaLogo::Enviada->value)
        ->call('salvar')
        ->assertHasErrors('logoEsquerda');

    expect(ModeloProva::query()->where('nome', 'Prometeu e não entregou')->exists())->toBeFalse();
});

it('não cobra imagem nova de quem já tem uma guardada', function () {
    $this->modelo->update([
        'origem_logo_esquerda' => OrigemDaLogo::Enviada,
        'logo_esquerda_path' => UploadedFile::fake()->image('antiga.png')->store('modelos-prova', 'public'),
    ]);

    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class, ['modelo' => $this->modelo])
        ->set('instituicao', 'Outro nome')
        ->call('salvar')
        ->assertHasNoErrors();

    expect($this->modelo->refresh()->logo_esquerda_path)->not->toBeNull();
});

it('volta para a logo padrão e apaga o arquivo que ninguém mais alcança', function () {
    $caminho = UploadedFile::fake()->image('antiga.png')->store('modelos-prova', 'public');

    $this->modelo->update([
        'origem_logo_direita' => OrigemDaLogo::Enviada,
        'logo_direita_path' => $caminho,
    ]);

    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class, ['modelo' => $this->modelo])
        ->set('origem_logo_direita', OrigemDaLogo::Padrao->value)
        ->call('salvar')
        ->assertHasNoErrors();

    expect($this->modelo->refresh()->logo_direita_path)->toBeNull()
        ->and($this->modelo->origem_logo_direita)->toBe(OrigemDaLogo::Padrao);

    Storage::disk('public')->assertMissing($caminho);
});

it('deixa um lado sem logo nenhuma quando isso é o pedido', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class, ['modelo' => $this->modelo])
        ->set('origem_logo_esquerda', OrigemDaLogo::Nenhuma->value)
        ->call('salvar')
        ->assertHasNoErrors();

    expect($this->modelo->refresh()->logo('esquerda')->url())->toBeNull()
        ->and($this->modelo->logo('direita')->url())->toBe('/'.LogoDaFolha::PADRAO['direita']);
});

it('mostra a prévia do lado escolhido no formulário', function () {
    $componente = Livewire::actingAs($this->paeet)->test(FormularioModeloProva::class);

    $componente->assertSee('/'.LogoDaFolha::PADRAO['esquerda'], escape: false)
        ->assertSee('Já vem com o sistema');

    $componente->set('origem_logo_esquerda', OrigemDaLogo::Nenhuma->value)
        ->assertDontSee('/'.LogoDaFolha::PADRAO['esquerda'], escape: false);
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
