<?php

namespace App\Livewire\Turmas;

use App\Enums\StatusTurma;
use App\Livewire\Concerns\ComTabela;
use App\Models\Curso;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaTurmas extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'curso', except: '')]
    public string $filtroCurso = '';

    #[Url(as: 'situacao', except: '')]
    public string $filtroStatus = '';

    #[Url(as: 'ano', except: '')]
    public string $filtroAno = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Turma::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['identificacao', 'ano_curso', 'periodo_letivo', 'status'];
    }

    protected function colunaPadrao(): string
    {
        return 'identificacao';
    }

    public function updatedFiltroCurso(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroAno(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = Turma::query()
            ->visivelPara($usuario)
            ->with(['curso:id,nome,codigo,duracao_anos,eixo_id', 'grade:id,versao,status'])
            ->withCount('alunos')
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('identificacao', 'like', $termo)
                    ->orWhere('periodo_letivo', 'like', $termo));
            })
            ->when($this->filtroCurso !== '', fn (Builder $q) => $q->where('curso_id', $this->filtroCurso))
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus))
            ->when($this->filtroAno !== '', fn (Builder $q) => $q->where('ano_curso', $this->filtroAno));

        return view('turmas.lista', [
            'turmas' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'cursos' => Curso::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
            'situacoes' => StatusTurma::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Turmas',
            'subtitulo' => 'Cada turma congela a versão de grade com que começou',
        ]);
    }
}
