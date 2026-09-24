<?php

namespace App\Livewire\Provas;

use App\Actions\Prova\MontarProvaAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\ModeloProva;
use App\Models\Prova;
use App\Models\Questao;
use App\Models\Turma;
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

    public string $data_aplicacao = '';

    public int $colunas = 2;

    public bool $mostrar_pesos = false;

    public string $instrucoes = '';

    /** Questões marcadas para entrar. @var array<int, int> */
    public array $questoesEscolhidas = [];

    public function mount(): void
    {
        $this->authorize('create', Prova::class);

        $this->turma_id = (int) request()->query('turma') ?: null;
        $this->data_aplicacao = now()->addWeek()->format('Y-m-d');

        $this->sincronizarEscolhas();
        $this->sugerirModelo();
    }

    public function updatedTurmaId(): void
    {
        $this->sincronizarEscolhas();
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
            'data_aplicacao' => ['nullable', 'date'],
            'colunas' => ['required', 'integer', Rule::in([1, 2])],
            'instrucoes' => ['nullable', 'string', 'max:2000'],
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
            'data_aplicacao' => 'data de aplicação',
            'questoesEscolhidas' => 'questões',
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
                dataAplicacao: $dados['data_aplicacao'] ? now()->parse($dados['data_aplicacao']) : null,
                questoesEscolhidas: $this->escolhidas(),
                configuracao: [
                    'colunas' => $dados['colunas'],
                    'mostrar_pesos' => $this->mostrar_pesos,
                ],
                instrucoes: $dados['instrucoes'] ?: null,
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
            'turmaSelecionada' => $this->turmaSelecionada(),
            'porDisciplina' => $aprovadas->groupBy(fn (Questao $q) => $q->disciplina->nome),
            'totalEscolhido' => $escolhidas->count(),
            'somaDosPesos' => (float) $escolhidas->sum('peso'),
        ])->layout('components.layouts.app', [
            'titulo' => 'Montar prova',
            'subtitulo' => 'As questões aprovadas entram numeradas em sequência, agrupadas por disciplina',
        ]);
    }
}
