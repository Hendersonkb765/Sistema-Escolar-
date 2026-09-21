<?php

namespace App\Livewire\Disciplinas;

use App\Livewire\Concerns\ComTabela;
use App\Models\Disciplina;
use App\Models\Eixo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaDisciplinas extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'eixo', except: '')]
    public string $filtroEixo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Disciplina::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'codigo', 'status'];
    }

    protected function colunaPadrao(): string
    {
        return 'nome';
    }

    public function updatedFiltroEixo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = Disciplina::query()
            ->visivelPara($usuario)
            ->with('eixo:id,nome')
            ->withCount('gradeDisciplinas')
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('codigo', 'like', $termo));
            })
            ->when($this->filtroEixo !== '', fn (Builder $q) => $q->where('eixo_id', $this->filtroEixo));

        return view('disciplinas.lista', [
            'disciplinas' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'eixos' => Eixo::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
        ])->layout('components.layouts.app', [
            'titulo' => 'Disciplinas',
            'subtitulo' => 'Independentes de ano — a mesma disciplina serve a vários cursos e anos',
        ]);
    }
}
