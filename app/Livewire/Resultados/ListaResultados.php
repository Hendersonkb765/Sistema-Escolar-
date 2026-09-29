<?php

namespace App\Livewire\Resultados;

use App\Enums\Bimestre;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection as ColecaoSimples;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * As notas dos alunos, disciplina a disciplina.
 *
 * A tela serve a uma tarefa concreta: olhar as notas e lançá-las noutro
 * sistema. Por isso mostra nota, e só nota — o acerto questão a questão
 * mora na Análise de desempenho, junto das outras leituras por questão.
 *
 * Cada disciplina tem a sua nota de 0 a 10, calculada contra a soma dos
 * pesos dela — somar questões de disciplinas diferentes numa nota só
 * apagaria o que a escola quer ver.
 */
class ListaResultados extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'turma', except: '')]
    public string $filtroTurma = '';

    #[Url(as: 'bimestre', except: '')]
    public string $filtroBimestre = '';

    #[Url(as: 'prova', except: '')]
    public string $prova_id = '';

    /**
     * As disciplinas que a tabela mostra, por id.
     *
     * Vai para o endereço: quem confere sempre as mesmas três guarda o
     * link e abre nelas.
     *
     * @var array<int, string>
     */
    #[Url(as: 'disciplinas', except: [])]
    public array $disciplinasEscolhidas = [];

    public function mount(): void
    {
        $this->authorize('viewAny', ResultadoAluno::class);

        $this->sincronizarDisciplinas();
    }

    /*
    |--------------------------------------------------------------------------
    | Recorte
    |--------------------------------------------------------------------------
    */

    public function updatedFiltroTurma(): void
    {
        $this->reescolherProva();
    }

    public function updatedFiltroBimestre(): void
    {
        $this->reescolherProva();
    }

    public function updatedProvaId(): void
    {
        $this->sincronizarDisciplinas();
    }

    /**
     * Mudar o recorte muda as opções do select de prova.
     *
     * Uma escolha que aponta para fora da lista deixaria a tela em branco
     * sem dizer por quê; nesse caso ela volta a "todas do recorte", que é
     * o que a pessoa acabou de pedir ao trocar o filtro.
     */
    protected function reescolherProva(): void
    {
        if ($this->prova_id !== '' && ! $this->provasDisponiveis()->contains('id', (int) $this->prova_id)) {
            $this->prova_id = '';
        }

        $this->sincronizarDisciplinas();
    }

    /** @return Collection<int, Turma> */
    public function turmasDisponiveis(): Collection
    {
        return Turma::query()
            ->visivelPara(auth()->user())
            ->whereHas('provas', fn (Builder $q) => $q->whereHas('resultados'))
            ->with('curso:id,nome')
            ->orderByDesc('periodo_letivo')
            ->orderBy('nome')
            ->get(['id', 'nome', 'periodo_letivo', 'curso_id']);
    }

    /** @return Collection<int, Prova> */
    public function provasDisponiveis(): Collection
    {
        return Prova::query()
            ->visivelPara(auth()->user())
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->whereHas('resultados')
            ->when($this->filtroTurma !== '', fn (Builder $q) => $q->where('turma_id', $this->filtroTurma))
            ->when($this->filtroBimestre !== '', fn (Builder $q) => $q->where('bimestre', $this->filtroBimestre))
            ->orderByDesc('gerada_em')
            ->get();
    }

    /**
     * As provas que a tela desenha, cada uma na sua seção.
     *
     * Sem prova escolhida, todas as do recorte: ao filtrar por turma, o
     * que se quer é ver as notas dela prova a prova, e não escolher uma
     * de cada vez. Com uma escolhida, só ela.
     *
     * @return Collection<int, Prova>
     */
    public function provasMostradas(): Collection
    {
        if ($this->prova_id === '') {
            return $this->provasDisponiveis();
        }

        $escolhida = $this->provasDisponiveis()->firstWhere('id', (int) $this->prova_id);

        /*
         * Uma prova pedida por endereço entra mesmo sem resultado: ela
         * não está no select, que só lista as que têm, mas quem montou o
         * link merece saber que a prova existe e ainda não foi importada
         * — e não "nenhuma prova com resultado", que sugere outra coisa.
         */
        $escolhida ??= Prova::query()
            ->visivelPara(auth()->user())
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->find((int) $this->prova_id);

        return $escolhida === null ? new Collection : new Collection([$escolhida]);
    }

    /*
    |--------------------------------------------------------------------------
    | Quais disciplinas aparecem
    |--------------------------------------------------------------------------
    */

    /**
     * As disciplinas do recorte inteiro, e não de uma prova.
     *
     * Com várias provas na tela, a lista de caixas precisa ser a união
     * delas: uma caixa que some ao rolar a página não é filtro.
     *
     * @return ColecaoSimples<int, string> id => nome
     */
    public function disciplinasDaProva(): ColecaoSimples
    {
        $provas = $this->provasMostradas();

        if ($provas->isEmpty()) {
            return collect();
        }

        return ProvaQuestao::query()
            ->whereIn('prova_id', $provas->modelKeys())
            ->with('disciplina:id,nome')
            ->get()
            ->pluck('disciplina.nome', 'disciplina.id')
            ->unique()
            ->sort();
    }

    /** As disciplinas em que o usuário leciona, dentro desta prova. */
    public function minhasDisciplinas(): array
    {
        return array_values(array_intersect(
            $this->idsDisponiveis(),
            array_map('strval', auth()->user()->disciplinaIds()),
        ));
    }

    public function mostrarTodasAsDisciplinas(): void
    {
        $this->disciplinasEscolhidas = $this->idsDisponiveis();
    }

    public function mostrarSoAsMinhas(): void
    {
        $minhas = $this->minhasDisciplinas();

        $this->disciplinasEscolhidas = $minhas === [] ? $this->idsDisponiveis() : $minhas;
    }

    /**
     * Mantém a escolha viva quando a prova muda.
     *
     * Trocar de prova troca o conjunto de disciplinas. O que continua
     * existindo fica marcado; o que não sobrou nenhuma volta ao padrão,
     * porque uma tabela sem coluna nenhuma não é filtro, é tela quebrada.
     */
    protected function sincronizarDisciplinas(): void
    {
        $disponiveis = $this->idsDisponiveis();

        $mantidas = array_values(array_intersect($this->disciplinasEscolhidas, $disponiveis));

        $this->disciplinasEscolhidas = $mantidas !== [] ? $mantidas : $this->padraoDeDisciplinas();
    }

    /**
     * Com o que a tela abre.
     *
     * O professor abre vendo só as disciplinas dele: é o que ele veio
     * fazer, e procurar as três dele entre seis é o trabalho que esta
     * tela existe para poupar. As outras ficam a um clique — a nota da
     * turma não é segredo dele, só não é o que ele procurava.
     *
     * Quem é da gestão abre com todas, que é o que a coordenação olha.
     *
     * @return array<int, string>
     */
    protected function padraoDeDisciplinas(): array
    {
        $disponiveis = $this->idsDisponiveis();

        if (auth()->user()->ehGestao()) {
            return $disponiveis;
        }

        $minhas = $this->minhasDisciplinas();

        return $minhas === [] ? $disponiveis : $minhas;
    }

    /** @return array<int, string> */
    protected function idsDisponiveis(): array
    {
        return $this->disciplinasDaProva()->keys()->map(fn ($id) => (string) $id)->all();
    }

    /*
    |--------------------------------------------------------------------------
    | Render
    |--------------------------------------------------------------------------
    */

    public function render(): View
    {
        $disciplinasDoRecorte = $this->disciplinasDaProva();

        $mostradas = $disciplinasDoRecorte->filter(
            fn (string $nome, int $id) => in_array((string) $id, $this->disciplinasEscolhidas, true)
        );

        return view('resultados.lista', [
            'turmas' => $this->turmasDisponiveis(),
            'provas' => $this->provasDisponiveis(),
            'bimestres' => Bimestre::opcoes(),
            'secoes' => $this->secoes($mostradas),
            'disciplinasDaProva' => $disciplinasDoRecorte,
            'disciplinas' => $mostradas,
            'minhasDisciplinas' => $this->minhasDisciplinas(),
            'bimestreEscolhido' => $this->filtroBimestre === ''
                ? null
                : Bimestre::tryFrom((int) $this->filtroBimestre)?->rotulo(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Resultados e notas',
            'subtitulo' => 'A nota de cada aluno em cada disciplina, para conferir e lançar',
        ]);
    }

    /**
     * Uma seção por prova: a prova e as notas dela.
     *
     * As disciplinas mostradas são as do recorte, mas cada prova só tem
     * as suas — a seção da prova de Matemática não ganha uma coluna de
     * Inglês vazia por causa de outra prova da lista.
     *
     * @param  ColecaoSimples<int, string>  $mostradas
     * @return array<int, array{prova: Prova, disciplinas: ColecaoSimples<int, string>, resultados: ColecaoSimples<int, ResultadoAluno>}>
     */
    protected function secoes(ColecaoSimples $mostradas): array
    {
        $provas = $this->provasMostradas();

        if ($provas->isEmpty() || $mostradas->isEmpty()) {
            return [];
        }

        $porProva = ProvaQuestao::query()
            ->whereIn('prova_id', $provas->modelKeys())
            ->whereIn('disciplina_id', $mostradas->keys()->all())
            ->get(['id', 'prova_id', 'disciplina_id'])
            ->groupBy('prova_id');

        $resultados = ResultadoAluno::query()
            ->whereIn('prova_id', $provas->modelKeys())
            ->visivelPara(auth()->user())
            ->with([
                'aluno:id,nome,ra,turma_id',
                'notas:id,resultado_aluno_id,disciplina_id,nota,soma_pesos_acertos,soma_pesos_total',
                'notas.disciplina:id,nome',
            ])
            ->get()
            ->sortBy(fn (ResultadoAluno $resultado) => $resultado->aluno->nome)
            ->groupBy('prova_id');

        $secoes = [];

        foreach ($provas as $prova) {
            $daProva = ($porProva[$prova->getKey()] ?? collect())->pluck('disciplina_id')->unique();

            $secoes[] = [
                'prova' => $prova,
                'disciplinas' => $mostradas->filter(fn ($nome, $id) => $daProva->contains($id)),
                'resultados' => ($resultados[$prova->getKey()] ?? collect())->values(),
            ];
        }

        return $secoes;
    }
}
