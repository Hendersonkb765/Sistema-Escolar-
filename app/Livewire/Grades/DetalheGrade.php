<?php

namespace App\Livewire\Grades;

use App\Models\GradeCurricular;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Consulta de uma versão publicada. Não há edição: a grade é o registro
 * de como o curso estava, e mudanças no cadastro geram uma nova versão.
 */
class DetalheGrade extends Component
{
    use AuthorizesRequests;

    public GradeCurricular $grade;

    public function mount(GradeCurricular $grade): void
    {
        $this->authorize('view', $grade);

        $this->grade = $grade;
    }

    public function render(): View
    {
        $this->grade->load(['curso.eixo', 'turmas.curso.eixo', 'origem']);

        return view('grades.detalhe', [
            'porPeriodo' => $this->grade->porPeriodo(),
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
