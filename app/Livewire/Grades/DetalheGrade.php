<?php

namespace App\Livewire\Grades;

use App\Actions\Academico\CriarNovaVersaoDeGradeAction;
use App\Actions\Academico\PublicarGradeAction;
use App\Exceptions\RegraDeNegocioException;
use App\Models\GradeCurricular;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class DetalheGrade extends Component
{
    use AuthorizesRequests;

    public GradeCurricular $grade;

    public bool $confirmandoPublicacao = false;

    public function mount(GradeCurricular $grade): void
    {
        $this->authorize('view', $grade);

        $this->grade = $grade;
    }

    /**
     * Gera a próxima versão a partir desta, copiando as disciplinas.
     * A versão de origem permanece exatamente como está, e as turmas que
     * a congelaram não são tocadas.
     */
    public function criarNovaVersao(CriarNovaVersaoDeGradeAction $action): void
    {
        $this->authorize('novaVersao', $this->grade);

        $nova = $action->executar($this->grade, auth()->user());

        session()->flash('sucesso', "Versão {$nova->versao} criada em rascunho a partir da versão {$this->grade->versao}.");

        $this->redirectRoute('grades.editar', $nova, navigate: true);
    }

    public function publicar(PublicarGradeAction $action): void
    {
        $this->authorize('publicar', $this->grade);

        try {
            $action->executar($this->grade, auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            session()->flash('erro', $excecao->getMessage());
            $this->confirmandoPublicacao = false;

            return;
        }

        $this->grade->refresh();
        $this->confirmandoPublicacao = false;

        session()->flash('sucesso', "Versão {$this->grade->versao} entrou em vigência. As turmas já abertas seguem na versão que congelaram.");
    }

    public function render(): View
    {
        $this->grade->load(['curso.eixo', 'disciplinas.disciplina', 'turmas.curso.eixo', 'origem']);

        return view('grades.detalhe', [
            'porAno' => $this->grade->disciplinas->groupBy('ano_curso')->sortKeys(),
            'outrasVersoes' => GradeCurricular::query()
                ->where('curso_id', $this->grade->curso_id)
                ->whereKeyNot($this->grade->getKey())
                ->withCount('turmas')
                ->orderByDesc('versao')
                ->get(),
        ])->layout('components.layouts.app', [
            'titulo' => "Grade v{$this->grade->versao} — {$this->grade->curso->nome}",
            'subtitulo' => 'Vigência '.$this->grade->ano_vigencia,
        ]);
    }
}
