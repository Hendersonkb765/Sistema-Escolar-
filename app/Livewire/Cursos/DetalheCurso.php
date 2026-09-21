<?php

namespace App\Livewire\Cursos;

use App\Models\Curso;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class DetalheCurso extends Component
{
    use AuthorizesRequests;

    public Curso $curso;

    /**
     * O binding traz o curso pelo id sem filtro de escopo, de propósito:
     * a Policy então responde 403 — e não 404 — quando o recurso pertence
     * a outro Eixo. Adivinhar o id na URL não revela nem a existência.
     */
    public function mount(Curso $curso): void
    {
        $this->authorize('view', $curso);

        $this->curso = $curso->load('eixo', 'grades', 'turmas');
    }

    public function render(): View
    {
        return view('cursos.detalhe')->layout('components.layouts.app', [
            'titulo' => $this->curso->nome,
            'subtitulo' => $this->curso->eixo->nome.' · '.$this->curso->codigo,
        ]);
    }
}
