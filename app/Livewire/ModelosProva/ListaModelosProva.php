<?php

namespace App\Livewire\ModelosProva;

use App\Livewire\Concerns\ComTabela;
use App\Livewire\Concerns\Notifica;
use App\Models\ModeloProva;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaModelosProva extends Component
{
    use AuthorizesRequests;
    use ComTabela;
    use Notifica;

    #[Url(as: 'inativos', except: false)]
    public bool $mostrarInativos = false;

    public function mount(): void
    {
        $this->authorize('viewAny', ModeloProva::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'created_at'];
    }

    protected function colunaPadrao(): string
    {
        return 'nome';
    }

    public function updatedMostrarInativos(): void
    {
        $this->resetPage();
    }

    /** Desativar preserva as provas já montadas; por isso não se apaga. */
    public function alternarAtivo(int $id): void
    {
        $modelo = ModeloProva::query()->findOrFail($id);

        $this->authorize('update', $modelo);

        $modelo->update(['ativo' => ! $modelo->ativo]);

        $this->notificarSucesso($modelo->ativo
            ? 'Modelo reativado: volta a aparecer na montagem de provas.'
            : 'Modelo desativado: as provas já montadas com ele continuam intactas.');
    }

    public function render(): View
    {
        $consulta = ModeloProva::query()
            ->visivelPara(auth()->user())
            ->with('eixo:id,nome')
            ->withCount('provas')
            ->when(! $this->mostrarInativos, fn (Builder $q) => $q->where('ativo', true))
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('instituicao', 'like', $termo));
            });

        return view('modelos-prova.lista', [
            'modelos' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
        ])->layout('components.layouts.app', [
            'titulo' => 'Modelos de prova',
            'subtitulo' => 'Cabeçalho, identificação do aluno e rodapé da folha impressa',
        ]);
    }
}
