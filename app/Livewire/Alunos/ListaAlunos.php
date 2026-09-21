<?php

namespace App\Livewire\Alunos;

use App\Enums\StatusAluno;
use App\Livewire\Concerns\ComTabela;
use App\Models\Aluno;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaAlunos extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'turma', except: '')]
    public string $filtroTurma = '';

    #[Url(as: 'situacao', except: '')]
    public string $filtroStatus = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Aluno::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['nome', 'matricula', 'status'];
    }

    protected function colunaPadrao(): string
    {
        return 'nome';
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

        $consulta = Aluno::query()
            ->visivelPara($usuario)
            // `eixo_id` é o que a Policy lê para decidir o escopo de cada linha.
            ->with('turma:id,identificacao,curso_id,ano_curso', 'turma.curso:id,nome,eixo_id')
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('matricula', 'like', $termo));
            })
            ->when($this->filtroTurma !== '', fn (Builder $q) => $q->where('turma_id', $this->filtroTurma))
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus));

        return view('alunos.lista', [
            'alunos' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'turmas' => Turma::query()->visivelPara($usuario)->orderBy('identificacao')->get(),
            'situacoes' => StatusAluno::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Alunos',
            'subtitulo' => 'Matrículas por turma, com histórico de movimentações',
        ]);
    }
}
