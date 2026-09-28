<?php

namespace App\Livewire\Resultados;

use App\Enums\Bimestre;
use App\Models\Prova;
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

        /*
         * A tela abre já mostrando alguma coisa.
         *
         * Só quando ninguém escolheu: uma prova no endereço é decisão de
         * quem montou o link, mesmo que ela ainda não tenha resultado —
         * aí a tela diz isso, que é a informação certa.
         */
        if ($this->prova_id === '') {
            $this->prova_id = (string) ($this->provasDisponiveis()->first()?->id ?? '');
        }

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
     * A escolha anterior pode ter ficado de fora, e uma escolha que
     * aponta para fora da lista deixa a tela em branco sem dizer por quê.
     * Nesse caso vale a primeira do novo recorte.
     */
    protected function reescolherProva(): void
    {
        $disponiveis = $this->provasDisponiveis();

        if ($this->prova_id === '' || ! $disponiveis->contains('id', (int) $this->prova_id)) {
            $this->prova_id = (string) ($disponiveis->first()?->id ?? '');
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

    public function provaSelecionada(): ?Prova
    {
        if ($this->prova_id === '') {
            return null;
        }

        return Prova::query()
            ->visivelPara(auth()->user())
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->find((int) $this->prova_id);
    }

    /*
    |--------------------------------------------------------------------------
    | Quais disciplinas aparecem
    |--------------------------------------------------------------------------
    */

    /**
     * Todas as disciplinas da prova escolhida.
     *
     * @return ColecaoSimples<int, string> id => nome
     */
    public function disciplinasDaProva(): ColecaoSimples
    {
        $prova = $this->provaSelecionada();

        if ($prova === null) {
            return collect();
        }

        return $prova->questoes()
            ->with('disciplina:id,nome')
            ->get()
            ->pluck('disciplina.nome', 'disciplina.id')
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
        $prova = $this->provaSelecionada();

        $disciplinasDaProva = $this->disciplinasDaProva();

        $mostradas = $disciplinasDaProva->filter(
            fn (string $nome, int $id) => in_array((string) $id, $this->disciplinasEscolhidas, true)
        );

        $resultados = $prova === null
            ? collect()
            : ResultadoAluno::query()
                ->where('prova_id', $prova->getKey())
                ->visivelPara(auth()->user())
                ->with([
                    'aluno:id,nome,ra,turma_id',
                    'notas:id,resultado_aluno_id,disciplina_id,nota,soma_pesos_acertos,soma_pesos_total',
                    'notas.disciplina:id,nome',
                ])
                ->get()
                ->sortBy(fn (ResultadoAluno $resultado) => $resultado->aluno->nome)
                ->values();

        return view('resultados.lista', [
            'turmas' => $this->turmasDisponiveis(),
            'provas' => $this->provasDisponiveis(),
            'bimestres' => Bimestre::opcoes(),
            'prova' => $prova,
            'disciplinasDaProva' => $disciplinasDaProva,
            'disciplinas' => $mostradas,
            'minhasDisciplinas' => $this->minhasDisciplinas(),
            'resultados' => $resultados,
            'bimestreEscolhido' => $this->filtroBimestre === ''
                ? null
                : Bimestre::tryFrom((int) $this->filtroBimestre)?->rotulo(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Resultados e notas',
            'subtitulo' => 'A nota de cada aluno em cada disciplina, para conferir e lançar',
        ]);
    }
}
