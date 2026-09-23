<?php

/*
 * O modelo é a moldura da folha. Sem ao menos um, não há como montar
 * prova — e alterá-lo depois não pode mexer em prova já montada, que
 * congelou o próprio snapshot.
 */

use App\Actions\Prova\RenderizarProvaAction;
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

it('guarda a logo enviada', function () {
    Livewire::actingAs($this->paeet)
        ->test(FormularioModeloProva::class)
        ->set('nome', 'Com logo')
        ->set('eixo_id', $this->eixo->id)
        ->set('logo', UploadedFile::fake()->image('brasao.png'))
        ->call('salvar')
        ->assertHasNoErrors();

    $criado = ModeloProva::query()->where('nome', 'Com logo')->sole();

    expect($criado->logo_path)->not->toBeNull();
    Storage::disk('public')->assertExists($criado->logo_path);
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
