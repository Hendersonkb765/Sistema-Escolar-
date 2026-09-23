<?php

/*
 * Enunciado com imagem e trechos de código nas linguagens que os cursos
 * usam — Python, JavaScript, HTML, React, Kotlin, Swift e as demais.
 */

use App\Actions\Avaliacao\SalvarBlocosDaQuestaoAction;
use App\Actions\Avaliacao\SalvarQuestaoAction;
use App\Enums\LinguagemCodigo;
use App\Enums\TipoBlocoQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Questoes\ResponderSolicitacao;
use App\Models\Eixo;
use App\Models\QuestaoBloco;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
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
        ['disciplina' => $montagem['disciplinas']['Lógica de Programação'], 'professor' => $this->professor, 'questoes' => 2],
    ], alternativas: 4, prazo: now()->addWeek(),
    );

    $this->questao = $this->solicitacao->questoes()->first();
    $this->salvar = app(SalvarBlocosDaQuestaoAction::class);
});

it('guarda um bloco de código com a linguagem declarada', function (string $linguagem, string $codigo) {
    $this->salvar->executar($this->questao, $this->professor, [
        ['tipo' => 'codigo', 'conteudo' => $codigo, 'linguagem' => $linguagem],
    ]);

    $bloco = $this->questao->refresh()->load('blocos')->blocos->first();

    expect($bloco->tipo)->toBe(TipoBlocoQuestao::Codigo)
        ->and($bloco->linguagem)->toBe(LinguagemCodigo::from($linguagem))
        ->and($bloco->conteudo)->toBe($codigo)
        ->and($bloco->linguagem->classe())->toBe('language-'.$linguagem);
})->with([
    ['python', "def soma(a, b):\n    return a + b"],
    ['javascript', 'const soma = (a, b) => a + b;'],
    ['html', '<ul><li>item</li></ul>'],
    ['jsx', 'const Botao = () => <button>Ok</button>;'],
    ['kotlin', 'fun soma(a: Int, b: Int) = a + b'],
    ['swift', 'func soma(_ a: Int, _ b: Int) -> Int { a + b }'],
    ['sql', 'select * from alunos;'],
]);

it('oferece as linguagens que os cursos usam', function () {
    $valores = LinguagemCodigo::valores();

    expect($valores)->toContain('python', 'javascript', 'html', 'jsx', 'kotlin', 'swift')
        ->and(LinguagemCodigo::Jsx->rotulo())->toBe('React (JSX)')
        ->and(LinguagemCodigo::Swift->rotulo())->toBe('Swift');
});

it('guarda uma imagem enviada e a associa ao bloco', function () {
    $caminho = $this->salvar->guardarImagem(
        UploadedFile::fake()->image('diagrama.png', 800, 600),
        $this->questao,
    );

    $this->salvar->executar($this->questao, $this->professor, [
        ['tipo' => 'imagem', 'caminho' => $caminho, 'legenda' => 'Diagrama do fluxo'],
    ]);

    Storage::disk('public')->assertExists($caminho);

    $bloco = $this->questao->refresh()->load('blocos')->blocos->first();

    expect($bloco->tipo)->toBe(TipoBlocoQuestao::Imagem)
        ->and($bloco->caminho)->toBe($caminho)
        ->and($bloco->legenda)->toBe('Diagrama do fluxo')
        ->and($bloco->urlDaImagem())->toContain($caminho)
        ->and($bloco->caminhoAbsolutoDaImagem())->not->toBeNull();
});

it('recusa arquivo que não é imagem aceita', function () {
    expect(fn () => $this->salvar->guardarImagem(
        UploadedFile::fake()->create('planilha.pdf', 100, 'application/pdf'),
        $this->questao,
    ))->toThrow(RegraDeNegocioException::class, 'JPG, PNG, GIF ou WEBP');
});

it('recusa imagem acima do limite', function () {
    expect(fn () => $this->salvar->guardarImagem(
        UploadedFile::fake()->image('enorme.png')->size(SalvarBlocosDaQuestaoAction::TAMANHO_MAXIMO_IMAGEM_KB + 1),
        $this->questao,
    ))->toThrow(RegraDeNegocioException::class, 'passa de');
});

it('mantém a ordem dos blocos', function () {
    $this->salvar->executar($this->questao, $this->professor, [
        ['tipo' => 'texto', 'conteudo' => 'Considere o programa a seguir:'],
        ['tipo' => 'codigo', 'conteudo' => 'print("oi")', 'linguagem' => 'python'],
        ['tipo' => 'texto', 'conteudo' => 'Qual é a saída?'],
    ]);

    $blocos = $this->questao->refresh()->load('blocos')->blocos;

    expect($blocos->pluck('ordem')->all())->toBe([1, 2, 3])
        ->and($blocos->pluck('tipo')->map->value->all())->toBe(['texto', 'codigo', 'texto']);
});

