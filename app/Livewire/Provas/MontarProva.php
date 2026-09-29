<?php

namespace App\Livewire\Provas;

use App\Actions\Prova\MontarProvaAction;
use App\Enums\Bimestre;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\Turma;
use App\Support\LayoutDaFolha;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Montagem da prova: escolhe-se a turma e o sistema reúne as questões já
 * aprovadas, agrupadas por disciplina, numerando-as em sequência.
 */
class MontarProva extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public ?int $turma_id = null;

    public ?int $modelo_prova_id = null;

    public string $titulo = '';

    public int $bimestre = 1;

    public string $data_aplicacao = '';

    public int $colunas = 2;

    public bool $mostrar_pesos = false;

    public string $instrucoes = '';

    /** Cabeçalho próprio desta prova; em branco vale o do modelo. */
    public string $instituicao = '';

    public string $nome_avaliacao = '';

    /** Vazio usa o tamanho do modelo. */
    public string $tamanho_instituicao = '';

    /** Questões marcadas para entrar. @var array<int, int> */
    public array $questoesEscolhidas = [];

    public function mount(): void
    {
        $this->authorize('create', Prova::class);

        $this->turma_id = (int) request()->query('turma') ?: null;
        $this->data_aplicacao = now()->addWeek()->format('Y-m-d');

        $this->sincronizarEscolhas();
        $this->sugerirBimestre();
        $this->sugerirModelo();
    }

    public function updatedTurmaId(): void
    {
        $this->sincronizarEscolhas();
        $this->sugerirBimestre();
        $this->sugerirModelo();
    }

    /** Por padrão, tudo o que está aprovado entra na prova. */
    protected function sincronizarEscolhas(): void
    {
        $this->questoesEscolhidas = $this->questoesAprovadas()->pluck('id')->all();

        if ($this->titulo === '' && $this->turmaSelecionada() !== null) {
            $this->titulo = 'Avaliação — '.$this->turmaSelecionada()->nome;
        }
    }

    /** O bimestre vem das solicitações que produziram as questões. */
    protected function sugerirBimestre(): void
    {
        $aprovadas = $this->questoesAprovadas();

        if ($aprovadas->isNotEmpty()) {
            $this->bimestre = app(MontarProvaAction::class)->bimestreDasQuestoes($aprovadas)->value;
        }
    }

    protected function sugerirModelo(): void
    {
        $this->modelo_prova_id ??= $this->modelosDisponiveis()->first()?->getKey();
    }

    /**
     * O navegador devolve o valor de um checkbox como texto. Sem
     * normalizar, a comparação estrita com os ids do banco nunca casa e
     * o "desmarcar todas" acaba marcando tudo de novo.
     *
     * @return array<int, int>
     */
    protected function escolhidas(): array
    {
        return array_values(array_unique(array_map('intval', $this->questoesEscolhidas)));
    }

    public function updatedQuestoesEscolhidas(): void
    {
        $this->questoesEscolhidas = $this->escolhidas();
    }

    public function alternarDisciplina(int $disciplinaId): void
    {
        $daDisciplina = $this->questoesAprovadas()
            ->where('disciplina_id', $disciplinaId)
            ->pluck('id')
            ->map('intval')
            ->all();

        $escolhidas = $this->escolhidas();

        $todasMarcadas = collect($daDisciplina)
            ->every(fn (int $id) => in_array($id, $escolhidas, true));

        $this->questoesEscolhidas = $todasMarcadas
            ? array_values(array_diff($escolhidas, $daDisciplina))
            : array_values(array_unique([...$escolhidas, ...$daDisciplina]));
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'turma_id' => ['required', Rule::in($this->turmasDisponiveis()->pluck('id')->all())],
            'modelo_prova_id' => ['required', Rule::in($this->modelosDisponiveis()->pluck('id')->all())],
            'titulo' => ['required', 'string', 'max:255'],
            'bimestre' => ['required', Rule::in(Bimestre::valores())],
            'data_aplicacao' => ['nullable', 'date'],
            'colunas' => ['required', 'integer', Rule::in([1, 2])],
            'instrucoes' => ['nullable', 'string', 'max:2000'],
            'instituicao' => ['nullable', 'string', 'max:255'],
            'nome_avaliacao' => ['nullable', 'string', 'max:255'],
            'tamanho_instituicao' => [
                'nullable', 'integer',
                'min:'.LayoutDaFolha::TAMANHO_MINIMO_DA_INSTITUICAO,
                'max:'.LayoutDaFolha::TAMANHO_MAXIMO_DA_INSTITUICAO,
            ],
            'questoesEscolhidas' => ['array', 'min:1'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'turma_id' => 'turma',
            'modelo_prova_id' => 'modelo de prova',
            'titulo' => 'título',
            'bimestre' => 'bimestre',
            'data_aplicacao' => 'data de aplicação',
            'questoesEscolhidas' => 'questões',
            'instituicao' => 'nome no topo da folha',
            'nome_avaliacao' => 'nome da avaliação',
            'tamanho_instituicao' => 'tamanho do nome',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'questoesEscolhidas.min' => 'Escolha ao menos uma questão para a prova.',
            'modelo_prova_id.required' => 'Cadastre um modelo de prova antes de montar.',
        ];
    }

    public function montar(MontarProvaAction $action): void
    {
        $this->authorize('create', Prova::class);

        $dados = $this->validate();

        try {
            $prova = $action->executar(
                autor: auth()->user(),
                turma: Turma::query()->findOrFail($dados['turma_id']),
                modelo: ModeloProva::query()->findOrFail($dados['modelo_prova_id']),
                titulo: $dados['titulo'],
                bimestre: Bimestre::from($dados['bimestre']),
                dataAplicacao: $dados['data_aplicacao'] ? now()->parse($dados['data_aplicacao']) : null,
                questoesEscolhidas: $this->escolhidas(),
                configuracao: [
                    'colunas' => $dados['colunas'],
                    'mostrar_pesos' => $this->mostrar_pesos,
                ],
                instrucoes: $dados['instrucoes'] ?: null,
                instituicao: $dados['instituicao'] ?: null,
                nomeAvaliacao: $dados['nome_avaliacao'] ?: null,
                tamanhoInstituicao: $dados['tamanho_instituicao'] === '' ? null : (int) $dados['tamanho_instituicao'],
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->flashSucesso(
            "Prova montada com {$prova->totalDeQuestoes()} questão(ões), numeradas de 1 a {$prova->totalDeQuestoes()}."
        );

        $this->redirectRoute('provas.show', $prova, navigate: true);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Turma> */
    public function turmasDisponiveis(): \Illuminate\Database\Eloquent\Collection
    {
        return Turma::query()
            ->visivelPara(auth()->user())
            ->with('curso:id,nome,eixo_id')
            ->orderBy('nome')
            ->get();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ModeloProva> */
    public function modelosDisponiveis(): \Illuminate\Database\Eloquent\Collection
    {
        return ModeloProva::query()
            ->visivelPara(auth()->user())
            ->where('ativo', true)
            ->orderBy('nome')
            ->get();
    }

    public function modeloEscolhido(): ?ModeloProva
    {
        return $this->modelo_prova_id === null
            ? null
            : $this->modelosDisponiveis()->firstWhere('id', $this->modelo_prova_id);
    }

    /** @return Collection<int, Questao> */
    public function questoesAprovadas(): Collection
    {
        $turma = $this->turmaSelecionada();

        if ($turma === null) {
            return collect();
        }

        return app(MontarProvaAction::class)->questoesElegiveis($turma)
            // A tela mostra o professor de cada bloco de disciplina.
            ->load('professor')
            ->sortBy(fn (Questao $questao) => sprintf(
                '%04d-%04d', $questao->parte?->ordem ?? 0, $questao->ordem,
            ))
            ->values();
    }

    protected function turmaSelecionada(): ?Turma
    {
        return $this->turma_id === null
            ? null
            : Turma::query()->visivelPara(auth()->user())->with('curso')->find($this->turma_id);
    }

    public function render(): View
    {
        $aprovadas = $this->questoesAprovadas();
        $escolhidas = $aprovadas->whereIn('id', $this->escolhidas());

        return view('provas.montar', [
            'turmas' => $this->turmasDisponiveis(),
            'modelos' => $this->modelosDisponiveis(),
            'bimestres' => Bimestre::opcoes(),
            'turmaSelecionada' => $this->turmaSelecionada(),
            // O que o modelo diria, para o campo mostrar como marca-d'água
            // em vez de vir preenchido: em branco o modelo é que vale.
            'modeloEscolhido' => $this->modeloEscolhido(),
            'tamanhoDoModelo' => $this->modeloEscolhido()?->layoutDaFolha()->tamanhoDaInstituicao(),
            'porDisciplina' => $aprovadas->groupBy(fn (Questao $q) => $q->disciplina->nome),
            'totalEscolhido' => $escolhidas->count(),
            'somaDosPesos' => (float) $escolhidas->sum('peso'),
        ])->layout('components.layouts.app', [
            'titulo' => 'Montar prova',
            'subtitulo' => 'As questões aprovadas entram numeradas em sequência, agrupadas por disciplina',
        ]);
    }
}
