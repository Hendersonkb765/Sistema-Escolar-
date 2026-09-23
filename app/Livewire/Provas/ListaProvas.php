<?php

namespace App\Livewire\Provas;

use App\Enums\StatusProva;
use App\Livewire\Concerns\ComTabela;
use App\Models\Prova;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaProvas extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'turma', except: '')]
    public string $filtroTurma = '';

    #[Url(as: 'situacao', except: '')]
    public string $filtroStatus = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Prova::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['titulo', 'data_aplicacao', 'status', 'gerada_em'];
    }

    protected function colunaPadrao(): string
    {
        return 'gerada_em';
    }

    public function updatedFiltroTurma(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = Prova::query()
            ->visivelPara($usuario)
            ->with([
                'turma:id,nome,periodo,periodo_letivo,curso_id',
                'turma.curso:id,nome,eixo_id',
                'modelo:id,nome,eixo_id',
                'geradaPor:id,nome',
            ])
            ->withCount('questoes')
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('titulo', 'like', $termo)
                    ->orWhereHas('turma', fn (Builder $t) => $t->where('nome', 'like', $termo)));
            })
            ->when($this->filtroTurma !== '', fn (Builder $q) => $q->where('turma_id', $this->filtroTurma))
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus));

        return view('provas.lista', [
            'provas' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'turmas' => Turma::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
            'situacoes' => StatusProva::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Provas',
            'subtitulo' => 'Montadas a partir das questões aprovadas, com snapshot imutável',
        ]);
    }
}
