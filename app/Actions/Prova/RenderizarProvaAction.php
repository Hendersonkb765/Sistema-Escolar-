<?php

namespace App\Actions\Prova;

use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\Turma;
use App\Support\LogoDaFolha;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

/**
 * Monta o HTML da folha de prova.
 *
 * A mesma view serve à pré-visualização na tela e ao PDF — o que o
 * professor confere é exatamente o que sai impresso. Duas diferenças,
 * ambas de motor e não de conteúdo:
 *
 * - imagens: o navegador busca pela URL, o mPDF lê o arquivo no disco;
 * - colunas: o navegador entende `column-count`, o mPDF quer a própria
 *   tag `<columns>`.
 */
class RenderizarProvaAction
{
    public function paraTela(Prova $prova, bool $comGabarito = false): string
    {
        return $this->renderizar($prova, $comGabarito, paraImpressao: false);
    }

    public function paraPdf(Prova $prova, bool $comGabarito = false): string
    {
        return $this->renderizar($prova, $comGabarito, paraImpressao: true);
    }

    /**
     * Amostra da folha para quem está desenhando o modelo: a moldura é a
     * real, as questões são inventadas. Nada aqui é persistido.
     */
    public function amostraDoModelo(ModeloProva $modelo): string
    {
        $curso = new Curso(['nome' => 'Curso de exemplo']);
        $turma = new Turma(['nome' => '2 A', 'periodo' => 2]);
        $turma->setRelation('curso', $curso);

        $prova = new Prova([
            'titulo' => 'Amostra do modelo',
            'instrucoes' => $modelo->instrucoes,
            'configuracao' => ['colunas' => 2, 'mostrar_pesos' => true],
        ]);
        $prova->setRelation('turma', $turma);
        $prova->setRelation('modelo', $modelo);

        return View::make('provas.folha.documento', [
            'prova' => $prova,
            'modelo' => $modelo,
            'questoesPorDisciplina' => collect([
                'Disciplina de exemplo' => collect([
                    $this->questaoDeAmostra(1, 'Assim aparece o enunciado de uma questão nesta folha.'),
                    $this->questaoDeAmostra(2, 'E assim aparece a seguinte, já na segunda coluna quando couber.'),
                ]),
            ]),
            'comGabarito' => false,
            'paraImpressao' => false,
            'layout' => $modelo->layoutDaFolha(),
            'logos' => $this->logos($modelo, paraImpressao: false),
            'origemDaImagem' => fn (string $caminho) => $this->imagem($caminho, paraImpressao: false),
        ])->render();
    }

    protected function questaoDeAmostra(int $numero, string $enunciado): ProvaQuestao
    {
        $questao = new ProvaQuestao([
            'numero' => $numero,
            'peso' => 1,
            'enunciado_snapshot' => $enunciado,
            'blocos_snapshot' => [],
            'alternativas_snapshot' => [
                ['letra' => 'A', 'texto' => 'Primeira alternativa', 'correta' => true],
                ['letra' => 'B', 'texto' => 'Segunda alternativa', 'correta' => false],
                ['letra' => 'C', 'texto' => 'Terceira alternativa', 'correta' => false],
            ],
        ]);

        $questao->setRelation('disciplina', new Disciplina(['nome' => 'Disciplina de exemplo']));

        return $questao;
    }

    protected function renderizar(Prova $prova, bool $comGabarito, bool $paraImpressao): string
    {
        $prova->loadMissing(['turma.curso', 'modelo']);

        $modelo = $prova->modelo;

        return View::make('provas.folha.documento', [
            'prova' => $prova,
            'modelo' => $modelo,
            'questoesPorDisciplina' => $prova->questoesPorDisciplina(),
            'comGabarito' => $comGabarito,
            'paraImpressao' => $paraImpressao,
            'layout' => $modelo->layoutDaFolha(),
            'logos' => $this->logos($modelo, $paraImpressao),
            'origemDaImagem' => fn (string $caminho) => $this->imagem($caminho, $paraImpressao),
        ])->render();
    }

    /**
     * As duas logos do cabeçalho — uma em cada extremo. Cada lado
     * resolve sozinho se usa a logo que acompanha o sistema, uma imagem
     * enviada, ou nenhuma.
     *
     * @return array{esquerda: ?string, direita: ?string}
     */
    protected function logos(ModeloProva $modelo, bool $paraImpressao): array
    {
        $enderecos = [];

        foreach (LogoDaFolha::LADOS as $lado) {
            $logo = $modelo->logo($lado);

            $enderecos[$lado] = $paraImpressao ? $logo->arquivo() : $logo->url();
        }

        return $enderecos;
    }

    /**
     * O mPDF não busca a imagem por HTTP: ele lê o arquivo. Na tela, o
     * navegador precisa da URL.
     */
    protected function imagem(string $caminho, bool $paraImpressao): string
    {
        if (! $paraImpressao) {
            return Storage::disk('public')->url($caminho);
        }

        return Storage::disk('public')->exists($caminho)
            ? Storage::disk('public')->path($caminho)
            : '';
    }
}
