<?php

namespace App\Livewire\Questoes;

use App\Enums\StatusQuestao;
use App\Livewire\Concerns\ComTabela;
use App\Models\Curso;
use App\Models\Questao;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fila de análise para a coordenação e painel de acompanhamento para o
 * professor — o mesmo `visivelPara` decide o que cada um enxerga.
 */
class ListaQuestoes extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'situacao', except: '')]
    public string $filtroStatus = '';

    #[Url(as: 'curso', except: '')]
    public string $filtroCurso = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Questao::class);

        // A coordenação chega aqui para decidir: o padrão é a fila.
        if ($this->filtroStatus === '' && auth()->user()->ehGestao()) {
            $this->filtroStatus = StatusQuestao::Enviada->value;
        }
    }

    protected function colunasOrdenaveis(): array
    {
        return ['status', 'enviada_em', 'versao'];
    }

    protected function colunaPadrao(): string
    {
        return 'enviada_em';
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroCurso(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = Questao::query()
            ->visivelPara($usuario)
            ->with([
                'item',
                'disciplina:id,nome,curso_id',
                'professor:id,nome',
                'solicitacao:id,turma_id,curso_id,prazo,quantidade_alternativas',
                'solicitacao.turma:id,nome,curso_id',
                'solicitacao.curso:id,nome,eixo_id',
            ])
            ->withCount('feedbacks')
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('enunciado', 'like', $termo)
                    ->orWhereHas('disciplina', fn (Builder $d) => $d->where('nome', 'like', $termo))
                    ->orWhereHas('professor', fn (Builder $p) => $p->where('nome', 'like', $termo)));
            })
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus))
            ->when($this->filtroCurso !== '', fn (Builder $q) => $q
                ->whereHas('solicitacao', fn (Builder $s) => $s->where('curso_id', $this->filtroCurso)));

        return view('questoes.lista', [
            'questoes' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'situacoes' => StatusQuestao::opcoes(),
            'cursos' => Curso::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
            'ehGestao' => $usuario->ehGestao(),
            'aguardando' => Questao::query()
                ->visivelPara($usuario)
                ->whereIn('status', [StatusQuestao::Enviada->value, StatusQuestao::EmAnalise->value])
                ->count(),
        ])->layout('components.layouts.app', [
            'titulo' => $usuario->ehGestao() ? 'Análise de questões' : 'Minhas questões',
            'subtitulo' => $usuario->ehGestao()
                ? 'Aprove ou devolva cada questão enviada pelos professores'
                : 'Acompanhe o que foi aprovado e o que voltou para correção',
        ]);
    }
}
