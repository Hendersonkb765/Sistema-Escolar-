<?php

/*
 * A folha segue as normas da ABNT por padrão, e o cabeçalho leva duas
 * logos — uma em cada extremo.
 *
 * A NBR 14724 trata de trabalho acadêmico, não de prova. O que se
 * aproveita dela é a tipografia, que é o que a escola cobra: A4, margens
 * 3/2/2/3 cm, Arial ou Times New Roman 12, entrelinhas 1,5, justificado,
 * e citação longa (aqui, o trecho de código) em 10 pt com espaçamento
 * simples.
 */

use App\Actions\Avaliacao\AnalisarQuestaoAction;
use App\Actions\Prova\GerarDocxDaProvaAction;
use App\Actions\Prova\GerarPdfDaProvaAction;
use App\Actions\Prova\MontarProvaAction;
use App\Actions\Prova\RenderizarProvaAction;
use App\Enums\NormaDaFolha;
use App\Enums\OrigemDaLogo;
use App\Models\Eixo;
use App\Models\ModeloProva;
use App\Models\QuestaoBloco;
use App\Models\Turma;
use App\Support\LayoutDaFolha;
use App\Support\LogoDaFolha;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->eixo = Eixo::factory()->create(['codigo' => 'TEC']);
    $this->paeet = paeet($this->eixo);

    $this->profLogica = professor($this->eixo);
    $this->profLogica->update(['nome' => 'Renato Lima', 'email' => 'renato@exemplo.test']);
    $this->profRedes = professor($this->eixo);
    $this->profRedes->update(['nome' => 'Marta Reis', 'email' => 'marta@exemplo.test']);

    $montagem = cursoComGrade($this->eixo, [1 => ['Lógica', 'Redes']], duracaoAnos: 2, autor: $this->paeet);

    $this->turma = Turma::factory()->doCurso($montagem['curso'], $montagem['grade'])
        ->create(['periodo' => 1, 'nome' => '1 A']);

    // As duas logos, já no disco público.
    $this->logoEsquerda = UploadedFile::fake()->image('escola.png', 200, 200)
        ->store('modelos-prova', 'public');
    $this->logoDireita = UploadedFile::fake()->image('estado.png', 200, 200)
        ->store('modelos-prova', 'public');

    $this->modelo = ModeloProva::factory()->create([
        'eixo_id' => $this->eixo->id,
        'instituicao' => 'Escola Técnica Estadual',
        'logo_esquerda_path' => $this->logoEsquerda,
        'logo_direita_path' => $this->logoDireita,
        'origem_logo_esquerda' => OrigemDaLogo::Enviada,
        'origem_logo_direita' => OrigemDaLogo::Enviada,
        'layout' => ['norma' => 'abnt', 'fonte' => 'sans'],
        'criado_por' => $this->paeet->id,
    ]);

    $solicitacao = solicitacaoCom($this->paeet, $this->turma, [
        ['disciplina' => $montagem['disciplinas']['Lógica'], 'professor' => $this->profLogica, 'questoes' => 2],
        ['disciplina' => $montagem['disciplinas']['Redes'], 'professor' => $this->profRedes, 'questoes' => 2],
    ], titulo: 'Avaliação bimestral');

    $professores = [$this->profLogica, $this->profRedes];

    foreach ($solicitacao->partes()->orderBy('ordem')->get() as $indice => $parte) {
        enviarParte($parte, $professores[$indice]);
    }

    // Uma questão com código, para conferir o tratamento de citação longa.
    QuestaoBloco::query()->create([
        'questao_id' => $solicitacao->questoes()->orderBy('id')->first()->id,
        'ordem' => 1,
        'tipo' => 'codigo',
        'linguagem' => 'python',
        'conteudo' => "for i in range(3):\n    print(i)",
    ]);

    $analisar = app(AnalisarQuestaoAction::class);

    foreach ($solicitacao->questoes()->get() as $questao) {
        $analisar->aprovar($questao, $this->paeet);
    }

    $this->montar = fn (?ModeloProva $modelo = null) => app(MontarProvaAction::class)->executar(
        autor: $this->paeet,
        turma: $this->turma,
        modelo: $modelo ?? $this->modelo,
        titulo: 'Avaliação bimestral',
    );
});

/*
|--------------------------------------------------------------------------
| Duas logos
|--------------------------------------------------------------------------
*/

it('põe uma logo em cada extremo do cabeçalho', function () {
    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain('class="logo esquerda"')
        ->toContain('class="logo direita"')
        ->toContain(Storage::disk('public')->url($this->logoEsquerda))
        ->toContain(Storage::disk('public')->url($this->logoDireita));

    // A da esquerda vem antes da do centro, que vem antes da da direita.
    $posicoes = [
        strpos($html, Storage::disk('public')->url($this->logoEsquerda)),
        strpos($html, 'Escola Técnica Estadual'),
        strpos($html, Storage::disk('public')->url($this->logoDireita)),
    ];

    expect($posicoes)->toBe(collect($posicoes)->sort()->values()->all());
});

