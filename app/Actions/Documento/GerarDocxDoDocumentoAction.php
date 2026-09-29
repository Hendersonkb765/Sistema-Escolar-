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
        $compacto = $modelo->viasPorPagina() > 1;
        $tamanho = $compacto ? 9 : 11;

        $documento = new PhpWord;
        $documento->setDefaultFontName('Arial');
        $documento->setDefaultFontSize($tamanho);
        $documento->getSettings()->setThemeFontLang(new Language(Language::PT_BR));
        $documento->setDefaultParagraphStyle([
            'alignment' => Jc::BOTH,
            'lineHeight' => $compacto ? 1.15 : 1.3,
        ]);

        $documento->addParagraphStyle('corpo', [
            'alignment' => Jc::BOTH,
            'spaceAfter' => $compacto ? 40 : 90,
        ]);
        $documento->addParagraphStyle('centrado', [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 0,
        ]);
        $documento->addFontStyle('discreto', ['size' => $tamanho - 2, 'color' => '555555']);

        $secao = $documento->addSection($this->pagina());

        $porPagina = $modelo->viasPorPagina();
        $instituicao = $this->base->instituicaoDaTurma($turma);
        $logos = $this->base->logosDaTurma($turma);

        foreach ($this->vias($modelo, $turma, $alunos) as $indice => $trechos) {
            if ($indice > 0) {
                $indice % $porPagina === 0
                    ? $secao->addPageBreak()
                    : $this->corte($secao);
            }

            $this->cabecalho($secao, $modelo, $instituicao, $logos, $compacto);
            $this->corpo($secao, $trechos, $alunos, $compacto);
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
    protected function corpo(Section $secao, array $trechos, Collection $alunos, bool $compacto): void
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
    protected function assinatura(Section $secao, string $rotulo): void
    {
        $secao->addText(str_repeat('_', 40), null, 'centrado');
        $secao->addText($rotulo === '' ? ' ' : $rotulo, 'discreto', 'centrado');
    }

    /** Quadro em branco para escrever à mão. */
    protected function espaco(Section $secao, bool $compacto): void
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
    protected function listaDeAlunos(Section $secao, Collection $alunos, bool $compacto): void
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

    /** A linha de corte entre duas vias da mesma folha. */
    protected function corte(Section $secao): void
    {
        $secao->addText(
            str_repeat('- ', 45),
            ['size' => 8, 'color' => '999999'],
            ['alignment' => Jc::CENTER, 'spaceBefore' => 120, 'spaceAfter' => 120],
        );
    }

    /**
     * @param  array{esquerda: ?string, direita: ?string}  $logos
     */
    protected function cabecalho(
        Section $secao,
        ModeloDocumento $modelo,
        string $instituicao,
        array $logos,
        bool $compacto,
    ): void {
        $util = 21 - 3.2;
        $ladoDaLogo = $compacto ? 1.6 : 2.4;

        $tabela = $secao->addTable(['cellMargin' => 0]);
        $tabela->addRow();

        $this->logo($tabela->addCell($this->twips($ladoDaLogo)), $logos['esquerda'], Jc::START, $compacto);

        $centro = $tabela->addCell($this->twips($util - 2 * $ladoDaLogo));

        $centro->addText(
            $instituicao,
            ['bold' => true, 'size' => $compacto ? 9 : 12],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
        );
        $centro->addText(
            $modelo->nome,
            ['size' => $compacto ? 8 : 10],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
        );

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
