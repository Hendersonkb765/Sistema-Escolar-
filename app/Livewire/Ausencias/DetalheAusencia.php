<?php

namespace App\Livewire\Ausencias;

use App\Models\Ausencia;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** A ausência com cada aula do dia e o que foi planejado para ela. */
class DetalheAusencia extends Component
{
    use AuthorizesRequests;

    #[Locked]
    public Ausencia $ausencia;

    public function mount(Ausencia $ausencia): void
    {
        $this->authorize('view', $ausencia);

        $this->ausencia = $ausencia;
    }

    public function render(): View
    {
        $this->ausencia->load('professor:id,nome', 'aulas.turma:id,nome', 'aulas.disciplina:id,nome');

        return view('ausencias.detalhe')->layout('components.layouts.app', [
            'titulo' => "Ausência de {$this->ausencia->data->format('d/m/Y')}",
            'subtitulo' => $this->ausencia->professor->nome,
        ]);
    }
}
