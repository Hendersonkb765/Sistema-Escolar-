<?php

namespace App\Actions\Resultado;

use App\Enums\Bimestre;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\ModeloProva;
use App\Models\NotaDisciplina;
use App\Models\Prova;
use App\Models\Turma;
use App\Models\User;
use App\Support\LayoutDaFolha;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;
use Mpdf\Mpdf;

/**
 * O boletim da turma: **uma página por aluno**.
 *
 * Cada página traz só as notas de um aluno, para a escola poder entregar
 * a folha certa a cada um. Um documento único com a turma inteira seria
 * mais simples de gerar e impossível de distribuir sem mostrar a nota de
 * um aluno para o outro.
 *
 * As notas vêm de `notas_disciplina`, que guarda também as duas somas de
 * peso — a conta fica conferível na própria folha.
 */
class GerarBoletimAction
{
    public function conteudo(Turma $turma, User $autor, ?Bimestre $bimestre = null): string
    {
        Gate::forUser($autor)->authorize('view', $turma);

        $alunos = $this->alunosComNota($turma, $autor, $bimestre);

        if ($alunos->isEmpty()) {
            throw RegraDeNegocioException::porque(
                'Nenhum aluno desta turma tem nota lançada'
                .($bimestre === null ? '.' : " no {$bimestre->rotulo()}.")
                .' Importe os resultados antes de gerar o boletim.'
            );
        }

        $layout = LayoutDaFolha::de([]);

        $mpdf = new Mpdf([
            'format' => 'A4',
            'margin_top' => $layout->margens['superior'],
            'margin_bottom' => $layout->margens['inferior'],
            'margin_left' => $layout->margens['esquerda'],
            'margin_right' => $layout->margens['direita'],
            'margin_header' => max(5, $layout->margens['superior'] / 2),
            'tempDir' => $this->pastaTemporaria(),
        ]);

        $mpdf->WriteHTML(View::make('resultados.boletim', [
            'turma' => $turma->loadMissing('curso'),
            'bimestre' => $bimestre,
            'alunos' => $alunos,
            'layout' => $layout,
            'modelo' => $this->modeloDaTurma($turma),
            'logos' => $this->logos($turma),
            'emitidoEm' => now(),
        ])->render());

        return (string) $mpdf->Output('', 'S');
    }

    public function nomeDoArquivo(Turma $turma, ?Bimestre $bimestre = null): string
    {
        return Str::slug(
            'boletim-'.$turma->nome.'-'.($bimestre?->value.'o-bimestre' ?: 'todos-os-bimestres')
        ).'.pdf';
    }

    /**
     * Cada aluno com as suas notas por disciplina e bimestre.
     *
     * O escopo é o de quem gera: a coordenação do Eixo. O professor
     * enxerga a turma, mas o boletim reúne disciplinas que não são dele
     * — por isso a Policy do boletim é a da turma.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function alunosComNota(Turma $turma, User $autor, ?Bimestre $bimestre = null): Collection
    {
        $provas = Prova::query()
            ->visivelPara($autor)
            ->where('turma_id', $turma->getKey())
            ->when($bimestre !== null, fn ($q) => $q->where('bimestre', $bimestre))
            ->pluck('id');

        if ($provas->isEmpty()) {
            return collect();
        }

        $notas = NotaDisciplina::query()
            ->whereHas('resultado', fn ($q) => $q->whereIn('prova_id', $provas))
            ->with([
                'disciplina:id,nome',
                'resultado:id,prova_id,aluno_id',
                'resultado.aluno:id,nome,ra,turma_id',
                'resultado.prova:id,titulo,bimestre',
            ])
            ->get();

        return $notas
            ->groupBy(fn (NotaDisciplina $nota) => $nota->resultado->aluno_id)
            ->map(function (Collection $doAluno) {
                /** @var Aluno $aluno */
                $aluno = $doAluno->first()->resultado->aluno;

                $linhas = $doAluno
                    ->map(fn (NotaDisciplina $nota) => [
                        'disciplina' => $nota->disciplina->nome,
                        'bimestre' => $nota->resultado->prova->bimestre,
                        'prova' => $nota->resultado->prova->titulo,
                        'nota' => (float) $nota->nota,
                        'acertos' => (float) $nota->soma_pesos_acertos,
                        'total' => (float) $nota->soma_pesos_total,
                    ])
                    // Pelo valor do bimestre, e não pelo enum: ordenar
                    // objetos deixaria 4º antes de 2º.
                    ->sortBy(fn (array $linha) => sprintf('%s-%d', $linha['disciplina'], $linha['bimestre']->value))
                    ->values();

                return [
                    'aluno' => $aluno,
                    'linhas' => $linhas,
                    'media' => round((float) $linhas->avg('nota'), 2),
                ];
            })
            ->sortBy(fn (array $dados) => $dados['aluno']->nome)
            ->values();
    }

    /** A moldura do boletim vem do modelo de prova do Eixo da turma. */
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
