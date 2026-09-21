<?php

namespace App\Livewire\Cursos;

use App\Livewire\Concerns\ComTabela;
use App\Models\Curso;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/**
 * Listagem de cursos dentro do escopo. O CRUD completo, com grades e
 * turmas, é entregue no milestone 2.
 */
class ListaCursos extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    public function mount(): void
    {
        $this->authorize('viewAny', Curso::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'codigo', 'duracao_anos', 'status'];
    }

    protected function colunaPadrao(): string
    {
        return 'nome';
    }

    public function render(): View
    {
        $consulta = Curso::query()
            ->visivelPara(auth()->user())
            ->with('eixo:id,nome')
            ->withCount(['turmas', 'grades'])
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('codigo', 'like', $termo));
            });

        return view('cursos.lista', [
            'cursos' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
        ])->layout('components.layouts.app', [
            'titulo' => 'Cursos',
            'subtitulo' => 'Cursos dos Eixos vinculados à sua conta',
        ]);
    }
}
