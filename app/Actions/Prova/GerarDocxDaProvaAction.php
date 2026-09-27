<?php

namespace App\Actions\Prova;

use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\User;
use App\Support\LayoutDaFolha;
use App\Support\LogoDaFolha;
use App\Support\TextoDoEnunciado;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

/**
 * Gera a prova em Word (.docx), com o mesmo layout de duas colunas.
 *
 * O Word não aceita o HTML da folha, então o documento é montado bloco a
 * bloco. O cabeçalho e a identificação ficam numa seção de coluna única,
 * e as questões numa seção contínua com o número de colunas escolhido —
 * é assim que o Word faz texto em colunas.
 *
 * A formatação (papel, margens, fonte, espaçamento) sai do mesmo
 * `LayoutDaFolha` que o HTML usa, para o .docx não divergir do PDF.
 */
class GerarDocxDaProvaAction
{
    public function conteudo(Prova $prova, User $autor, bool $comGabarito = false): string
    {
        Gate::forUser($autor)->authorize('view', $prova);

        $prova->loadMissing(['turma.curso', 'modelo']);

        $documento = $this->montar($prova, $comGabarito);

        $temporario = tempnam(sys_get_temp_dir(), 'prova').'.docx';

        $this->semRuidoDoPhpWord(
            fn () => IOFactory::createWriter($documento, 'Word2007')->save($temporario)
        );

        $conteudo = (string) file_get_contents($temporario);

        @unlink($temporario);

        return $conteudo;
    }

    /**
     * Cala uma depreciação do próprio PhpWord no PHP 8.5.
     *
     * Ao escrever cada parágrafo, o PhpWord 1.4 chama
     * `Style::getStyle(null)` quando não há estilo de numeração — e
     * `$styles[null]` virou "Using null as an array offset is
     * deprecated". O .docx sai correto; o que vaza é ruído, algumas
     * linhas de log a cada download.
     *
     * O silêncio é estreito de propósito: só `E_DEPRECATED`, e só se vier
     * de dentro do PhpWord. Qualquer outro aviso, inclusive uma
     * depreciação nossa, continua passando. A 1.4.0 é a última publicada
     * e ainda não traz a correção; quando trouxer, este método sai e o
     * teste de regressão cobra a saída.
     *
     * @template T
     *
     * @param  callable(): T  $escrever
     * @return T
     */
    protected function semRuidoDoPhpWord(callable $escrever): mixed
    {
        $anterior = set_error_handler(
            function (int $tipo, string $mensagem, string $arquivo = '', int $linha = 0) use (&$anterior) {
                $doPhpWord = str_contains(
                    str_replace('\\', '/', $arquivo),
                    'vendor/phpoffice/phpword/'
                );

                if ($tipo === E_DEPRECATED && $doPhpWord) {
                    return true;
                }

                return $anterior === null
                    ? false
                    : ($anterior)($tipo, $mensagem, $arquivo, $linha);
            }
        );

        try {
            return $escrever();
        } finally {
            restore_error_handler();
        }
    }

    public function guardar(Prova $prova, User $autor, bool $comGabarito = false): string
    {
        $conteudo = $this->conteudo($prova, $autor, $comGabarito);

        $caminho = "provas/{$prova->getKey()}/"
            .app(GerarPdfDaProvaAction::class)->nomeDoArquivo($prova, $comGabarito, 'docx');

        Storage::disk('public')->put($caminho, $conteudo);

        if (! $comGabarito) {
            $prova->update(['docx_path' => $caminho]);
        }

        activity('prova')
            ->performedOn($prova)
            ->causedBy($autor)
            ->withProperties(['arquivo' => $caminho, 'gabarito' => $comGabarito])
            ->log('Word da prova gerado');

        return $caminho;
    }

    protected function montar(Prova $prova, bool $comGabarito): PhpWord
    {
        $modelo = $prova->modelo;
        $layout = $modelo->layoutDaFolha();
        $tamanho = $layout->tamanho;
        $secundario = $layout->tamanhoSecundario();

        $documento = new PhpWord;
        $documento->setDefaultFontName($layout->nomeDaFonte());
        $documento->setDefaultFontSize($tamanho);
        $documento->getSettings()->setThemeFontLang(new Language(Language::PT_BR));

        // `lineHeight` do PhpWord é o multiplicador: 1,5 é o da norma.
        $documento->setDefaultParagraphStyle([
            'alignment' => Jc::BOTH,
            'lineHeight' => $layout->espacamento,
        ]);

        $documento->addTitleStyle(1, ['bold' => true, 'size' => $tamanho + 2]);
        $documento->addParagraphStyle('justificado', [
            'alignment' => Jc::BOTH,
            'lineHeight' => $layout->espacamento,
            'spaceAfter' => 60,
        ]);
        $documento->addParagraphStyle('compacto', [
            'alignment' => Jc::START,
            'lineHeight' => $layout->espacamentoSecundario(),
            'spaceAfter' => 0,
            'spaceBefore' => 0,
        ]);
        $documento->addFontStyle('codigo', ['name' => 'Courier New', 'size' => $secundario]);
        $documento->addFontStyle('disciplina', ['bold' => true, 'size' => $tamanho]);
        $documento->addFontStyle('discreto', ['size' => $secundario, 'color' => '555555']);

        $this->cabecalho($documento, $prova, $modelo, $layout);
        $this->corpoEmColunas($documento, $prova, $layout, $comGabarito);

        if ($comGabarito) {
            $this->gabarito($documento, $prova, $layout);
        }

        return $documento;
    }

