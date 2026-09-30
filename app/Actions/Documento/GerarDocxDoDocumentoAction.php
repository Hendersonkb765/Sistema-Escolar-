<?php

namespace App\Actions\Documento;

use App\Actions\Prova\GerarDocxDaProvaAction;
use App\Models\Aluno;
use App\Models\ModeloDocumento;
use App\Models\Turma;
use App\Models\User;
use App\Support\CamposDoDocumento;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Language;

/**
 * O mesmo documento do aluno, em Word.
 *
 * Existe porque a secretaria quase sempre precisa mexer no papel depois:
 * trocar uma data, acrescentar um parágrafo, corrigir um nome. O PDF
 * serve para imprimir; o .docx, para editar antes.
 *
 * O Word não aceita o HTML da folha, então o documento é montado bloco a
 * bloco a partir de {@see CamposDoDocumento::trechos()} — a mesma
 * conversão que o HTML usa, para os dois formatos não divergirem no que
 * dizem.
 */
class GerarDocxDoDocumentoAction
{
    /** Herda de GerarDocumentosAction a autorização e o recorte de alunos. */
    public function __construct(protected GerarDocumentosAction $base) {}

    public function conteudo(ModeloDocumento $modelo, Turma $turma, array $alunoIds, User $autor): string
    {
        $alunos = $this->base->prepararGeracao($modelo, $turma, $alunoIds, $autor);

        $documento = $this->montar($modelo, $turma, $alunos);

        $temporario = tempnam(sys_get_temp_dir(), 'documento').'.docx';

        $this->semRuidoDoPhpWord(
            fn () => IOFactory::createWriter($documento, 'Word2007')->save($temporario)
        );

        $conteudo = (string) file_get_contents($temporario);

        @unlink($temporario);

        return $conteudo;
    }

    public function nomeDoArquivo(ModeloDocumento $modelo, Turma $turma): string
    {
        return Str::slug($modelo->nome.'-'.$turma->nome).'.docx';
    }

    /** @param  Collection<int, Aluno>  $alunos */
    protected function montar(ModeloDocumento $modelo, Turma $turma, Collection $alunos): PhpWord
    {
        $empilha = $modelo->empilhaNaFolha();
        $compacto = $empilha;
        $tamanho = $compacto ? 9 : 11;

        $documento = new PhpWord;
        $documento->setDefaultFontName('Arial');
        $documento->setDefaultFontSize($tamanho);
        $documento->getSettings()->setThemeFontLang(new Language(Language::PT_BR));
        $documento->setDefaultParagraphStyle([
            'alignment' => Jc::BOTH,
            'lineHeight' => $compacto ? 1.15 : 1.3,
        ]);

        /*
         * `keepNext` e `keepLines` são o que o Word tem no lugar do
         * `page-break-inside: avoid`: cada parágrafo pede para não ser
         * partido e para ficar colado no seguinte, e o Word empurra a via
         * inteira para a folha nova quando ela não cabe. O último
         * parágrafo de cada via solta o `keepNext` — senão o documento
         * inteiro vira um bloco só e nada quebra em lugar nenhum.
         */
        $juntos = $empilha ? ['keepNext' => true, 'keepLines' => true] : [];

        $documento->addParagraphStyle('corpo', [
            'alignment' => Jc::BOTH,
            // Empilhando, o respiro entre parágrafos sai do orçamento da
            // folha: quem o dá são as linhas em branco do próprio texto.
            'spaceAfter' => $compacto ? 0 : 90,
            ...$juntos,
        ]);
        $documento->addParagraphStyle('centrado', [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 0,
            ...$juntos,
        ]);
        // Fecha a via: daqui o Word pode quebrar.
        $documento->addParagraphStyle('fim-da-via', [
            'alignment' => Jc::CENTER,
            'spaceBefore' => 120,
            'spaceAfter' => 120,
            'keepLines' => true,
        ]);
        $documento->addFontStyle('discreto', ['size' => $tamanho - 2, 'color' => '555555']);

        $secao = $documento->addSection($this->pagina());

        $instituicao = $this->base->instituicaoDaTurma($turma);
        $logos = $this->base->logosDaTurma($turma);

        $vias = $this->vias($modelo, $turma, $alunos);
        $ultima = count($vias) - 1;

        foreach ($vias as $indice => $trechos) {
            if (! $empilha && $indice > 0) {
                $secao->addPageBreak();
            }

            $onde = $empilha ? $this->envelope($secao) : $secao;

            $this->cabecalho($onde, $modelo, $instituicao, $logos, $compacto, $empilha);
            $this->corpo($onde, $trechos, $alunos, $compacto);

            if ($empilha && $indice < $ultima) {
                $this->corte($secao);
            }
        }

        return $documento;
    }

