<?php

namespace App\Livewire\Cursos;

use App\Actions\Academico\CompararGradeComCursoAction;
use App\Actions\Academico\PublicarVersaoDeGradeAction;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Curso;
use App\Models\GradeCurricular;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Centro do curso: as disciplinas de cada período e as versões da grade
 * publicadas a partir delas.
 */
class DetalheCurso extends Component
{
    use AuthorizesRequests;

    public Curso $curso;

    public bool $confirmandoPublicacao = false;

    public string $observacoesPublicacao = '';

    /**
     * O binding traz o curso pelo id sem filtro de escopo, de propósito:
     * a Policy então responde 403 — e não 404 — quando o recurso pertence
     * a outro Eixo. Adivinhar o id na URL não revela nem a existência.
     */
    public function mount(Curso $curso): void
    {
        $this->authorize('view', $curso);

        $this->curso = $curso;
    }

    public function publicarGrade(PublicarVersaoDeGradeAction $action): void
    {
        $this->authorize('create', GradeCurricular::class);

        try {
            $grade = $action->executar(
                curso: $this->curso,
                autor: auth()->user(),
                observacoes: $this->observacoesPublicacao ?: null,
            );
        } catch (RegraDeNegocioException $excecao) {
            session()->flash('erro', $excecao->getMessage());
            $this->confirmandoPublicacao = false;

            return;
        }

        $this->confirmandoPublicacao = false;
        $this->observacoesPublicacao = '';

        session()->flash('sucesso',
            "Grade v{$grade->versao} publicada. Turmas já abertas seguem na versão que congelaram.");

        $this->redirectRoute('grades.show', $grade, navigate: true);
    }

    public function render(CompararGradeComCursoAction $comparar): View
    {
        $this->curso->load('eixo');

        $vigente = $this->curso->gradeVigente();

        return view('cursos.detalhe', [
            'porPeriodo' => $this->curso->disciplinasPorPeriodo(),
            'periodos' => $this->curso->periodos(),
            'grades' => $this->curso->grades()->withCount('turmas')->orderByDesc('versao')->get(),
            'turmas' => $this->curso->turmas()->with('grade:id,versao')->orderBy('periodo')->orderBy('nome')->get(),
            'gradeVigente' => $vigente,
            'diferencas' => $comparar->executar($this->curso, $vigente),
        ])->layout('components.layouts.app', [
            'titulo' => $this->curso->nome,
            'subtitulo' => $this->curso->eixo->nome.' · '.$this->curso->codigo.' · '.$this->curso->duracao_anos.' anos',
        ]);
    }
}
