<?php

namespace App\Livewire\Solicitacoes;

use App\Enums\Bimestre;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Livewire\Attributes\LembrarFiltro;
use App\Livewire\Concerns\ComTabela;
use App\Livewire\Concerns\ConfirmaExclusao;
use App\Livewire\Concerns\Notifica;
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
    use ConfirmaExclusao;
    use Notifica;

    #[Url(as: 'curso', except: '')]
    #[LembrarFiltro]
    public string $filtroCurso = '';

    #[Url(as: 'situacao', except: '')]
    #[LembrarFiltro]
    public string $filtroStatus = '';

    #[Url(as: 'prazo', except: '')]
    #[LembrarFiltro]
    public string $filtroPrazo = '';

    #[Url(as: 'bimestre', except: '')]
    #[LembrarFiltro]
    public string $filtroBimestre = '';

    public function mount(): void
    {
        $this->authorize('viewAny', SolicitacaoProva::class);
    }

    protected function colunasOrdenaveis(): array
    {
        return ['prazo', 'status', 'created_at', 'titulo'];
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

    public function updatedFiltroBimestre(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $usuario = auth()->user();

        // Nulo para a gestão, que olha a solicitação inteira.
        $comoProfessor = $usuario->ehProfessor() ? $usuario : null;

        $consulta = SolicitacaoProva::query()
            ->visivelPara($usuario)
            ->with([
                'turma:id,nome,periodo,periodo_letivo,curso_id',
                'turma.curso:id,nome,eixo_id',
                'partes.disciplina:id,nome,curso_id',
                'partes.professor:id,nome',
            ])
            ->withCount([
                'questoes',
                'partes',
                // Destaque para o que voltou e espera o professor.
                'questoes as questoes_devolvidas_count' => fn (Builder $q) => $q
                    ->where('status', StatusQuestao::Rejeitada),
            ])
            ->when($this->busca !== '', function (Builder $q) {
                $termo = '%'.str_replace('%', '\%', $this->busca).'%';
                $q->where(fn (Builder $sub) => $sub
                    ->where('titulo', 'like', $termo)
                    ->orWhereHas('turma', fn (Builder $t) => $t->where('nome', 'like', $termo))
                    ->orWhereHas('partes.disciplina', fn (Builder $d) => $d->where('nome', 'like', $termo))
                    ->orWhereHas('partes.professor', fn (Builder $p) => $p->where('nome', 'like', $termo)));
            })
            ->when($this->filtroCurso !== '', fn (Builder $q) => $q->where('curso_id', $this->filtroCurso))
            ->when($this->filtroStatus !== '', fn (Builder $q) => $q->where('status', $this->filtroStatus))
            ->when($this->filtroBimestre !== '', fn (Builder $q) => $q->where('bimestre', $this->filtroBimestre))
            /*
             * Pelos escopos do model, compartilhados com o painel: o
             * número de lá aponta para esta lista, e dois recortes
             * escritos à mão divergem no dia em que um deles muda.
             *
             * Para o professor, só a parte dele conta — com a regra
             * genérica, ele via "Atrasada" por causa do colega.
             */
            ->when($this->filtroPrazo === 'atrasadas', fn (Builder $q) => $q->atrasadas($comoProfessor))
            ->when($this->filtroPrazo === 'pendentes', fn (Builder $q) => $q->comPartePendente($comoProfessor))
            ->when($this->filtroPrazo === 'devolvidas', fn (Builder $q) => $q
                ->whereHas('questoes', fn (Builder $sub) => $sub
                    ->where('status', StatusQuestao::Rejeitada)));

        return view('solicitacoes.lista', [
            'solicitacoes' => $this->aplicarOrdenacao($consulta)->paginate($this->porPagina),
            'cursos' => Curso::query()->visivelPara($usuario)->orderBy('nome')->pluck('nome', 'id'),
            'situacoes' => StatusSolicitacao::opcoes(),
            'bimestres' => Bimestre::opcoes(),
            'ehGestao' => $usuario->ehGestao(),
        ])->layout('components.layouts.app', [
            'titulo' => $usuario->ehGestao() ? 'Solicitações de questões' : 'Minhas solicitações',
            'subtitulo' => $usuario->ehGestao()
                ? 'Prazo vencido não bloqueia o envio — apenas marca a solicitação como atrasada'
                : 'Você pode enviar mesmo depois do prazo; o atraso fica registrado',
        ]);
    }
}