    /**
     * Cada via já em trechos.
     *
     * @param  Collection<int, Aluno>  $alunos
     * @return array<int, array<int, array<string, mixed>>>
     */
    protected function vias(ModeloDocumento $modelo, Turma $turma, Collection $alunos): array
    {
        $comum = $this->base->dadosDaTurma($turma);

        if (! $modelo->ehIndividual()) {
            return [CamposDoDocumento::trechos(
                $modelo->corpo,
                CamposDoDocumento::contexto(null, null, ...$comum),
            )];
        }

        return $alunos
            ->values()
            ->map(fn (Aluno $aluno, int $posicao) => CamposDoDocumento::trechos(
                $modelo->corpo,
                CamposDoDocumento::contexto($aluno, $posicao + 1, ...$comum),
            ))
            ->all();
    }

    /**
     * Escreve os trechos, abrindo um parágrafo novo a cada quebra.
     *
     * @param  array<int, array<string, mixed>>  $trechos
     * @param  Collection<int, Aluno>  $alunos
     */
    protected function corpo(AbstractContainer $secao, array $trechos, Collection $alunos, bool $compacto): void
    {
        $paragrafo = $secao->addTextRun('corpo');

        foreach ($trechos as $trecho) {
            switch ($trecho['tipo']) {
                case 'texto':
                    $paragrafo->addText($trecho['texto'], [
                        'bold' => $trecho['negrito'] ?? false,
                        'italic' => $trecho['italico'] ?? false,
                    ]);
                    break;

                case 'quebra':
                    $paragrafo = $secao->addTextRun('corpo');
                    break;

                case 'linha':
                    $this->linha($paragrafo, $trecho['rotulo'] ?? '');
                    break;

                case 'caixa':
                    $this->caixa($paragrafo, $trecho['rotulo'] ?? '');
                    break;

                case 'assinatura':
                    $this->assinatura($secao, $trecho['rotulo'] ?? '');
                    $paragrafo = $secao->addTextRun('corpo');
                    break;

                case 'espaco':
                    $this->espaco($secao, $compacto);
                    $paragrafo = $secao->addTextRun('corpo');
                    break;

                case 'lista_de_alunos':
                    $this->listaDeAlunos($secao, $alunos, $compacto);
                    $paragrafo = $secao->addTextRun('corpo');
                    break;
            }
        }
    }

    /**
     * A linha de preencher é um traço de sublinhados.
     *
     * Sublinhar espaços seria mais elegante, mas o Word come espaço no
     * fim de um trecho, e a linha encurta ou some conforme o que vem
     * depois. Sublinhado é o que se usa em formulário de papel desde
     * antes do Word existir, e não depende de nada.
     */
    protected function linha(TextRun $paragrafo, string $rotulo): void
    {
        $paragrafo->addText(str_repeat('_', 28));

        if ($rotulo !== '') {
            $paragrafo->addText(' ('.$rotulo.')', 'discreto');
        }
    }

    /** O quadradinho é o caractere ☐: desenhar um quadrado em linha o Word não faz. */
    protected function caixa(TextRun $paragrafo, string $rotulo): void
    {
        $paragrafo->addText('☐');

        if ($rotulo !== '') {
            $paragrafo->addText(' '.$rotulo);
        }
    }

    /** Linha centrada com o rótulo embaixo, como num formulário de papel. */
    protected function assinatura(AbstractContainer $secao, string $rotulo): void
    {
        $secao->addText(str_repeat('_', 40), null, 'centrado');
        $secao->addText($rotulo === '' ? ' ' : $rotulo, 'discreto', 'centrado');
    }

    /** Quadro em branco para escrever à mão. */
    protected function espaco(AbstractContainer $secao, bool $compacto): void
    {
        $tabela = $secao->addTable([
            'borderSize' => 6,
            'borderColor' => 'BBBBBB',
            'width' => 100 * 50,
            'unit' => 'pct',
        ]);

        $tabela->addRow($this->twips($compacto ? 1.2 : 2.2));
        $tabela->addCell()->addText(' ');
    }

    /** @param  Collection<int, Aluno>  $alunos */
    protected function listaDeAlunos(AbstractContainer $secao, Collection $alunos, bool $compacto): void
    {
        $tabela = $secao->addTable([
            'borderSize' => 6,
            'borderColor' => '999999',
            'width' => 100 * 50,
            'unit' => 'pct',
            'cellMargin' => 60,
        ]);

        $cabecalho = ['bold' => true, 'size' => ($compacto ? 9 : 11) - 2];
        $semJustificar = ['alignment' => Jc::START, 'spaceAfter' => 0];

        /*
         * As larguras vão na PRIMEIRA linha: é ela que define a grade da
         * tabela no OOXML. Declaradas só nas linhas de baixo, elas são
         * ignoradas, e o Word reparte as colunas por conta própria — o
         * nome do aluno ficava espremido em duas linhas.
         */
        $colunas = ['Nº' => 1.2, 'Aluno' => 8.1, 'RA' => 2.7, 'Assinatura' => 5.8];

        $tabela->addRow();
        foreach ($colunas as $titulo => $largura) {
            $tabela->addCell($this->twips($largura))->addText($titulo, $cabecalho, $semJustificar);
        }

        foreach ($alunos->values() as $posicao => $aluno) {
            $tabela->addRow();
            $tabela->addCell($this->twips($colunas['Nº']))->addText((string) ($posicao + 1), null, $semJustificar);
            $tabela->addCell($this->twips($colunas['Aluno']))->addText($aluno->nome, null, $semJustificar);
            $tabela->addCell($this->twips($colunas['RA']))->addText((string) $aluno->ra, null, $semJustificar);
            $tabela->addCell($this->twips($colunas['Assinatura']))->addText(' ', null, $semJustificar);
        }
    }

