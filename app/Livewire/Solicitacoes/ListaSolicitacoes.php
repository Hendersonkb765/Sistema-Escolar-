<?php

namespace App\Livewire\Solicitacoes;

use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Livewire\Concerns\ComTabela;
use App\Models\Curso;
use App\Models\SolicitacaoProva;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Mesma listagem para os dois lados: a gestão vê o que abriu dentro do
 * seu escopo, o professor vê apenas o que lhe foi pedido — o filtro vem
 * do `visivelPara`, não de um `@if` na view.
 */
class ListaSolicitacoes extends Component
{
    use AuthorizesRequests;
    use ComTabela;

    #[Url(as: 'curso', except: '')]
    public string $filtroCurso = '';

    #[Url(as: 'situacao', except: '')]
    public string $filtroStatus = '';

    #[Url(as: 'prazo', except: '')]
    public string $filtroPrazo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', SolicitacaoProva::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['prazo', 'status', 'created_at', 'quantidade_questoes'];
    }

    protected function colunaPadrao(): string
    {
        return 'prazo';
    }

    public function updatedFiltroCurso(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroStatus(): void
    {
        $this->resetPage();
    }

    public function updatedFiltroPrazo(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        $consulta = SolicitacaoProva::query()
            ->visivelPara($usuario)
            ->with([
                'turma:id,nome,periodo,curso_id',
                'turma.curso:id,nome,eixo_id',
                'disciplina:id,nome,curso_id',
                'professor:id,nome',
            ])
            ->withCount([
                'questoes',
                // Destaque para o que voltou e espera o professor.
                'questoes as questoes_devolvidas_count' => fn (Builder $q) => $q
                    ->where('status', StatusQuestao::Rejeitada),
            ])
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->whereHas('disciplina', fn (Builder $d) => $d->where('nome', 'like', $termo))
                    ->orWhereHas('turma', fn (Builder $t) => $t->where('nome', 'like', $termo))
                    ->orWhereHas('professor', fn (Builder $p) => $p->where('nome', 'like', $termo)));
            })
            ->when($this->filtroCurso !== '', fn (Builder $q) => $q->where('curso_id', $this->filtroCurso))
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus))
            // "Atrasada" é estado de prazo, não de fluxo: pendente com
            // prazo vencido, ou enviada depois do combinado.
            ->when($this->filtroPrazo === 'atrasadas', fn (Builder $q) => $q
                ->where(fn (Builder $sub) => $sub
                    ->where('enviada_em_atraso', true)
                    ->orWhere(fn (Builder $pendente) => $pendente
                        ->whereNull('enviada_em')
                        ->where('prazo', '<', now())
                        ->whereIn('status', [
                            StatusSolicitacao::Aberta->value,
                            StatusSolicitacao::Enviada->value,
                            StatusSolicitacao::EmAnalise->value,
                        ]))))
            ->when($this->filtroPrazo === 'pendentes', fn (Builder $q) => $q
                ->whereNull('enviada_em')
                ->where('status', StatusSolicitacao::Aberta))
            ->when($this->filtroPrazo === 'devolvidas', fn (Builder $q) => $q
                ->whereHas('questoes', fn (Builder $sub) => $sub
                    ->where('status', StatusQuestao::Rejeitada)));

        return view('solicitacoes.lista', [
            'solicitacoes' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'cursos' => Curso::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
            'situacoes' => StatusSolicitacao::opcoes(),
            'ehGestao' => $usuario->ehGestao(),
        ])->layout('components.layouts.app', [
            'titulo' => $usuario->ehGestao() ? 'Solicitações de questões' : 'Minhas solicitações',
            'subtitulo' => $usuario->ehGestao()
                ? 'Prazo vencido não bloqueia o envio — apenas marca a solicitação como atrasada'
                : 'Você pode enviar mesmo depois do prazo; o atraso fica registrado',
        ]);
    }
}