it('usa as logos que acompanham o sistema quando nada foi enviado', function () {
    // É o padrão de qualquer modelo novo: ninguém precisa enviar as
    // mesmas duas imagens a cada modelo que cria.
    $modelo = ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]);

    expect($modelo->origem_logo_esquerda)->toBe(OrigemDaLogo::Padrao)
        ->and($modelo->origem_logo_direita)->toBe(OrigemDaLogo::Padrao);

    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)($modelo));

    expect($html)->toContain('/'.LogoDaFolha::PADRAO['esquerda'])
        ->toContain('/'.LogoDaFolha::PADRAO['direita']);
});

it('entrega o arquivo da logo padrão para o PDF, e não a URL', function () {
    $modelo = ModeloProva::factory()->create(['eixo_id' => $this->eixo->id]);

    $html = app(RenderizarProvaAction::class)->paraPdf(($this->montar)($modelo));

    expect($html)->toContain(public_path(LogoDaFolha::PADRAO['esquerda']))
        ->toContain(public_path(LogoDaFolha::PADRAO['direita']));
});

it('os dois arquivos padrão existem no projeto', function () {
    foreach (LogoDaFolha::PADRAO as $lado => $relativo) {
        expect(is_file(public_path($relativo)))->toBeTrue("falta a logo padrão da {$lado}");

        // O mPDF não desenha WEBP.
        expect(image_type_to_mime_type(getimagesize(public_path($relativo))[2]))
            ->toBeIn(['image/png', 'image/jpeg', 'image/gif']);
    }
});

it('mantém as duas células do cabeçalho quando os dois lados dispensam a logo', function () {
    // Sem as células vazias o texto do centro escorrega para a esquerda.
    $this->modelo->update([
        'origem_logo_esquerda' => OrigemDaLogo::Nenhuma,
        'origem_logo_direita' => OrigemDaLogo::Nenhuma,
    ]);

    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain('class="logo esquerda"')
        ->toContain('class="logo direita"')
        ->not->toContain('<img src="/storage/modelos-prova/')
        ->not->toContain('/'.LogoDaFolha::PADRAO['esquerda']);
});

it('cai para a logo padrão quando o arquivo enviado sumiu do disco', function () {
    // Um arquivo apagado à mão não pode deixar a folha sem moldura.
    $this->modelo->update(['logo_esquerda_path' => 'modelos-prova/que-nao-existe.png']);

    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain('/'.LogoDaFolha::PADRAO['esquerda']);
});

it('lê as logos do disco quando o destino é o PDF', function () {
    $prova = ($this->montar)();

    // O mPDF não busca por HTTP: ele abre o arquivo.
    $html = app(RenderizarProvaAction::class)->paraPdf($prova);

    expect($html)->toContain(Storage::disk('public')->path($this->logoEsquerda))
        ->toContain(Storage::disk('public')->path($this->logoDireita));
});

it('leva as duas logos para o Word', function () {
    $docx = app(GerarDocxDaProvaAction::class)->conteudo(($this->montar)(), $this->paeet);

    $arquivo = tempnam(sys_get_temp_dir(), 'prova').'.docx';
    file_put_contents($arquivo, $docx);

    $zip = new ZipArchive;
    $zip->open($arquivo);

    $imagens = collect(range(0, $zip->numFiles - 1))
        ->map(fn (int $i) => $zip->getNameIndex($i))
        ->filter(fn (string $nome) => str_starts_with($nome, 'word/media/'));

    $zip->close();
    @unlink($arquivo);

    expect($imagens)->toHaveCount(2);
});

/*
|--------------------------------------------------------------------------
| Normas da ABNT
|--------------------------------------------------------------------------
*/

it('nasce com a formatação da ABNT', function () {
    $layout = LayoutDaFolha::de([]);

    expect($layout->norma)->toBe(NormaDaFolha::Abnt)
        ->and($layout->tamanho)->toBe(12)
        ->and($layout->espacamento)->toBe(1.5)
        ->and($layout->margens)->toBe([
            'superior' => 30.0, 'inferior' => 20.0, 'esquerda' => 30.0, 'direita' => 20.0,
        ]);
});

it('impõe os valores da norma mesmo quando o modelo guarda outros', function () {
    // Um modelo antigo, gravado antes da norma existir, não pode
    // continuar imprimindo 9 pt só porque o JSON diz isso.
    $layout = LayoutDaFolha::de([
        'norma' => 'abnt', 'tamanho' => 9, 'espacamento' => 1, 'margens' => ['superior' => 5],
    ]);

    expect($layout->tamanho)->toBe(12)
        ->and($layout->espacamento)->toBe(1.5)
        ->and($layout->margens['superior'])->toBe(30.0);
});