    /**
     * A via inteira dentro de uma linha de tabela que não se parte.
     *
     * `cantSplit` é o que o Word tem de mais próximo do
     * `page-break-inside: avoid`: uma linha que não cabe no que resta da
     * folha desce inteira para a próxima. Só o `keepNext` dos parágrafos
     * não bastava — a tabela do cabeçalho ficava no pé de uma folha e o
     * corpo da via começava na outra, e o pedaço recortado saía sem o
     * nome da escola.
     *
     * A tabela não tem borda nem margem: ela existe só para segurar.
     */
    protected function envelope(Section $secao): Cell
    {
        $tabela = $secao->addTable(['cellMargin' => 0, 'width' => 100 * 50, 'unit' => 'pct']);

        $tabela->addRow(null, ['cantSplit' => true]);

        return $tabela->addCell($this->twips(17.8));
    }

    /** A linha de corte fecha a via: é dela que o Word pode quebrar. */
    protected function corte(Section $secao): void
    {
        $secao->addText(str_repeat('- ', 45), ['size' => 8, 'color' => '999999'], 'fim-da-via');
    }

    /**
     * @param  array{esquerda: ?string, direita: ?string}  $logos
     */
    protected function cabecalho(
        AbstractContainer $secao,
        ModeloDocumento $modelo,
        string $instituicao,
        array $logos,
        bool $compacto,
        bool $empilha = false,
    ): void {
        /*
         * O cabeçalho também pede para não se separar do que vem
         * depois. Sem isso ele pode ficar sozinho no pé de uma folha,
         * com o corpo da via na seguinte — o pedaço recortado sai sem
         * texto e o outro sem o nome da escola.
         */
        $colado = $empilha
            ? ['alignment' => Jc::CENTER, 'spaceAfter' => 0, 'keepNext' => true, 'keepLines' => true]
            : ['alignment' => Jc::CENTER, 'spaceAfter' => 0];

        $util = 21 - 3.2;
        $ladoDaLogo = $compacto ? 1.6 : 2.4;

        $tabela = $secao->addTable(['cellMargin' => 0]);
        $tabela->addRow();

        $this->logo($tabela->addCell($this->twips($ladoDaLogo)), $logos['esquerda'], Jc::START, $compacto);

        $centro = $tabela->addCell($this->twips($util - 2 * $ladoDaLogo));

        $centro->addText($instituicao, ['bold' => true, 'size' => $compacto ? 9 : 12], $colado);
        $centro->addText($modelo->nome, ['size' => $compacto ? 8 : 10], $colado);

        $this->logo($tabela->addCell($this->twips($ladoDaLogo)), $logos['direita'], Jc::END, $compacto);
    }

    /** A célula existe mesmo sem imagem: é ela que segura o centro no lugar. */
    protected function logo(Cell $celula, ?string $arquivo, string $alinhamento, bool $compacto): void
    {
        if ($arquivo === null || ! is_file($arquivo)) {
            $celula->addTextBreak(1);

            return;
        }

        $celula->addImage($arquivo, [
            'height' => Converter::cmToPixel($compacto ? 0.9 : 1.4),
            'alignment' => $alinhamento,
        ]);
    }

    /** @return array<string, mixed> */
    protected function pagina(): array
    {
        return [
            'pageSizeW' => $this->twips(21),
            'pageSizeH' => $this->twips(29.7),
            'marginTop' => $this->twips(1.4),
            'marginBottom' => $this->twips(1.2),
            'marginLeft' => $this->twips(1.6),
            'marginRight' => $this->twips(1.6),
        ];
    }

    /**
     * Centímetros em twips inteiros: o OOXML espera inteiro, e
     * `w:top="1700.787"` é medida inválida.
     */
    protected function twips(float $centimetros): int
    {
        return (int) round(Converter::cmToTwip($centimetros));
    }

    /**
     * Cala a depreciação do próprio PhpWord no PHP 8.5, como em
     * {@see GerarDocxDaProvaAction::semRuidoDoPhpWord()}.
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
}
