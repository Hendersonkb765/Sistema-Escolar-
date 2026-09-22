<?php

namespace App\Livewire\Alunos;

use App\Actions\Academico\MoverAlunoDeTurmaAction;
use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class FormularioAluno extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public ?Aluno $aluno = null;

    public ?int $turma_id = null;

    public string $nome = '';

    public string $matricula = '';

    public string $status = 'ativo';

    public string $motivoMovimentacao = '';

    /**
     * Turma original, para detectar troca e gerar o histórico correto.
     * `Locked` porque precisa sobreviver entre requests do Livewire sem
     * poder ser alterada pelo cliente.
     */
    #[Locked]
    public ?int $turmaOriginal = null;

    public function mount(?Aluno $aluno = null): void
    {
        if ($aluno?->exists) {
            $this->authorize('update', $aluno);

            $this->aluno = $aluno;
            $this->turma_id = $aluno->turma_id;
            $this->turmaOriginal = $aluno->turma_id;
            $this->nome = $aluno->nome;
            $this->matricula = $aluno->matricula;
            $this->status = $aluno->status->value;

            return;
        }

        $this->authorize('create', Aluno::class);

        $this->turma_id = (int) request()->query('turma') ?: null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $usuario = auth()->user();
        $turmasVisiveis = Turma::query()->visivelPara($usuario)->pluck('id')->all();

        return [
            'turma_id' => ['required', Rule::in($turmasVisiveis)],
            'nome' => ['required', 'string', 'max:255'],
            // A matrícula é única dentro da turma.
            'matricula' => [
                'required', 'string', 'max:40',
                Rule::unique('alunos', 'matricula')
                    ->where(fn ($consulta) => $consulta->where('turma_id', $this->turma_id))
                    ->ignore($this->aluno?->getKey()),
            ],
            'status' => ['required', Rule::in(StatusAluno::valores())],
            'motivoMovimentacao' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'turma_id' => 'turma',
            'nome' => 'nome',
            'matricula' => 'matrícula',
            'status' => 'status',
            'motivoMovimentacao' => 'motivo',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'matricula.unique' => 'Esta matrícula já existe nesta turma.',
            'turma_id.in' => 'Selecione uma turma dentro do seu escopo.',
        ];
    }

    public function salvar(MoverAlunoDeTurmaAction $mover): void
    {
        $this->aluno !== null
            ? $this->authorize('update', $this->aluno)
            : $this->authorize('create', Aluno::class);

        $dados = $this->validate();

        try {
            DB::transaction(function () use ($dados, $mover) {
                if ($this->aluno === null) {
                    $this->aluno = Aluno::create([
                        'turma_id' => $dados['turma_id'],
                        'nome' => $dados['nome'],
                        'matricula' => $dados['matricula'],
                        'status' => $dados['status'],
                    ]);

                    AlunoHistorico::create([
                        'aluno_id' => $this->aluno->getKey(),
                        'evento' => EventoHistorico::AlunoMatriculado,
                        'turma_nova_id' => $this->aluno->turma_id,
                        'status_novo' => $this->aluno->status->value,
                        'registrado_por' => auth()->id(),
                    ]);

                    return;
                }

                $statusAnterior = $this->aluno->status;
                $trocouDeTurma = (int) $dados['turma_id'] !== (int) $this->turmaOriginal;

                $this->aluno->update([
                    'nome' => $dados['nome'],
                    'matricula' => $dados['matricula'],
                    'status' => $dados['status'],
                ]);

                // Troca de turma passa pela action, que grava o histórico.
                if ($trocouDeTurma) {
                    $destino = Turma::query()->findOrFail($dados['turma_id']);

                    $mover->executar(
                        aluno: $this->aluno,
                        destino: $destino,
                        autor: auth()->user(),
                        motivo: $this->motivoMovimentacao ?: null,
                    );
                }

                if ($statusAnterior->value !== $dados['status']) {
                    AlunoHistorico::create([
                        'aluno_id' => $this->aluno->getKey(),
                        'evento' => EventoHistorico::AlunoStatusAlterado,
                        'turma_anterior_id' => $this->turmaOriginal,
                        'turma_nova_id' => $this->aluno->turma_id,
                        'status_anterior' => $statusAnterior->value,
                        'status_novo' => $dados['status'],
                        'motivo' => $this->motivoMovimentacao ?: null,
                        'registrado_por' => auth()->id(),
                    ]);
                }
            });
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        session()->flash('sucesso', 'Aluno salvo com sucesso.');

        $this->redirectRoute('alunos.index', navigate: true);
    }

    public function render(): View
    {
        return view('alunos.formulario', [
            'turmasDisponiveis' => Turma::query()
                ->visivelPara(auth()->user())
                ->with('curso:id,nome,eixo_id')
                ->orderBy('nome')
                ->get(),
            'situacoes' => StatusAluno::opcoes(),
            'trocandoDeTurma' => $this->aluno !== null && (int) $this->turma_id !== (int) $this->turmaOriginal,
            'historicos' => $this->aluno?->historicos()
                ->with(['turmaAnterior', 'turmaNova', 'registradoPor'])
                ->latest('created_at')
                ->get() ?? collect(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->aluno === null ? 'Novo aluno' : 'Editar aluno',
            'subtitulo' => $this->aluno?->nome,
        ]);
    }
}
