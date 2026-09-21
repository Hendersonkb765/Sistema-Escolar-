<?php

namespace App\Livewire\Disciplinas;

use App\Livewire\Concerns\ComTabela;
use App\Models\Curso;
use App\Models\Disciplina;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaDisciplinas extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'curso', except: '')]
    public string $filtroCurso = '';

    #[Url(as: 'periodo', except: '')]
    public string $filtroPeriodo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Disciplina::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'codigo', 'periodo', 'carga_horaria', 'status'];
    }

    protected function colunaPadrao(): string
    {
        return 'periodo';
    }

    public function updatedFiltroCurso(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroPeriodo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = Disciplina::query()
            ->visivelPara($usuario)
            ->with('curso:id,nome,codigo,eixo_id')
            ->withCount('gradeDisciplinas')
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('codigo', 'like', $termo));
            })
            ->when($this->filtroCurso !== '', fn (Builder $q) => $q->where('curso_id', $this->filtroCurso))
            ->when($this->filtroPeriodo !== '', fn (Builder $q) => $q->where('periodo', $this->filtroPeriodo));

        return view('disciplinas.lista', [
            'disciplinas' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'cursos' => Curso::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
        ])->layout('components.layouts.app', [
            'titulo' => 'Disciplinas',
            'subtitulo' => 'Cada disciplina pertence a um curso e a um período dele',
        ]);
    }
}