it('descarta blocos vazios em silêncio', function () {
    $this->salvar->executar($this->questao, $this->professor, [
        ['tipo' => 'texto', 'conteudo' => 'Vale'],
        ['tipo' => 'texto', 'conteudo' => '   '],
        ['tipo' => 'codigo', 'conteudo' => null, 'linguagem' => 'python'],
        ['tipo' => 'imagem', 'caminho' => null],
    ]);

    expect($this->questao->refresh()->load('blocos')->blocos)->toHaveCount(1)
        ->and($this->questao->blocos->first()->conteudo)->toBe('Vale');
});

it('apaga a imagem do disco quando o bloco é removido', function () {
    $caminho = $this->salvar->guardarImagem(
        UploadedFile::fake()->image('antiga.png'), $this->questao,
    );

    $this->salvar->executar($this->questao, $this->professor, [
        ['tipo' => 'imagem', 'caminho' => $caminho],
    ]);

    Storage::disk('public')->assertExists($caminho);

    $this->salvar->executar($this->questao, $this->professor, []);

    Storage::disk('public')->assertMissing($caminho);
    expect($this->questao->refresh()->load('blocos')->blocos)->toHaveCount(0);
});

it('nega salvar blocos em questão de outro professor', function () {
    expect(fn () => $this->salvar->executar($this->questao, professor($this->eixo), [
        ['tipo' => 'texto', 'conteudo' => 'Intruso'],
    ]))->toThrow(AuthorizationException::class);

    expect(QuestaoBloco::query()->count())->toBe(0);
});

it('renderiza o código realçado no detalhe da solicitação', function () {
    $this->salvar->executar($this->questao, $this->professor, [
        ['tipo' => 'codigo', 'conteudo' => "def soma(a, b):\n    return a + b", 'linguagem' => 'python'],
    ]);
    $this->questao->update(['enunciado' => 'O que o programa imprime?']);

    // A segunda questão também tem conteúdo: coleções de um item só
    // escondem violações de lazy loading.
    $outra = $this->solicitacao->questoes()->whereKeyNot($this->questao->id)->first();
    $this->salvar->executar($outra, $this->professor, [
        ['tipo' => 'texto', 'conteudo' => 'Segundo enunciado'],
    ]);
    $outra->update(['enunciado' => 'Segunda questão']);

    $this->actingAs($this->paeet)
        ->get(route('solicitacoes.show', $this->solicitacao))
        ->assertOk()
        ->assertSee('language-python', escape: false)
        ->assertSee('def soma(a, b):', escape: false)
        ->assertSee('Segundo enunciado');
});

it('monta blocos pela tela do professor', function () {
    $componente = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->solicitacao])
        ->call('adicionarBloco', $this->questao->id, 'codigo')
        ->set("formulario.{$this->questao->id}.blocos.0.linguagem", 'kotlin')
        ->set("formulario.{$this->questao->id}.blocos.0.conteudo", 'fun main() {}')
        ->call('adicionarBloco', $this->questao->id, 'texto')
        ->set("formulario.{$this->questao->id}.blocos.1.conteudo", 'Explique o código acima.')
        ->set("formulario.{$this->questao->id}.enunciado", 'Analise:')
        ->call('salvarQuestao', $this->questao->id);

    $componente->assertHasNoErrors();

    $blocos = $this->questao->refresh()->load('blocos')->blocos;

    expect($blocos)->toHaveCount(2)
        ->and($blocos->first()->linguagem)->toBe(LinguagemCodigo::Kotlin)
        ->and($blocos->first()->conteudo)->toBe('fun main() {}')
        ->and($blocos->last()->conteudo)->toBe('Explique o código acima.');
});

it('reordena e remove blocos pela tela', function () {
    $componente = Livewire::actingAs($this->professor)
        ->test(ResponderSolicitacao::class, ['solicitacao' => $this->solicitacao])
        ->call('adicionarBloco', $this->questao->id, 'texto')
        ->set("formulario.{$this->questao->id}.blocos.0.conteudo", 'Primeiro')
        ->call('adicionarBloco', $this->questao->id, 'texto')
        ->set("formulario.{$this->questao->id}.blocos.1.conteudo", 'Segundo')
        ->call('moverBloco', $this->questao->id, 0, 1);

    $blocos = collect($componente->get("formulario.{$this->questao->id}.blocos"));

    expect($blocos->pluck('conteudo')->all())->toBe(['Segundo', 'Primeiro']);

    $componente->call('removerBloco', $this->questao->id, 0);

    expect(collect($componente->get("formulario.{$this->questao->id}.blocos"))->pluck('conteudo')->all())
        ->toBe(['Primeiro']);
});

it('o peso entra na conta de questão completa', function () {
    $completa = fn () => $this->questao->refresh()->estaCompleta(4);

    app(SalvarQuestaoAction::class)->executar(
        questao: $this->questao,
        autor: $this->professor,
        enunciado: 'Enunciado',
        alternativas: [
            ['letra' => 'A', 'texto' => 'A', 'correta' => true],
            ['letra' => 'B', 'texto' => 'B', 'correta' => false],
            ['letra' => 'C', 'texto' => 'C', 'correta' => false],
            ['letra' => 'D', 'texto' => 'D', 'correta' => false],
        ],
        peso: 2,
    );

    expect($completa())->toBeTrue();

    $this->questao->update(['peso' => 0]);

    expect($completa())->toBeFalse();
});
