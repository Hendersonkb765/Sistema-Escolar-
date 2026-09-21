<?php

namespace App\Livewire\Grades;

use App\Enums\StatusGrade;
use App\Livewire\Concerns\ComTabela;
use App\Models\Curso;
use App\Models\GradeCurricular;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class ListaGrades extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'curso', except: '')]
    public string $filtroCurso = '';

    #[Url(as: 'situacao', except: '')]
    public string $filtroStatus = '';

    public function mount(): void
    {
        $this->authorize('viewAny', GradeCurricular::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['versao', 'ano_vigencia', 'status', 'created_at'];
    }

    protected function colunaPadrao(): string
    {
        return 'versao';
    }

    public function updatedFiltroCurso(): void
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

        $consulta = GradeCurricular::query()
            ->visivelPara($usuario)
            ->with('curso:id,nome,codigo,eixo_id')
            ->withCount(['disciplinas', 'turmas'])
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->whereHas('curso', fn (Builder $sub) => $sub
                    ->where('nome', 'like', $termo)
                    ->orWhere('codigo', 'like', $termo));
            })
            ->when($this->filtroCurso !== '', fn (Builder $q) => $q->where('curso_id', $this->filtroCurso))
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus));

        return view('grades.lista', [
            'grades' => $this->aplicarOrdenacao($consulta->orderByDesc('curso_id'))->paginate($this->porPagina),
            'cursos' => Curso::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
            'situacoes' => StatusGrade::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Grades curriculares',
            'subtitulo' => 'Fotos do curso: publicadas a partir das disciplinas cadastradas',
        ]);
    }
}
