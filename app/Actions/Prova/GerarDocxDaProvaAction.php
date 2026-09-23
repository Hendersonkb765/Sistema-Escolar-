<?php

namespace App\Actions\Prova;

use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

/**
 * Gera a prova em Word (.docx), com o mesmo layout de duas colunas.
 *
 * O Word não aceita o HTML da folha, então o documento é montado bloco a
 * bloco. O cabeçalho e a identificação ficam numa seção de coluna única,
 * e as questões numa seção contínua com o número de colunas escolhido —
 * é assim que o Word faz texto em colunas.
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
        $tamanho = (int) ($modelo->layout['tamanho'] ?? 11);
        $fonte = ($modelo->layout['fonte'] ?? 'sans') === 'serif' ? 'Georgia' : 'Arial';

        $documento = new PhpWord;
        $documento->setDefaultFontName($fonte);
        $documento->setDefaultFontSize($tamanho);

        $documento->addTitleStyle(1, ['bold' => true, 'size' => $tamanho + 2]);
        $documento->addParagraphStyle('justificado', ['alignment' => Jc::BOTH, 'spaceAfter' => 60]);
        $documento->addFontStyle('codigo', ['name' => 'Courier New', 'size' => max(8, $tamanho - 2)]);
        $documento->addFontStyle('disciplina', ['bold' => true, 'size' => $tamanho]);
        $documento->addFontStyle('discreto', ['size' => max(8, $tamanho - 2), 'color' => '555555']);

        $this->cabecalho($documento, $prova, $modelo, $tamanho);
        $this->corpoEmColunas($documento, $prova, $comGabarito);

        if ($comGabarito) {
            $this->gabarito($documento, $prova);
        }

        return $documento;
    }

    protected function cabecalho(PhpWord $documento, Prova $prova, $modelo, int $tamanho): void
    {
        $secao = $documento->addSection([
            'marginTop' => Converter::cmToTwip(1.8),
            'marginBottom' => Converter::cmToTwip(1.6),
            'marginLeft' => Converter::cmToTwip(1.4),
            'marginRight' => Converter::cmToTwip(1.4),
        ]);

        $secao->addText($modelo->instituicao ?: config('app.name'), ['bold' => true, 'size' => $tamanho + 2]);
        $secao->addText(
            ($modelo->nome_avaliacao ?: 'Avaliação').' — '.$prova->titulo,
            ['size' => $tamanho + 1],
        );

        $turma = $prova->turma;

        $secao->addText(
            "{$turma->curso->nome} · Turma {$turma->nome} · {$turma->periodo}º período"
            .($prova->data_aplicacao ? ' · '.$prova->data_aplicacao->format('d/m/Y') : ''),
            'discreto',
        );

        if ($modelo->cabecalho) {
            $secao->addText($modelo->cabecalho, 'discreto');
        }

        $secao->addTextBreak(1);

        $campos = $modelo->campos_identificacao ?: ['aluno', 'matricula', 'turma', 'data'];
        $tabela = $secao->addTable(['borderSize' => 6, 'borderColor' => '111111', 'cellMargin' => 60]);

        if (in_array('aluno', $campos, true)) {
            $tabela->addRow();
            $tabela->addCell(Converter::cmToTwip(18))->addText('Aluno(a): '.str_repeat('_', 60));
        }

        $linha = [];

        if (in_array('matricula', $campos, true)) {
            $linha[] = 'Matrícula: '.str_repeat('_', 18);
        }
        if (in_array('turma', $campos, true)) {
            $linha[] = 'Turma: '.$turma->nome;
        }
        if (in_array('data', $campos, true)) {
            $linha[] = 'Data: ___/___/______';
        }

        if ($linha !== []) {
            $tabela->addRow();
            $tabela->addCell(Converter::cmToTwip(18))->addText(implode('     ', $linha));
        }

        if ($prova->instrucoes) {
            $secao->addTextBreak(1);
            $secao->addText($prova->instrucoes, 'discreto', 'justificado');
        }
    }

    /** As questões entram numa seção contínua com o número de colunas. */
    protected function corpoEmColunas(PhpWord $documento, Prova $prova, bool $comGabarito): void
    {
        $secao = $documento->addSection([
            'breakType' => 'continuous',
            'colsNum' => $prova->colunas(),
            'colsSpace' => Converter::cmToTwip(0.8),
            'marginLeft' => Converter::cmToTwip(1.4),
            'marginRight' => Converter::cmToTwip(1.4),
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
                    ['spaceAfter' => 0],
                );

                foreach (preg_split('/\R/', trim((string) $bloco['conteudo'])) as $linha) {
                    // Uma linha por parágrafo: o Word não preserva quebras
                    // dentro de um mesmo bloco de texto.
                    $secao->addText(
                        htmlspecialchars($linha === '' ? ' ' : $linha, ENT_QUOTES),
                        'codigo',
                        ['spaceAfter' => 0, 'spaceBefore' => 0],
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

    protected function gabarito(PhpWord $documento, Prova $prova): void
    {
        $secao = $documento->addSection(['breakType' => 'continuous', 'colsNum' => 1]);

        $secao->addText('Gabarito', ['bold' => true, 'size' => 12], ['spaceBefore' => 200]);

        $tabela = $secao->addTable(['borderSize' => 6, 'borderColor' => '333333', 'cellMargin' => 40]);
        $gabarito = $prova->gabarito();

        $tabela->addRow();
        foreach ($gabarito as $numero => $letra) {
            $tabela->addCell(Converter::cmToTwip(1))->addText((string) $numero, null, ['alignment' => Jc::CENTER]);
        }

        $tabela->addRow();
        foreach ($gabarito as $letra) {
            $tabela->addCell(Converter::cmToTwip(1))->addText($letra, ['bold' => true], ['alignment' => Jc::CENTER]);
        }
    }
}
