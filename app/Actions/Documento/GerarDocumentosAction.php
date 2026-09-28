<?php

namespace App\Actions\Documento;

use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\ModeloDocumento;
use App\Models\ModeloProva;
use App\Models\Turma;
use App\Models\User;
use App\Support\CamposDoDocumento;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

/**
 * O PDF dos documentos do aluno.
 *
 * Três formas, decididas pelo modelo e não por opção solta na tela:
 *
 * - **coletivo**: uma via, com a tabela dos alunos escolhidos dentro;
 * - **individual, 1 por página**: uma folha por aluno, para assinar e
 *   devolver;
 * - **individual, 2 ou 3 por página**: as vias empilhadas na mesma
 *   folha, separadas por linha de corte. É o que a escola usa quando o
 *   texto é curto — 90 autorizações em 30 folhas em vez de 90.
 */
class GerarDocumentosAction
{
    public function conteudo(
        ModeloDocumento $modelo,
        Turma $turma,
        array $alunoIds,
        User $autor,
    ): string {
        Gate::forUser($autor)->authorize('gerar', $modelo);
        Gate::forUser($autor)->authorize('view', $turma);

        $alunos = $this->alunosEscolhidos($turma, $alunoIds, $autor);

        $invalidos = $modelo->camposInvalidos();

        if ($invalidos !== []) {
            throw RegraDeNegocioException::porque(
                'O modelo usa campo que não existe neste tipo de documento: '
                .implode(', ', array_map(fn (string $campo) => "{{ {$campo} }}", $invalidos))
                .'. Corrija o modelo antes de gerar.'
            );
        }

        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => 14,
            'margin_bottom' => 12,
            'margin_left' => 16,
            'margin_right' => 16,
            'tempDir' => $this->pastaTemporaria(),
        ]);

        $mpdf->WriteHTML(View::make('documentos.folha', [
            'modelo' => $modelo,
            'turma' => $turma,
            'vias' => $this->vias($modelo, $turma, $alunos),
            'porPagina' => $modelo->viasPorPagina(),
            'logos' => $this->logos($turma),
            'instituicao' => $this->instituicao($turma),
        ])->render());

        return (string) $mpdf->Output('', 'S');
    }

    public function nomeDoArquivo(ModeloDocumento $modelo, Turma $turma): string
    {
        return Str::slug($modelo->nome.'-'.$turma->nome).'.pdf';
    }

    /**
     * Os alunos escolhidos, na ordem da chamada.
     *
     * A seleção vem da tela, então os ids são entrada de usuário: são
     * cruzados com a turma e com o escopo de quem gera. Um id de outra
     * turma não entra — nem gera erro que revele que ele existe.
     *
     * @param  array<int, int|string>  $alunoIds
     * @return Collection<int, Aluno>
     */
    public function alunosEscolhidos(Turma $turma, array $alunoIds, User $autor): Collection
    {
        $ids = array_filter(array_map('intval', $alunoIds));

        if ($ids === []) {
            throw RegraDeNegocioException::porque(
                'Escolha pelo menos um aluno para gerar o documento.'
            );
        }

        $alunos = Aluno::query()
            ->visivelPara($autor)
            ->where('turma_id', $turma->getKey())
            ->whereIn('id', $ids)
            ->orderBy('nome')
            ->get(['id', 'nome', 'ra', 'turma_id']);

        if ($alunos->isEmpty()) {
            throw RegraDeNegocioException::porque(
                'Nenhum dos alunos escolhidos pertence a esta turma.'
            );
        }

        return $alunos;
    }

    /**
     * Cada via já renderizada.
     *
     * No documento coletivo é uma só, com a lista dentro. No individual é
     * uma por aluno.
     *
     * @param  Collection<int, Aluno>  $alunos
     * @return array<int, HtmlString>
     */
    protected function vias(ModeloDocumento $modelo, Turma $turma, Collection $alunos): array
    {
        $comum = $this->dadosComuns($turma);

        if (! $modelo->ehIndividual()) {
            return [CamposDoDocumento::render($modelo->corpo, array_merge(
                CamposDoDocumento::contexto(null, null, ...$comum),
                ['lista_de_alunos' => $this->tabelaDeAlunos($alunos)],
            ))];
        }

        return $alunos
            ->values()
            ->map(fn (Aluno $aluno, int $indice) => CamposDoDocumento::render(
                $modelo->corpo,
                CamposDoDocumento::contexto($aluno, $indice + 1, ...$comum),
            ))
            ->all();
    }

    /** @return array<string, string|int> */
    protected function dadosComuns(Turma $turma): array
    {
        $turma->loadMissing('curso.eixo');

        return [
            'turma' => (string) $turma->nome,
            'periodo' => (int) $turma->periodo,
            'periodoLetivo' => (string) $turma->periodo_letivo,
            'curso' => (string) $turma->curso->nome,
            'eixo' => (string) $turma->curso->eixo->nome,
            'instituicao' => $this->instituicao($turma),
        ];
    }

    /** @param  Collection<int, Aluno>  $alunos */
    protected function tabelaDeAlunos(Collection $alunos): string
    {
        return View::make('documentos.lista-de-alunos', ['alunos' => $alunos])->render();
    }

    /** O nome da escola vem do modelo de prova do Eixo, como no boletim. */
    protected function instituicao(Turma $turma): string
    {
        return $this->modeloDaTurma($turma)?->instituicao
            ?: (string) config('instituicao.nome');
    }

    protected function modeloDaTurma(Turma $turma): ?ModeloProva
    {
        return ModeloProva::query()
            ->where('eixo_id', $turma->loadMissing('curso')->curso->eixo_id)
            ->where('ativo', true)
            ->orderBy('id')
            ->first();
    }

    /** @return array{esquerda: ?string, direita: ?string} */
    protected function logos(Turma $turma): array
    {
        $modelo = $this->modeloDaTurma($turma);

        if ($modelo === null) {
            return ['esquerda' => null, 'direita' => null];
        }

        return [
            'esquerda' => $modelo->logo('esquerda')->arquivo(),
            'direita' => $modelo->logo('direita')->arquivo(),
        ];
    }

    protected function pastaTemporaria(): string
    {
        $pasta = storage_path('framework/mpdf');

        if (! is_dir($pasta)) {
            mkdir($pasta, 0775, recursive: true);
        }

        return $pasta;
    }
}
