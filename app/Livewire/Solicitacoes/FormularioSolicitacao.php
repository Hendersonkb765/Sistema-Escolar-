<?php

namespace App\Livewire\Solicitacoes;

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Disciplina;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Abertura de uma solicitação de questões. Escolhe-se a turma, e a partir
 * dela as disciplinas possíveis são as do período que ela cursa, segundo
 * a foto de grade que congelou.
 */
class FormularioSolicitacao extends Component
{
    use AuthorizesRequests;

    public ?int $turma_id = null;

    public ?int $disciplina_id = null;

    public ?int $professor_id = null;

    public int $quantidade_questoes = 5;

    public int $quantidade_alternativas = 4;

    public string $prazo = '';

    public string $observacoes = '';

    /** Um peso por questão pedida, na ordem. @var array<int, string> */
    public array $pesos = [];

    public function mount(): void
    {
        $this->authorize('create', SolicitacaoProva::class);

        $this->turma_id = (int) request()->query('turma') ?: null;
        $this->prazo = now()->addWeek()->format('Y-m-d\TH:i');
        $this->ajustarPesos();
    }

    public function updatedTurmaId(): void
    {
        $this->disciplina_id = null;
        $this->professor_id = null;
    }

    public function updatedDisciplinaId(): void
    {
        // Quem já leciona a disciplina é o palpite natural.
        $this->professor_id = $this->professoresSugeridos()->first()?->getKey();
    }

    public function updatedQuantidadeQuestoes(): void
    {
        $this->ajustarPesos();
    }

    /** Mantém a lista de pesos do tamanho da quantidade pedida. */
    protected function ajustarPesos(): void
    {
        $quantidade = max(1, min(60, (int) $this->quantidade_questoes));

        $this->quantidade_questoes = $quantidade;

        $atuais = array_values($this->pesos);

        $this->pesos = collect(range(1, $quantidade))
            ->map(fn (int $ordem) => $atuais[$ordem - 1] ?? '1')
            ->all();
    }

    public function aplicarPesoATodas(): void
    {
        $primeiro = $this->pesos[0] ?? '1';

        $this->pesos = array_fill(0, count($this->pesos), $primeiro);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $usuario = auth()->user();

        return [
            'turma_id' => ['required', Rule::in($this->turmasDisponiveis()->pluck('id')->all())],
            // Só as disciplinas que a turma cursa neste período.
            'disciplina_id' => ['required', Rule::in($this->disciplinasDaTurma()->pluck('id')->all())],
            'professor_id' => [
                'required',
                Rule::exists('usuarios', 'id')->where(
                    fn ($consulta) => $consulta->where('ativo', true)->whereNull('deleted_at')
                ),
            ],
            'quantidade_questoes' => ['required', 'integer', 'min:1', 'max:60'],
            'quantidade_alternativas' => ['required', 'integer', 'min:2', 'max:6'],
            'prazo' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'pesos' => ['array', 'size:'.$this->quantidade_questoes],
            'pesos.*' => ['required', 'numeric', 'gt:0', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'turma_id' => 'turma',
            'disciplina_id' => 'disciplina',
            'professor_id' => 'professor',
            'quantidade_questoes' => 'quantidade de questões',
            'quantidade_alternativas' => 'quantidade de alternativas',
            'prazo' => 'prazo',
            'pesos' => 'pesos',
            'pesos.*' => 'peso',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'disciplina_id.in' => 'Selecione uma disciplina que esta turma cursa no período dela.',
            'professor_id.exists' => 'Selecione um professor com conta ativa.',
            'pesos.*.gt' => 'O peso precisa ser maior que zero.',
        ];
    }

    public function salvar(CriarSolicitacaoAction $action): void
    {
        $this->authorize('create', SolicitacaoProva::class);

        $dados = $this->validate();

        try {
            $solicitacao = $action->executar(
                autor: auth()->user(),
                turma: Turma::query()->findOrFail($dados['turma_id']),
                disciplina: Disciplina::query()->findOrFail($dados['disciplina_id']),
                professor: User::query()->findOrFail($dados['professor_id']),
                pesos: $dados['pesos'],
                quantidadeAlternativas: $dados['quantidade_alternativas'],
                prazo: now()->parse($dados['prazo']),
                observacoes: $dados['observacoes'] ?: null,
            );
        } catch (RegraDeNegocioException $excecao) {
            session()->flash('erro', $excecao->getMessage());

            return;
        }

        session()->flash('sucesso',
            "Solicitação aberta para {$solicitacao->professor->nome}: {$solicitacao->quantidade_questoes} questão(ões), soma dos pesos "
            .number_format($solicitacao->somaDosPesos(), 2, ',', '.').'.');

        $this->redirectRoute('solicitacoes.show', $solicitacao, navigate: true);
    }

    /** @return Collection<int, Turma> */
    public function turmasDisponiveis(): Collection
    {
        return Turma::query()
            ->visivelPara(auth()->user())
            ->where('status', StatusTurma::Ativa)
            ->with('curso:id,nome,eixo_id')
            ->orderBy('nome')
            ->get();
    }

    /** Disciplinas do período que a turma cursa, pela grade congelada. */
    public function disciplinasDaTurma(): \Illuminate\Support\Collection
    {
        $turma = $this->turmaSelecionada();

        if ($turma === null) {
            return collect();
        }

        return $turma->disciplinasDoPeriodo()
            ->map(fn ($item) => $item->disciplina)
            ->filter()
            ->sortBy('nome')
            ->values();
    }

    /** @return \Illuminate\Support\Collection<int, User> */
    public function professoresSugeridos(): \Illuminate\Support\Collection
    {
        if ($this->disciplina_id === null) {
            return collect();
        }

        return User::query()
            ->where('ativo', true)
            ->whereHas('vinculosDocentes', fn ($q) => $q
                ->where('disciplina_id', $this->disciplina_id)
                ->where('ativo', true))
            ->orderBy('nome')
            ->get();
    }

    /** Demais contas do escopo, para quem ainda não tem vínculo docente. */
    public function outrosUsuarios(): \Illuminate\Support\Collection
    {
        // pluck em vez de modelKeys: sem disciplina escolhida a lista de
        // sugeridos é uma Collection simples, que não tem modelKeys.
        $jaSugeridos = $this->professoresSugeridos()->pluck('id')->all();

        return User::query()
            ->where('ativo', true)
            ->whereKeyNot($jaSugeridos ?: [0])
            ->whereHas('eixos', fn ($q) => $q->whereIn('eixos.id', auth()->user()->eixoIds()))
            ->orderBy('nome')
            ->get();
    }

    protected function turmaSelecionada(): ?Turma
    {
        if ($this->turma_id === null) {
            return null;
        }

        return Turma::query()
            ->visivelPara(auth()->user())
            ->with('grade')
            ->find($this->turma_id);
    }

    public function render(): View
    {
        return view('solicitacoes.formulario', [
            'turmas' => $this->turmasDisponiveis(),
            'disciplinas' => $this->disciplinasDaTurma(),
            'sugeridos' => $this->professoresSugeridos(),
            'outros' => $this->outrosUsuarios(),
            'turmaSelecionada' => $this->turmaSelecionada(),
            'somaDosPesos' => collect($this->pesos)->sum(fn ($peso) => (float) $peso),
        ])->layout('components.layouts.app', [
            'titulo' => 'Nova solicitação de questões',
            'subtitulo' => 'O peso de cada questão é definido aqui e o professor não pode alterá-lo',
        ]);
    }
}
