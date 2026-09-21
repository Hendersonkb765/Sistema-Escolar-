<?php

namespace App\Livewire\Eixos;

use App\Livewire\Concerns\ComTabela;
use App\Models\Eixo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class ListaEixos extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    public function mount(): void
    {
        $this->authorize('viewAny', Eixo::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'codigo', 'status', 'created_at'];
    }

    protected function colunaPadrao(): string
    {
        return 'nome';
    }

    public function render(): View
    {
        $consulta = Eixo::query()
            ->visivelPara(auth()->user())
            ->withCount(['cursos', 'disciplinas', 'usuarios'])
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('codigo', 'like', $termo));
            });

        return view('eixos.lista', [
            'eixos' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
        ])->layout('components.layouts.app', [
            'titulo' => 'Eixos',
            'subtitulo' => 'Cada Eixo delimita o que sua conta enxerga no sistema',
        ]);
    }
}
