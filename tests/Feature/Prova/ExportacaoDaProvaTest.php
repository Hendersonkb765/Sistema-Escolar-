<?php

/*
 * Pré-visualização na tela, PDF e Word saem da mesma prova montada, com
 * o mesmo layout de duas colunas.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\GerarDocxDaProvaAction;
use App\Actions\Prova\GerarPdfDaProvaAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Prova\RenderizarProvaAction;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\Turma;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);
    $this->professor = professor($this->eixo);
    $this->outroProfessor = professor($this->eixo);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->paeet);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])->create([
        'periodo' => 1, 'nome' => '1 A',
    ]);

    $this->modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'instituicao' => 'Escola Técnica Estadual',
        'nome_avaliacao' => 'Prova Bimestral',
        'rodape' => 'Boa prova!',
        'criado_por' => $this->paeet->id,
    ]);

    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->professor, 'questoes' => 2],
        ['disciplina' => $montagem['disciplinas']['Redes'], 'professor' => $this->outroProfessor, 'questoes' => 2],
    ]);

    $partes = $solicitacao->partes()->with(['disciplina', 'solicitacao'])->get();
    enviarParte($partes[0], $this->professor);
    enviarParte($partes[1], $this->outroProfessor);

    // Uma questão com código, para conferir que o trecho sai nos arquivos.
    $primeira = $solicitacao->questoes()->where('ordem', 1)->first();
    $primeira->blocos()->create([
        'ordem' => 1, 'tipo' => 'codigo',
        'conteudo' => "def soma(a, b):\n    return a + b",
        'linguagem' => 'python',
    ]);

    $analisar = app(AnalisarQuestaoAction::class);
    foreach ($solicitacao->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    $this->provaMontada = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Avaliação bimestral',
        dataAplicacao: now()->addWeek(),
        configuracao: ['colunas' => 2, 'mostrar_pesos' => true],
    );
});

// ----------------------------------------------------- pré-visualização

it('renderiza a folha em duas colunas com cabeçalho e questões', function () {
    $html = app(RenderizarProvaAction::class)->paraTela($this->provaMontada);

    expect($html)->toContain('column-count: 2')
        ->toContain('Escola Técnica Estadual')
        ->toContain('Prova Bimestral')
        ->toContain('Avaliação bimestral')
        ->toContain('Turma 1 A')
        ->toContain('Aluno(a):')
        ->toContain('Boa prova!')
        // Blocos de disciplina com a faixa de numeração.
        ->toContain('Lógica — questões 1 a 2')
        ->toContain('Redes — questões 3 a 4')
        // O código do enunciado.
        ->toContain('def soma(a, b)');
});

it('respeita a escolha de uma coluna', function () {
    $prova = app(MontarProvaAction::class)->executar(
        autor: $this->paeet, turma: $this->turma, modelo: $this->modelo,
        titulo: 'Coluna única', configuracao: ['colunas' => 1],
    );

    expect(app(RenderizarProvaAction::class)->paraTela($prova))->toContain('column-count: 1');
});

it('esconde o gabarito por padrão e mostra quando pedido', function () {
    $renderizar = app(RenderizarProvaAction::class);

    expect($renderizar->paraTela($this->provaMontada))->not->toContain('Gabarito')
        ->and($renderizar->paraTela($this->provaMontada, comGabarito: true))->toContain('Gabarito');
});

it('mostra o peso quando a montagem pediu', function () {
    expect(app(RenderizarProvaAction::class)->paraTela($this->provaMontada))
        ->toContain('peso 1,00');
});

// ------------------------------------------------------------------ PDF

it('gera um PDF válido', function () {
    $conteudo = app(GerarPdfDaProvaAction::class)->conteudo($this->provaMontada, $this->paeet);

    expect(substr($conteudo, 0, 4))->toBe('%PDF')
        ->and(strlen($conteudo))->toBeGreaterThan(1000);
});

it('guarda o PDF e registra o caminho na prova', function () {
    $caminho = app(GerarPdfDaProvaAction::class)->guardar($this->provaMontada, $this->paeet);

    Storage::disk('public')->assertExists($caminho);

    expect($this->provaMontada->refresh()->pdf_path)->toBe($caminho)
        ->and($caminho)->toContain('.pdf')
        ->and($caminho)->toContain('avaliacao-bimestral');
});

it('o PDF com gabarito não sobrescreve o da prova', function () {
    $acao = app(GerarPdfDaProvaAction::class);

    $daProva = $acao->guardar($this->provaMontada, $this->paeet);
    $doGabarito = $acao->guardar($this->provaMontada, $this->paeet, comGabarito: true);

    expect($doGabarito)->not->toBe($daProva)
        ->and($doGabarito)->toContain('-gabarito')
        ->and($this->provaMontada->refresh()->pdf_path)->toBe($daProva);
});

// ----------------------------------------------------------------- Word

it('gera um DOCX válido', function () {
    $conteudo = app(GerarDocxDaProvaAction::class)->conteudo($this->provaMontada, $this->paeet);

    // Um .docx é um zip: começa com a assinatura PK.
    expect(substr($conteudo, 0, 2))->toBe('PK')
        ->and(strlen($conteudo))->toBeGreaterThan(1000);
});

it('o DOCX traz o conteúdo da prova e as duas colunas', function () {
    $conteudo = app(GerarDocxDaProvaAction::class)->conteudo($this->provaMontada, $this->paeet);

    $temporario = tempnam(sys_get_temp_dir(), 'docx');
    file_put_contents($temporario, $conteudo);

    $zip = new ZipArchive;
    $zip->open($temporario);
    $documento = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($temporario);

    expect($documento)->toContain('Escola Técnica Estadual')
        ->toContain('Avaliação bimestral')
        // Seção contínua com duas colunas.
        ->toContain('w:num="2"')
        ->toContain('def soma')
        ->toContain('Lógica')
        // Quadro de identificação, com os mesmos campos da folha.
        ->toContain('Aluno(a):')
        ->toContain('Matrícula:')
        ->toContain('Turma:')
        // Linha para preencher é borda de célula, e não fileira de `_`,
        // que quebraria no fim da célula. (A máscara da data segue com
        // os seus poucos sublinhados.)
        ->toContain('w:tcBorders')
        ->not->toContain(str_repeat('_', 12));
});

it('guarda o DOCX e registra o caminho na prova', function () {
    $caminho = app(GerarDocxDaProvaAction::class)->guardar($this->provaMontada, $this->paeet);

    Storage::disk('public')->assertExists($caminho);

    expect($this->provaMontada->refresh()->docx_path)->toBe($caminho)
        ->and($caminho)->toContain('.docx');
});

// ------------------------------------------------------------ permissão

it('nega a exportação a quem não pode ver a prova', function () {
    $intruso = paeet(Eixo::factory()->create(['codigo' => 'ADM']));

    expect(fn () => app(GerarPdfDaProvaAction::class)->conteudo($this->provaMontada, $intruso))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(GerarDocxDaProvaAction::class)->conteudo($this->provaMontada, $intruso))
        ->toThrow(AuthorizationException::class);
});

it('o professor autor de uma questão pode ver a prova', function () {
    expect($this->professor->can('view', $this->provaMontada))->toBeTrue();

    $semQuestao = professor($this->eixo);

    expect($semQuestao->can('view', $this->provaMontada))->toBeFalse();
});