it('imprime A4 com margens de 3, 2, 2 e 3 centímetros', function () {
    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain('size: A4 portrait')
        ->toContain('margin: 30mm 20mm 20mm 30mm');
});

it('usa corpo 12, entrelinhas 1,5 e texto justificado', function () {
    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain('font-size: 12pt')
        ->toContain('line-height: 1.5')
        ->toContain('text-align: justify');
});

it('oferece as duas fontes que a norma admite', function () {
    expect(LayoutDaFolha::FONTES)->toBe(['sans' => 'Arial', 'serif' => 'Times New Roman']);

    $this->modelo->update(['layout' => ['norma' => 'abnt', 'fonte' => 'serif']]);

    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain("'Times New Roman'");
});

it('trata o trecho de código como citação longa: 10 pt e espaçamento simples', function () {
    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    $estiloDoCodigo = substr($html, strpos($html, '.bloco-codigo {'), 400);

    expect($estiloDoCodigo)->toContain('font-size: 10pt')
        ->toContain('line-height: 1');
});

it('numera as páginas no alto à direita', function () {
    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain('class="paginacao"')
        ->toContain('content: counter(page)');
});

it('deixa a formatação livre quando a norma é dispensada', function () {
    $this->modelo->update(['layout' => [
        'norma' => 'livre',
        'fonte' => 'sans',
        'tamanho' => 10,
        'espacamento' => 1.2,
        'margens' => ['superior' => 15, 'inferior' => 15, 'esquerda' => 12, 'direita' => 12],
    ]]);

    $html = app(RenderizarProvaAction::class)->paraTela(($this->montar)());

    expect($html)->toContain('font-size: 10pt')
        ->toContain('line-height: 1.2')
        ->toContain('margin: 15mm 12mm 15mm 12mm');
});

/*
|--------------------------------------------------------------------------
| Duas colunas de verdade
|--------------------------------------------------------------------------
*/

it('abre o fluxo em colunas que o motor do PDF entende', function () {
    // `column-count` resolve no navegador. O mPDF quer a tag `<columns>`
    // — e o dompdf, que gerava este PDF antes, ignorava as duas: a folha
    // saía inteira em coluna única sem reclamar de nada.
    $prova = ($this->montar)();
    $renderizar = app(RenderizarProvaAction::class);

    expect($renderizar->paraTela($prova))
        ->toContain('column-count: 2')
        ->not->toContain('<columns');

    expect($renderizar->paraPdf($prova))
        ->toContain('<columns column-count="2"')
        ->toContain('<columns column-count="1"');
});

it('não emite a tag de colunas quando a folha é de coluna única', function () {
    $prova = ($this->montar)();
    $prova->update(['configuracao' => ['colunas' => 1]]);

    expect(app(RenderizarProvaAction::class)->paraPdf($prova->refresh()))
        ->not->toContain('<columns');
});

it('gera um PDF de poucas páginas', function () {
    // Uma prova de 4 questões cabe em duas páginas. A regra `@page`
    // enviada ao mPDF fazia o cálculo de altura entrar em laço e o
    // mesmo documento sair com milhares de páginas — sem erro nenhum,
    // só um arquivo gigante.
    $pdf = app(GerarPdfDaProvaAction::class)
        ->conteudo(($this->montar)(), $this->paeet, comGabarito: true);

    preg_match('#/Count\s+(\d+)#', $pdf, $paginas);

    expect((int) ($paginas[1] ?? 0))->toBeGreaterThan(0)->toBeLessThanOrEqual(4);
});

it('mantém a indentação do código, que o mPDF perderia', function () {
    // `white-space: pre-wrap` só existe no navegador: sem quebrar em
    // linhas, o trecho sairia todo grudado no PDF.
    $html = app(RenderizarProvaAction::class)->paraPdf(($this->montar)());

    expect($html)->toContain('<div class="linha">for i in range(3):</div>')
        ->toContain('&nbsp;&nbsp;&nbsp;&nbsp;print(i)');
});

it('gera PDF e Word com a norma aplicada', function () {
    $prova = ($this->montar)();

    $pdf = app(GerarPdfDaProvaAction::class)->conteudo($prova, $this->paeet);
    $docx = app(GerarDocxDaProvaAction::class)->conteudo($prova, $this->paeet);

    expect(substr($pdf, 0, 4))->toBe('%PDF')
        ->and(substr($docx, 0, 2))->toBe('PK');

    // 3 cm = 1701 twips; é assim que o .docx guarda a margem.
    $arquivo = tempnam(sys_get_temp_dir(), 'prova').'.docx';
    file_put_contents($arquivo, $docx);

    $zip = new ZipArchive;
    $zip->open($arquivo);
    $xml = (string) $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($arquivo);

    expect($xml)->toContain('w:top="1701"')
        ->toContain('w:left="1701"')
        ->toContain('w:right="1134"');
});
