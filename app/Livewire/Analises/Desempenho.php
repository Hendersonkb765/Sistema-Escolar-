<?php

namespace App\Livewire\Analises;

use App\Actions\Resultado\AnalisarDesempenhoAction;
use App\Enums\Bimestre;
use App\Models\Prova;
use App\Models\ProvaQuestao;
use App\Models\ResultadoAluno;
use App\Models\Turma;
use App\Support\GraficoDeEvolucao;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection as ColecaoSimples;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Análise de desempenho: onde a turma teve dificuldade.
 *
 * Lê por habilidade e por questão. A primeira é a que permite intervir —
 * "interpretar estruturas de repetição" diz o que ensinar de novo, e
 * "questão 7" não diz nada.
 *
 * Todos os recortes são por bimestre, porque é assim que o ano letivo é
 * conduzido.
 */
class Desempenho extends Component
{
    use AuthorizesRequests;

    #[Url(as: 'ano', except: '')]
    public string $filtroAno = '';

    #[Url(as: 'turma', except: '')]
    public string $filtroTurma = '';

    #[Url(as: 'bimestre', except: '')]
    public string $filtroBimestre = '';

    /**
     * As provas escolhidas, por id. Vazio significa todas as do recorte.
     *
     * Várias, e não uma: consolidar duas provas do mesmo bimestre é o
     * caso comum de quem quer olhar a habilidade com evidência bastante.
     *
     * @var array<int, string>
     */
    #[Url(as: 'provas', except: [])]
    public array $provasEscolhidas = [];

    #[Url(as: 'disciplina', except: '')]
    public string $filtroDisciplina = '';

    /** A habilidade aberta para ver aluno a aluno. */
    #[Url(as: 'habilidade', except: '')]
    public string $habilidadeAberta = '';

    public function mount(): void
    {
        $this->authorize('viewAny', ResultadoAluno::class);
    }

    /**
     * As provas que entram na análise: só as que já têm resultado
     * importado, dentro dos filtros escolhidos.
     *
     * @return Collection<int, Prova>
     */
    public function provasAnalisadas(): Collection
    {
        return $this->consultaDeProvas()
            ->when($this->provasEscolhidas !== [], fn (Builder $q) => $q->whereKey($this->provasEscolhidas))
            ->get();
    }

    protected function consultaDeProvas(): Builder
    {
        return Prova::query()
            ->visivelPara(auth()->user())
            ->whereHas('resultados')
            ->with(['turma:id,nome,curso_id', 'turma.curso:id,nome,eixo_id'])
            ->when(
                $this->filtroAno !== '',
                fn (Builder $q) => $q->whereHas('turma', fn ($t) => $t->where('periodo_letivo', $this->filtroAno))
            )
            ->when($this->filtroTurma !== '', fn (Builder $q) => $q->where('turma_id', $this->filtroTurma))
            ->when($this->filtroBimestre !== '', fn (Builder $q) => $q->where('bimestre', $this->filtroBimestre))
            ->orderByDesc('gerada_em');
    }

    /*
     * Mudar o recorte não pode deixar uma escolha apontando para fora
     * dele: a tela ficaria vazia sem dizer por quê. O que continua
     * valendo fica marcado; o resto volta a "todas do recorte".
     */
    public function updatedFiltroAno(): void
    {
        $this->sincronizarEscolhas();
    }

    public function updatedFiltroTurma(): void
    {
        $this->sincronizarEscolhas();
    }

    public function updatedFiltroBimestre(): void
    {
        $this->sincronizarEscolhas();
    }

    protected function sincronizarEscolhas(): void
    {
        $this->habilidadeAberta = '';

        if ($this->provasEscolhidas === []) {
            return;
        }

        $noRecorte = $this->consultaDeProvas()->pluck('id')->map(fn ($id) => (string) $id)->all();

        $this->provasEscolhidas = array_values(array_intersect($this->provasEscolhidas, $noRecorte));
    }

    /**
     * Os anos letivos que têm prova com resultado.
     *
     * `Support\Collection`, e não a do Eloquent: `pluck` sobre uma
     * consulta devolve valores, não models.
     *
     * @return ColecaoSimples<int, string>
     */
    public function anosDisponiveis(): ColecaoSimples
    {
        return Turma::query()
            ->visivelPara(auth()->user())
            ->whereHas('provas', fn (Builder $q) => $q->whereHas('resultados'))
            ->orderByDesc('periodo_letivo')
            ->pluck('periodo_letivo')
            ->unique()
            ->values();
    }

