<?php

/*
 * O caminho completo da imagem pela tela: escolher o arquivo, ver a
 * prévia, salvar a questão e continuar vendo a imagem.
 *
 * A action que guarda o arquivo já tinha teste; o que faltava era a
 * passagem por ela — e era ali que a imagem sumia.
 */

use App\Enums\TipoBlocoQuestao;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Models\Eixo;
use App\Models\Turma;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('public');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica de Programação']],
        duracaoAnos: 2, autor: $this->paeet);

    $turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->solicitacao = solicitacaoCom($this->paeet, $turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica de Programação'],
            'professor' => $this->professor, 'questoes' => 2],
    ]);

    $this->questao = $this->solicitacao->questoes()->orderBy('ordem')->first();

    $this->tela = fn () => Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->solicitacao->refresh()]);
});

it('guarda a imagem assim que o arquivo é escolhido', function () {
    $componente = ($this->tela)()
        ->call('adicionarBloco', $this->questao->id, 'imagem')
        ->set("imagens.{$this->questao->id}.0", UploadedFile::fake()->image('diagrama.png', 800, 600));

    $caminho = $componente->get('formulario')[$this->questao->id]['blocos'][0]['caminho'] ?? null;

    expect($caminho)->not->toBeNull();
    Storage::disk('public')->assertExists($caminho);
});

it('mostra a prévia da imagem na tela logo após o upload', function () {
    $componente = ($this->tela)()
        ->call('adicionarBloco', $this->questao->id, 'imagem')
        ->set("imagens.{$this->questao->id}.0", UploadedFile::fake()->image('diagrama.png'));

    $caminho = $componente->get('formulario')[$this->questao->id]['blocos'][0]['caminho'];

    $componente->assertSee(Storage::disk('public')->url($caminho), escape: false);
});

it('serve os arquivos pelo mesmo host que serve a página', function () {
    // `APP_URL` em desenvolvimento é `http://localhost`, mas o
    // `artisan serve` atende em `127.0.0.1:8000`. Com a URL absoluta, o
    // `<img>` aponta para um host onde não há nada e a imagem
    // simplesmente não carrega — sem erro nenhum na tela.
    //
    // `Storage::fake()` troca o disco mas não a configuração, então é a
    // configuração que se confere aqui.
    expect(config('filesystems.disks.public.url'))->toBe('/storage');
});

it('monta o `src` da prévia a partir do disco, sem host fixo', function () {
    $componente = ($this->tela)()
        ->call('adicionarBloco', $this->questao->id, 'imagem')
        ->set("imagens.{$this->questao->id}.0", UploadedFile::fake()->image('diagrama.png'));

    $caminho = $componente->get('formulario')[$this->questao->id]['blocos'][0]['caminho'];

    $componente->assertSee('src="'.Storage::disk('public')->url($caminho).'"', escape: false);
});

it('mantém a imagem depois de salvar a questão', function () {
    $componente = ($this->tela)()
        ->call('adicionarBloco', $this->questao->id, 'imagem')
        ->set("imagens.{$this->questao->id}.0", UploadedFile::fake()->image('diagrama.png'));

    $caminho = $componente->get('formulario')[$this->questao->id]['blocos'][0]['caminho'];

    $componente
        ->set("formulario.{$this->questao->id}.enunciado", 'Observe o diagrama a seguir.')
        ->set("formulario.{$this->questao->id}.blocos.0.legenda", 'Diagrama do fluxo')
        ->call('salvarQuestao', $this->questao->id)
        ->assertHasNoErrors();

    $bloco = $this->questao->blocos()->sole();

    expect($bloco->tipo)->toBe(TipoBlocoQuestao::Imagem)
        ->and($bloco->caminho)->toBe($caminho)
        ->and($bloco->legenda)->toBe('Diagrama do fluxo');

    Storage::disk('public')->assertExists($caminho);
});

it('continua exibindo a imagem ao reabrir a tela', function () {
    $componente = ($this->tela)()
        ->call('adicionarBloco', $this->questao->id, 'imagem')
        ->set("imagens.{$this->questao->id}.0", UploadedFile::fake()->image('diagrama.png'));

    $caminho = $componente->get('formulario')[$this->questao->id]['blocos'][0]['caminho'];

    $componente
        ->set("formulario.{$this->questao->id}.enunciado", 'Observe o diagrama.')
        ->call('salvarQuestao', $this->questao->id);

    // Nova visita: o rascunho é remontado a partir do banco.
    ($this->tela)()->assertSee(Storage::disk('public')->url($caminho), escape: false);
});