    /**
     * Papel e margens da seção. A4 e 3/2/2/3 cm quando a norma manda.
     *
     * @return array<string, mixed>
     */
    protected function pagina(LayoutDaFolha $layout): array
    {
        return [
            'pageSizeW' => $this->twips(21),
            'pageSizeH' => $this->twips(29.7),
            'marginTop' => $this->twips($layout->margens['superior'] / 10),
            'marginBottom' => $this->twips($layout->margens['inferior'] / 10),
            'marginLeft' => $this->twips($layout->margens['esquerda'] / 10),
            'marginRight' => $this->twips($layout->margens['direita'] / 10),
        ];
    }

    /**
     * Centímetros em twips inteiros.
     *
     * `Converter::cmToTwip()` devolve float, e o OOXML espera inteiro —
     * uma margem gravada como `w:top="1700.787"` é medida inválida, que o
     * Word arredonda por conta própria ou simplesmente ignora.
     */
    protected function twips(float $centimetros): int
    {
        return (int) round(Converter::cmToTwip($centimetros));
    }

    protected function cabecalho(PhpWord $documento, Prova $prova, ModeloProva $modelo, LayoutDaFolha $layout): void
    {
        $secao = $documento->addSection($this->pagina($layout));

        // Numeração no alto à direita, como pede a NBR 14724.
        $secao->addHeader()->addPreserveText(
            '{PAGE}',
            ['size' => $layout->tamanhoSecundario()],
            ['alignment' => Jc::END],
        );

        $this->marca($secao, $prova, $modelo, $layout);

        $turma = $prova->turma;

        $centrado = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

        $secao->addText(
            "{$turma->curso->nome} · Turma {$turma->nome} · {$turma->periodo}º período"
            .' · '.$prova->bimestre->rotulo()
            .($prova->data_aplicacao ? ' · '.$prova->data_aplicacao->format('d/m/Y') : ''),
            'discreto',
            $centrado,
        );

        if ($modelo->cabecalho) {
            $secao->addText($modelo->cabecalho, 'discreto', $centrado);
        }

        $secao->addTextBreak(1);

        $this->identificacao($secao, $prova, $modelo, $layout);

        if ($prova->instrucoes) {
            $secao->addTextBreak(1);
            $secao->addText($prova->instrucoes, 'discreto', 'justificado');
        }
    }