    /**
     * O que a consolidação junta, e o que isso tem de limitação.
     *
     * Duas provas com quantidades de questões ou pesos diferentes somam
     * pontos que não pesam igual. O número continua certo; o que muda é
     * o que ele permite afirmar, e isso tem de estar escrito ao lado
     * dele.
     *
     * @param  Collection<int, Prova>  $provas
     * @return array<int, string>
     */
    public function limitacoesDaConsolidacao(Collection $provas): array
    {
        if ($provas->count() < 2) {
            return [];
        }

        $avisos = [];

        if ($provas->pluck('bimestre')->unique()->count() > 1) {
            $avisos[] = 'O recorte junta bimestres diferentes, que avaliaram conteúdos diferentes: '
                .'o consolidado soma provas que não medem a mesma coisa.';
        }

        if ($provas->pluck('turma_id')->unique()->count() > 1) {
            $avisos[] = 'O recorte junta turmas diferentes. As médias saem do conjunto, '
                .'e não de cada turma.';
        }

        $questoes = ProvaQuestao::query()
            ->whereIn('prova_id', $provas->modelKeys())
            ->selectRaw('prova_id, count(*) as total, sum(peso) as pontos')
            ->groupBy('prova_id')
            ->get();

        if ($questoes->pluck('total')->unique()->count() > 1) {
            $avisos[] = 'As provas do recorte têm quantidades diferentes de questões ('
                .$questoes->pluck('total')->sort()->join(', ').'): '
                .'quem fez a mais longa pesa mais no consolidado.';
        }

        return $avisos;
    }

    public function verAlunos(string $habilidade): void
    {
        $this->habilidadeAberta = $this->habilidadeAberta === $habilidade ? '' : $habilidade;
    }

    /**
     * A prova do recorte, quando ele aponta para uma só.
     *
     * O acerto nominal depende disso: o número da questão só quer dizer
     * alguma coisa dentro da prova em que ela saiu.
     */
    public function provaDoRecorte(): ?Prova
    {
        $provas = $this->provasAnalisadas();

        return $provas->count() === 1 ? $provas->first() : null;
    }

    /** A turma do recorte, quando ele aponta para uma só. */
    public function turmaDoRecorte(): ?Turma
    {
        if ($this->filtroTurma !== '') {
            return Turma::query()->visivelPara(auth()->user())->find($this->filtroTurma);
        }

        $turmas = $this->provasAnalisadas()->pluck('turma_id')->unique();

        return $turmas->count() === 1
            ? Turma::query()->visivelPara(auth()->user())->find($turmas->first())
            : null;
    }

    public function render(AnalisarDesempenhoAction $analisar): View
    {
        $provas = $this->provasAnalisadas();
        $disciplina = $this->filtroDisciplina === '' ? null : (int) $this->filtroDisciplina;

        $disciplinas = $provas->isEmpty()
            ? collect()
            : ProvaQuestao::query()
                ->whereIn('prova_id', $provas->modelKeys())
                ->with('disciplina:id,nome')
                ->get()
                ->pluck('disciplina.nome', 'disciplina.id')
                ->unique()
                ->sort();

        // A evolução ignora o filtro de prova: comparar bimestres exige
        // olhar além do recorte de uma avaliação só.
        $paraEvolucao = $this->consultaDeProvas()->get();

        return view('analises.desempenho', [
            'provas' => $provas,
            'grafico' => GraficoDeEvolucao::de(
                array_values(Bimestre::opcoes()),
                $analisar->evolucaoPorBimestre($paraEvolucao, auth()->user(), $disciplina),
            ),
            'alunosDaHabilidade' => $this->habilidadeAberta === ''
                ? collect()
                : $analisar->porAluno($provas, auth()->user(), $this->habilidadeAberta, $disciplina),
            'turmaDoRecorte' => $this->turmaDoRecorte(),
            'provasDisponiveis' => $this->consultaDeProvas()->get(),
            'anos' => $this->anosDisponiveis(),
            'limitacoes' => $this->limitacoesDaConsolidacao($provas),
            'turmas' => Turma::query()->visivelPara(auth()->user())->orderBy('nome')->pluck('nome', 'id'),
            'bimestres' => Bimestre::opcoes(),
            'disciplinas' => $disciplinas,
            'geral' => $analisar->visaoGeral($provas, auth()->user(), $disciplina),
            'porHabilidade' => $analisar->porHabilidade($provas, auth()->user(), $disciplina),
            'porQuestao' => $analisar->porQuestao($provas, auth()->user(), $disciplina),
            'provaDoRecorte' => $provaUnica = $this->provaDoRecorte(),
            'acertoNominal' => $provaUnica === null
                ? null
                : $analisar->acertoDeCadaAluno($provaUnica, auth()->user(), $disciplina),
            'criterios' => $analisar->criterios,
        ])->layout('components.layouts.app', [
            'titulo' => 'Análise de desempenho',
            'subtitulo' => 'Por habilidade avaliada, e não só pelo número da questão',
        ]);
    }
}
