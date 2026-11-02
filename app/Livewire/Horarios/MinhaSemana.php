<?php

namespace App\Livewire\Horarios;

use App\Enums\StatusTurma;
use App\Models\QuadroHorario;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * As aulas da semana de quem leciona, tiradas do quadro de horários de
 * todas as turmas ativas. Só consulta: quem monta o quadro é a
 * coordenação.
 */
class MinhaSemana extends Component
{
    use AuthorizesRequests;

    public function mount(): void
    {
        $this->authorize('viewAny', QuadroHorario::class);
    }

    public function render(): View
    {
        $aulas = QuadroHorario::query()
            ->where('professor_id', auth()->id())
            ->whereHas('turma', fn (Builder $q) => $q->where('status', StatusTurma::Ativa))
            ->with('turma:id,nome,periodo_letivo', 'disciplina:id,nome')
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        return view('horarios.minha-semana', [
            'porDia' => $aulas->groupBy(fn (QuadroHorario $a) => $a->dia_semana->value),
        ])->layout('components.layouts.app', [
            'titulo' => 'Minha semana',
            'subtitulo' => 'Suas aulas no quadro de horários das turmas',
        ]);
    }
}