    /**
     * Quadro de identificação: rótulo numa célula, linha para preencher
     * na de baixo bordada ao lado — os mesmos campos e a mesma ordem da
     * folha em HTML.
     *
     * A linha não é feita de sublinhados: uma fileira de `_` quebra no
     * fim da célula e desce para a linha seguinte.
     */
    protected function identificacao(Section $secao, Prova $prova, ModeloProva $modelo, LayoutDaFolha $layout): void
    {
        $campos = $modelo->campos_identificacao ?: ['aluno', 'matricula', 'turma', 'data'];
        $turma = $prova->turma;

        $util = 21 - ($layout->margens['esquerda'] + $layout->margens['direita']) / 10;
        $rotulo = 2.6;

        $linhas = array_values(array_filter([
            in_array('aluno', $campos, true) ? [['Aluno(a):', null]] : null,
            array_values(array_filter([
                in_array('matricula', $campos, true) ? ['Matrícula:', null] : null,
                in_array('turma', $campos, true) ? ['Turma:', $turma->nome] : null,
            ])) ?: null,
            array_values(array_filter([
                in_array('curso', $campos, true) ? ['Curso:', $turma->curso->nome] : null,
                in_array('data', $campos, true) ? ['Data:', '___/___/______'] : null,
            ])) ?: null,
            array_values(array_filter([
                in_array('nota', $campos, true) ? ['Nota:', null] : null,
                in_array('assinatura', $campos, true) ? ['Assinatura:', null] : null,
            ])) ?: null,
        ]));

        if ($linhas === []) {
            return;
        }

        // Só a moldura de fora: `borderSize` desenharia também o
        // gradeado interno, e as únicas linhas internas que este quadro
        // tem são as de preencher, que são borda de célula.
        $tabela = $secao->addTable([
            'borderTopSize' => 6, 'borderBottomSize' => 6,
            'borderLeftSize' => 6, 'borderRightSize' => 6,
            'borderColor' => '111111',
            'cellMargin' => 60,
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        foreach ($linhas as $linha) {
            $tabela->addRow();

            // O espaço que sobra é dividido entre os campos da linha.
            $preenchimento = ($util - count($linha) * $rotulo) / count($linha);

            foreach ($linha as [$texto, $valor]) {
                $tabela->addCell($this->twips($rotulo))
                    ->addText($texto, ['bold' => true], 'compacto');

                $celula = $tabela->addCell(
                    $this->twips($preenchimento),
                    $valor === null ? ['borderBottomSize' => 6, 'borderBottomColor' => '555555'] : [],
                );

                $celula->addText($valor ?? ' ', null, 'compacto');
            }
        }
    }

    /**
     * Logo à esquerda, identificação ao centro, logo à direita — numa
     * tabela sem bordas, que é como o Word põe três blocos lado a lado.
     */
    protected function marca(Section $secao, Prova $prova, ModeloProva $modelo, LayoutDaFolha $layout): void
    {
        $util = 21 - ($layout->margens['esquerda'] + $layout->margens['direita']) / 10;
        $ladoDaLogo = 2.6;

        // Sem `borderSize`: declarar `0` emite `<w:tblBorders>` com
        // espessura zero, que alguns leitores desenham como fio de cabelo.
        $tabela = $secao->addTable(['cellMargin' => 0]);
        $tabela->addRow();

        $this->logo($tabela->addCell($this->twips($ladoDaLogo)), $modelo->logo('esquerda'), Jc::START);

        $centro = $tabela->addCell($this->twips($util - 2 * $ladoDaLogo));

        $centro->addText(
            $prova->instituicaoDaFolha(),
            ['bold' => true, 'size' => $prova->tamanhoDaInstituicao(), 'allCaps' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
        );

        $centro->addText(
            $prova->nomeDaAvaliacao().' — '.$prova->titulo,
            ['size' => $layout->tamanho],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
        );

        $this->logo($tabela->addCell($this->twips($ladoDaLogo)), $modelo->logo('direita'), Jc::END);
    }

    /**
     * A célula existe mesmo sem imagem: é ela que mantém o centro no
     * lugar.
     *
     * Quem decide de onde sai o arquivo é a `LogoDaFolha`, a mesma que o
     * HTML consulta — ler `logo_esquerda_path` direto daqui deixava o
     * .docx sem cabeçalho para quem usa a logo padrão, que não mora no
     * disco público, e fazia o "sem logo deste lado" ser ignorado.
     */
    protected function logo(Cell $celula, LogoDaFolha $logo, string $alinhamento): void
    {
        $arquivo = $logo->arquivo();

        if ($arquivo === null || ! is_file($arquivo)) {
            $celula->addTextBreak(1);

            return;
        }

        $celula->addImage($arquivo, ['height' => 62, 'alignment' => $alinhamento]);
    }

    /** As questões entram numa seção contínua com o número de colunas. */
    protected function corpoEmColunas(PhpWord $documento, Prova $prova, LayoutDaFolha $layout, bool $comGabarito): void
    {
        $secao = $documento->addSection([
            ...$this->pagina($layout),
            'breakType' => 'continuous',
            'colsNum' => $prova->colunas(),
            'colsSpace' => $this->twips(0.8),
        ]);

        $largura = $this->twips(
            (21 - ($layout->margens['esquerda'] + $layout->margens['direita']) / 10
                - 0.8 * ($prova->colunas() - 1)) / $prova->colunas()
        );

        foreach ($prova->questoesPorDisciplina() as $disciplina => $questoes) {
            $this->faixaDaDisciplina(
                $secao,
                "{$disciplina} — questões {$questoes->min('numero')} a {$questoes->max('numero')}",
                $largura,
            );

            foreach ($questoes as $questao) {
                $this->questao($secao, $questao, $prova, $comGabarito);
            }
        }
    }

    /**
     * A faixa que abre cada disciplina: fundo cinza e barra preta nos
     * dois lados, como na folha em HTML. É uma tabela de uma célula
     * porque o parágrafo do Word aceita sombreado, mas não borda.
     */
    protected function faixaDaDisciplina(Section $secao, string $texto, int $largura): void
    {
        $tabela = $secao->addTable([
            'borderLeftSize' => 18, 'borderLeftColor' => '111111',
            'borderRightSize' => 18, 'borderRightColor' => '111111',
            'cellMargin' => 60,
        ]);

        $tabela->addRow();
        // À esquerda, e não no justificado herdado do corpo: uma faixa
        // de uma linha justificada estica as palavras de ponta a ponta.
        $tabela->addCell($largura, ['bgColor' => 'EEEEEE'])
            ->addText($texto, 'disciplina', ['spaceAfter' => 0, 'alignment' => Jc::START]);

        $secao->addTextBreak(1);
    }

    protected function questao(Section $secao, ProvaQuestao $questao, Prova $prova, bool $comGabarito): void
    {
        $peso = $prova->mostrarPesos()
            ? ' (peso '.number_format((float) $questao->peso, 2, ',', '.').')'
            : '';

        $paragrafo = $secao->addTextRun('justificado');
        $paragrafo->addText("{$questao->numero}.{$peso} ", ['bold' => true]);
        $this->escreverFormatado($paragrafo, (string) $questao->enunciado_snapshot);

        foreach ($questao->blocos() as $bloco) {
            if ($bloco['tipo'] === 'codigo') {
                $secao->addText(
                    strtoupper((string) ($bloco['linguagem'] ?? 'código')),
                    'discreto',
                    'compacto',
                );

                foreach (preg_split('/\R/', trim((string) $bloco['conteudo'])) as $linha) {
                    // Uma linha por parágrafo: o Word não preserva quebras
                    // dentro de um mesmo bloco de texto.
                    $secao->addText(
                        htmlspecialchars($linha === '' ? ' ' : $linha, ENT_QUOTES),
                        'codigo',
                        'compacto',
                    );
                }
            } elseif ($bloco['tipo'] === 'imagem' && ! empty($bloco['caminho'])) {
                if (Storage::disk('public')->exists($bloco['caminho'])) {
                    $secao->addImage(
                        Storage::disk('public')->path($bloco['caminho']),
                        ['width' => 200, 'alignment' => Jc::CENTER],
                    );
                }

                if (! empty($bloco['legenda'])) {
                    $secao->addText($bloco['legenda'], 'discreto');
                }
            } else {
                $this->escreverFormatado(
                    $secao->addTextRun('justificado'),
                    (string) $bloco['conteudo'],
                );
            }
        }

        foreach ($questao->alternativas_snapshot as $alternativa) {
            $destaque = $comGabarito && $alternativa['correta'];

            $linha = $secao->addTextRun(['alignment' => Jc::BOTH, 'spaceAfter' => 20]);
            $linha->addText("{$alternativa['letra']}) ", ['bold' => true]);
            $linha->addText((string) $alternativa['texto'], $destaque ? ['bold' => true, 'bgColor' => 'D8F3DC'] : null);
        }

        $secao->addTextBreak(1);
    }

    /**
     * Escreve o texto respeitando o negrito e o itálico das marcas.
     *
     * O Word não lê `**assim**`: cada trecho vira um `run` com o seu
     * próprio estilo, que é por isso que a formatação mora em
     * `TextoDoEnunciado::segmentos()` e não em HTML.
     *
     * @param  array<string, mixed>  $estiloBase
     */
    protected function escreverFormatado(TextRun $paragrafo, string $texto, array $estiloBase = []): void
    {
        foreach (TextoDoEnunciado::segmentos($texto) as $trecho) {
            $estilo = $estiloBase;

            if ($trecho['negrito']) {
                $estilo['bold'] = true;
            }

            if ($trecho['italico']) {
                $estilo['italic'] = true;
            }

            $paragrafo->addText($trecho['texto'], $estilo ?: null);
        }
    }

    protected function gabarito(PhpWord $documento, Prova $prova, LayoutDaFolha $layout): void
    {
        $secao = $documento->addSection([
            ...$this->pagina($layout),
            'breakType' => 'continuous',
            'colsNum' => 1,
        ]);

        $secao->addText('Gabarito', ['bold' => true, 'size' => 12], ['spaceBefore' => 200]);

        $tabela = $secao->addTable(['borderSize' => 6, 'borderColor' => '333333', 'cellMargin' => 40]);
        $gabarito = $prova->gabarito();

        $tabela->addRow();
        foreach ($gabarito as $numero => $letra) {
            $tabela->addCell($this->twips(1))->addText((string) $numero, null, ['alignment' => Jc::CENTER]);
        }

        $tabela->addRow();
        foreach ($gabarito as $letra) {
            $tabela->addCell($this->twips(1))->addText($letra, ['bold' => true], ['alignment' => Jc::CENTER]);
        }
    }
}
