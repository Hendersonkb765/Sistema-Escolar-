<?php

namespace App\Livewire\Importacoes;

use App\Enums\StatusImportacao;
use App\Livewire\Concerns\ComTabela;
use App\Models\Importacao;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaImportacoes extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'situacao', except: '')]
    public string $filtroStatus = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Importacao::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['created_at', 'status', 'total_linhas'];
    }

    protected function colunaPadrao(): string
    {
        return 'created_at';
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $consulta = Importacao::query()
            ->visivelPara(auth()->user())
            ->with([
                'prova:id,titulo,versao,turma_id',
                'prova.turma:id,nome,curso_id',
                'prova.turma.curso:id,nome,eixo_id',
                'usuario:id,nome',
            ])
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome_original', 'like', $termo)
                    ->orWhereHas('prova', fn (Builder $p) => $p->where('titulo', 'like', $termo)));
            })
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus));

        return view('importacoes.lista', [
            'importacoes' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'situacoes' => StatusImportacao::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Importações',
            'subtitulo' => 'Cada envio guarda o relatório da conferência que o aprovou',
        ]);
    }
}
