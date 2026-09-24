<?php

namespace App\Actions\Prova;

use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\User;
use App\Support\LayoutDaFolha;
use App\Support\LogoDaFolha;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
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

        IOFactory::createWriter($documento, 'Word2007')->save($temporario);

        $conteudo = (string) file_get_contents($temporario);

        @unlink($temporario);

        return $conteudo;
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
            $modelo->instituicao ?: config('app.name'),
            ['bold' => true, 'size' => $layout->tamanho + 1, 'allCaps' => true],
            ['alignment' => Jc::CENTER, 'spaceAfter' => 0],
        );

        $centro->addText(
            ($modelo->nome_avaliacao ?: 'Avaliação').' — '.$prova->titulo,
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

        foreach ($prova->questoesPorDisciplina() as $disciplina => $questoes) {
            $secao->addText(
                "{$disciplina} — questões {$questoes->min('numero')} a {$questoes->max('numero')}",
                'disciplina',
                ['spaceBefore' => 120, 'spaceAfter' => 60],
            );

            foreach ($questoes as $questao) {
                $this->questao($secao, $questao, $prova, $comGabarito);
            }
        }
    }

    protected function questao(Section $secao, ProvaQuestao $questao, Prova $prova, bool $comGabarito): void
    {
        $peso = $prova->mostrarPesos()
            ? ' (peso '.number_format((float) $questao->peso, 2, ',', '.').')'
            : '';

        $paragrafo = $secao->addTextRun('justificado');
        $paragrafo->addText("{$questao->numero}.{$peso} ", ['bold' => true]);
        $paragrafo->addText((string) $questao->enunciado_snapshot);

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
                $secao->addText((string) $bloco['conteudo'], null, 'justificado');
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
