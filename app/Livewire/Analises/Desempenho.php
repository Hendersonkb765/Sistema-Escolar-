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

    /** A questão aberta na grade, para ver o detalhe dela. */
    #[Url(as: 'questao', except: null)]
    public ?int $questaoAberta = null;

    /** O aluno escolhido na aba Alunos, para ver a questão com a resposta dele. */
    public ?int $alunoAberto = null;

    /** Como a tabela de apoio das questões é ordenada. */
    public string $ordemDasQuestoes = 'erro';

    /**
     * A aba aberta.
     *
     * Uma leitura por vez. A página inteira de uma vez eram três telas de
     * rolagem, e o atalho que apenas rolava não era navegação — a pessoa
     * continuava sem saber onde estava.
     */
    #[Url(as: 'aba', except: 'turma')]
    public string $aba = 'turma';

    /** @var array<string, string> */
    public const ABAS = [
        'turma' => 'A turma',
        'evolucao' => 'Evolução',
        'habilidades' => 'Habilidades',
        'questoes' => 'Questões',
        'alunos' => 'Alunos',
    ];

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
            ->orderByDesc('gerada_em')
            ->orderByDesc('id');
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
        $this->questaoAberta = null;
        $this->alunoAberto = null;

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

    public function abrir(string $aba): void
    {
        $this->aba = array_key_exists($aba, self::ABAS) ? $aba : 'turma';
    }

    /** Abre e fecha o detalhe de uma questão da grade. */
    public function verQuestao(int $id): void
    {
        $this->questaoAberta = $this->questaoAberta === $id ? null : $id;
        $this->alunoAberto = null;
    }

    /** Abre a questão com a resposta de um aluno, a partir da grade nominal. */
    public function verRespostaDoAluno(int $questaoId, int $alunoId): void
    {
        $mesma = $this->questaoAberta === $questaoId && $this->alunoAberto === $alunoId;

        $this->questaoAberta = $mesma ? null : $questaoId;
        $this->alunoAberto = $mesma ? null : $alunoId;
    }

    /**
     * A questão aberta, carregada do snapshot da prova.
     *
     * Passa pelo escopo: um id na URL não pode abrir questão de outro
     * Eixo nem questão de colega.
     */
    public function questaoCompleta(): ?ProvaQuestao
    {
        if ($this->questaoAberta === null) {
            return null;
        }

        $questao = ProvaQuestao::query()
            ->whereKey($this->questaoAberta)
            ->whereHas('prova', fn (Builder $q) => $q->visivelPara(auth()->user()))
            ->when(
                ! auth()->user()->ehGestao(),
                fn (Builder $q) => $q->where('professor_id', auth()->id())
            )
            ->first();

        return $questao;
    }

    public function ordenarQuestoes(string $por): void
    {
        $this->ordemDasQuestoes = in_array($por, ['erro', 'numero', 'habilidade'], true)
            ? $por
            : 'erro';
    }

    /**
     * As questões agrupadas por prova, na ordem em que o aluno as viu.
     *
     * A grade é por prova porque o número da questão só identifica dentro
     * de uma: com três provas no recorte havia três "questão 2" na mesma
     * lista, com valores diferentes e nada que as distinguisse.
     *
     * @param  ColecaoSimples<int, array<string, mixed>>  $porQuestao
     * @return array<int, array{prova: string, bimestre: mixed, questoes: array<int, array<string, mixed>>}>
     */
    public function questoesPorProva(ColecaoSimples $porQuestao): array
    {
        return $porQuestao
            ->groupBy('prova_id')
            ->map(fn (ColecaoSimples $questoes) => [
                'prova' => $questoes->first()['prova'],
                'bimestre' => $questoes->first()['bimestre'],
                'questoes' => $questoes->sortBy('numero')->values()->all(),
            ])
            ->values()
            ->all();
    }

    /** @param  ColecaoSimples<int, array<string, mixed>>  $porQuestao */
    public function questaoEmDetalhe(ColecaoSimples $porQuestao): ?array
    {
        return $this->questaoAberta === null
            ? null
            : $porQuestao->firstWhere('id', $this->questaoAberta);
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
        $leitor = auth()->user();

        $disciplinas = $provas->isEmpty()
            ? collect()
            : ProvaQuestao::query()
                ->whereIn('prova_id', $provas->modelKeys())
                ->with('disciplina:id,nome')
                ->get()
                ->pluck('disciplina.nome', 'disciplina.id')
                ->unique()
                ->sort();

        /*
         * Cada aba calcula só o que mostra.
         *
         * Antes a tela consolidava habilidade, questão, aluno e evolução
         * a cada render, para desenhar as quatro de uma vez. Com uma
         * leitura por vez, as outras três são trabalho jogado fora — e a
         * consolidação por habilidade percorre todas as respostas do
         * recorte.
         */
        $dados = match ($this->aba) {
            'evolucao' => $this->dadosDaEvolucao($analisar, $disciplina, $leitor),
            'habilidades' => $this->dadosDasHabilidades($analisar, $provas, $disciplina, $leitor),
            'questoes' => $this->dadosDasQuestoes($analisar, $provas, $disciplina, $leitor),
            'alunos' => $this->dadosDosAlunos($analisar, $disciplina, $leitor),
            default => [],
        };

        return view('analises.desempenho', [
            ...$dados,
            'provas' => $provas,
            'abas' => self::ABAS,
            'turmaDoRecorte' => $this->turmaDoRecorte(),
            'provasDisponiveis' => $this->consultaDeProvas()->get(),
            'anos' => $this->anosDisponiveis(),
            'limitacoes' => $this->limitacoesDaConsolidacao($provas),
            'turmas' => Turma::query()->visivelPara($leitor)->orderBy('nome')->pluck('nome', 'id'),
            'bimestres' => Bimestre::opcoes(),
            'disciplinas' => $disciplinas,
            // O painel fica fora do `match`: a aba "A turma" o mostra, e
            // o resumo do recorte usa os números dele em qualquer aba.
            'geral' => $analisar->visaoGeral($provas, $leitor, $disciplina),
            'criterios' => $analisar->criterios,
        ])->layout('components.layouts.app', [
            'titulo' => 'Análise de desempenho',
            'subtitulo' => 'Por habilidade avaliada, e não só pelo número da questão',
        ]);
    }

    /** @return array<string, mixed> */
    protected function dadosDaEvolucao(AnalisarDesempenhoAction $analisar, ?int $disciplina, $leitor): array
    {
        // A evolução ignora o filtro de prova: comparar bimestres exige
        // olhar além do recorte de uma avaliação só.
        $paraEvolucao = $this->consultaDeProvas()->get();

        return [
            'grafico' => GraficoDeEvolucao::de(
                array_values(Bimestre::opcoes()),
                $analisar->evolucaoPorBimestre($paraEvolucao, $leitor, $disciplina),
                // A linha de referência é o critério da escola: é contra
                // ela que o professor lê cada coluna, por posição.
                criterio: $analisar->criterios->notaEsperada,
            ),
        ];
    }

    /** @return array<string, mixed> */
    protected function dadosDasHabilidades(
        AnalisarDesempenhoAction $analisar,
        Collection $provas,
        ?int $disciplina,
        $leitor,
    ): array {
        return [
            'porHabilidade' => $analisar->porHabilidade($provas, $leitor, $disciplina),
            'alunosDaHabilidade' => $this->habilidadeAberta === ''
                ? collect()
                : $analisar->porAluno($provas, $leitor, $this->habilidadeAberta, $disciplina),
        ];
    }

    /** @return array<string, mixed> */
    protected function dadosDasQuestoes(
        AnalisarDesempenhoAction $analisar,
        Collection $provas,
        ?int $disciplina,
        $leitor,
    ): array {
        $porQuestao = $analisar->porQuestao($provas, $leitor, $disciplina);

        $completa = $this->questaoCompleta();

        return [
            'porQuestao' => $porQuestao,
            'questoesPorProva' => $this->questoesPorProva($porQuestao),
            'questaoEmDetalhe' => $this->questaoEmDetalhe($porQuestao),
            'questaoCompleta' => $completa,
            'respostasDaQuestao' => $completa === null
                ? collect()
                : $analisar->respostasDaQuestao($completa, $leitor),
            'questoesOrdenadas' => match ($this->ordemDasQuestoes) {
                'numero' => $porQuestao->sortBy([['prova', 'asc'], ['numero', 'asc']])->values(),
                'habilidade' => $porQuestao->sortBy([['habilidade', 'asc'], ['percentual', 'asc']])->values(),
                default => $porQuestao->sortBy('percentual')->values(),
            },
        ];
    }

    /** @return array<string, mixed> */
    protected function dadosDosAlunos(AnalisarDesempenhoAction $analisar, ?int $disciplina, $leitor): array
    {
        $prova = $this->provaDoRecorte();

        $completa = $this->questaoCompleta();

        return [
            'provaDoRecorte' => $prova,
            'acertoNominal' => $prova === null
                ? null
                : $analisar->acertoDeCadaAluno($prova, $leitor, $disciplina),
            'questaoCompleta' => $completa,
            'alunoAberto' => $this->alunoAberto,
        ];
    }
}
