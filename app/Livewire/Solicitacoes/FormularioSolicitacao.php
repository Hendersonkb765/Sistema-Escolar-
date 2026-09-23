<?php

namespace App\Livewire\Solicitacoes;

use App\Actions\Avaliacao\CriarSolicitacaoAction;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Disciplina;
use App\Models\SolicitacaoProva;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection as ColecaoSimples;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Abertura da solicitação de uma prova.
 *
 * A prova é uma só e reúne várias disciplinas: para cada uma escolhe-se o
 * professor responsável e quantas questões ele deve entregar.
 */
class FormularioSolicitacao extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public ?int $turma_id = null;

    public string $titulo = '';

    public int $quantidade_alternativas = 4;

    public string $prazo = '';

    public string $observacoes = '';

    /**
     * Pares disciplina + professor + cota de questões.
     *
     * @var array<int, array{disciplina_id: string, professor_id: string, quantidade_questoes: string, observacoes: string}>
     */
    public array $partes = [];

    public function mount(): void
    {
        $this->authorize('create', SolicitacaoProva::class);

        $this->turma_id = (int) request()->query('turma') ?: null;
        $this->prazo = now()->addWeek()->format('Y-m-d\TH:i');
        $this->partes = [$this->parteVazia()];
    }

    public function updatedTurmaId(): void
    {
        // Trocar de turma invalida as disciplinas escolhidas.
        $this->partes = [$this->parteVazia()];
    }

    /** @return array{disciplina_id: string, professor_id: string, quantidade_questoes: string, observacoes: string} */
    protected function parteVazia(): array
    {
        return [
            'disciplina_id' => '',
            'professor_id' => '',
            'quantidade_questoes' => '5',
            'observacoes' => '',
        ];
    }

    public function adicionarParte(): void
    {
        $this->partes[] = $this->parteVazia();
    }

    public function removerParte(int $indice): void
    {
        if (count($this->partes) <= 1) {
            $this->notificarInfo('A prova precisa de ao menos uma disciplina.');

            return;
        }

        unset($this->partes[$indice]);

        $this->partes = array_values($this->partes);
    }

    /** Ao escolher a disciplina, sugere quem já leciona. */
    public function updatedPartes(mixed $valor, string $chave): void
    {
        if (! str_ends_with($chave, '.disciplina_id')) {
            return;
        }

        $indice = (int) explode('.', $chave)[0];

        $sugerido = $this->professoresDaDisciplina((int) $valor)->first();

        if ($sugerido !== null && blank($this->partes[$indice]['professor_id'] ?? '')) {
            $this->partes[$indice]['professor_id'] = (string) $sugerido->getKey();
        }
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $disciplinasValidas = $this->disciplinasDaTurma()->pluck('id')->all();

        return [
            'turma_id' => ['required', Rule::in($this->turmasDisponiveis()->pluck('id')->all())],
            'titulo' => ['nullable', 'string', 'max:255'],
            'quantidade_alternativas' => ['required', 'integer', 'min:2', 'max:6'],
            'prazo' => ['required', 'date'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'partes' => ['array', 'min:1'],
            'partes.*.disciplina_id' => ['required', Rule::in($disciplinasValidas)],
            'partes.*.professor_id' => [
                'required',
                Rule::exists('usuarios', 'id')->where(
                    fn ($consulta) => $consulta->where('ativo', true)->whereNull('deleted_at')
                ),
            ],
            'partes.*.quantidade_questoes' => ['required', 'integer', 'min:1', 'max:60'],
            'partes.*.observacoes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'turma_id' => 'turma',
            'quantidade_alternativas' => 'quantidade de alternativas',
            'prazo' => 'prazo',
            'partes' => 'disciplinas',
            'partes.*.disciplina_id' => 'disciplina',
            'partes.*.professor_id' => 'professor',
            'partes.*.quantidade_questoes' => 'quantidade de questões',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'partes.min' => 'Inclua ao menos uma disciplina na prova.',
            'partes.*.disciplina_id.required' => 'Escolha a disciplina.',
            'partes.*.disciplina_id.in' => 'Escolha uma disciplina que esta turma cursa no período dela.',
            'partes.*.professor_id.required' => 'Escolha o professor responsável.',
            'partes.*.professor_id.exists' => 'Selecione um professor com conta ativa.',
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
                partes: array_map(fn (array $parte) => [
                    'disciplina_id' => (int) $parte['disciplina_id'],
                    'professor_id' => (int) $parte['professor_id'],
                    'quantidade_questoes' => (int) $parte['quantidade_questoes'],
                    'observacoes' => $parte['observacoes'] ?: null,
                ], $dados['partes']),
                quantidadeAlternativas: $dados['quantidade_alternativas'],
                prazo: now()->parse($dados['prazo']),
                titulo: $dados['titulo'] ?: null,
                observacoes: $dados['observacoes'] ?: null,
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->flashSucesso(
            "Solicitação aberta: {$solicitacao->partes()->count()} disciplina(s), "
            .$solicitacao->totalDeQuestoes().' questão(ões) no total. '
            .'Cada professor recebe a parte dele.'
        );

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
    public function disciplinasDaTurma(): ColecaoSimples
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

    /** @return ColecaoSimples<int, User> */
    public function professoresDaDisciplina(?int $disciplinaId): ColecaoSimples
    {
        if ($disciplinaId === null || $disciplinaId === 0) {
            return collect();
        }

        return User::query()
            ->where('ativo', true)
            ->whereHas('vinculosDocentes', fn ($q) => $q
                ->where('disciplina_id', $disciplinaId)
                ->where('ativo', true))
            ->orderBy('nome')
            ->get();
    }

    /** Demais contas do escopo, para quem ainda não tem vínculo docente. */
    public function outrosUsuarios(): ColecaoSimples
    {
        return User::query()
            ->where('ativo', true)
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
        $disciplinas = $this->disciplinasDaTurma();

        return view('solicitacoes.formulario', [
            'turmas' => $this->turmasDisponiveis(),
            'disciplinas' => $disciplinas,
            'outros' => $this->outrosUsuarios(),
            'turmaSelecionada' => $this->turmaSelecionada(),
            // Sugestões por disciplina, resolvidas de uma vez para a view
            // não consultar dentro do laço.
            'sugeridosPorDisciplina' => $disciplinas
                ->mapWithKeys(fn (Disciplina $d) => [$d->getKey() => $this->professoresDaDisciplina($d->getKey())]),
            'totalDeQuestoes' => collect($this->partes)->sum(fn (array $p) => (int) ($p['quantidade_questoes'] ?? 0)),
        ])->layout('components.layouts.app', [
            'titulo' => 'Nova solicitação de questões',
            'subtitulo' => 'Uma prova reúne várias disciplinas — cada uma com seu professor e sua cota',
        ]);
    }
}
