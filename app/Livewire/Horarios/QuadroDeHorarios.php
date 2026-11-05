<?php

namespace App\Livewire\Horarios;

use App\Actions\Horarios\SalvarHorarioAction;
use App\Enums\DiaDaSemana;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\QuadroHorario;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QuadroDeHorarios extends Component
{
    use AuthorizesRequests;
    use Notifica;

    #[Locked]
    public Turma $turma;

    /** A aula aberta no formulário, ou null para uma nova. */
    #[Locked]
    public ?int $editando = null;

    public string $dia = '1';

    public string $aula_numero = '1';

    public string $hora_inicio = '';

    public string $hora_fim = '';

    public string $disciplina_id = '';

    public string $professor_id = '';

    public function mount(Turma $turma): void
    {
        $this->authorize('update', $turma);

        $this->turma = $turma;
    }

    /** Trocar a disciplina invalida o professor escolhido para a anterior. */
    public function updatedDisciplinaId(): void
    {
        $this->professor_id = '';
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'dia' => ['required', Rule::in(DiaDaSemana::valores())],
            'aula_numero' => ['required', 'integer', 'between:1,12'],
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'disciplina_id' => ['required', 'integer'],
            'professor_id' => ['required', 'integer'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'dia' => 'dia',
            'aula_numero' => 'número da aula',
            'hora_inicio' => 'início',
            'hora_fim' => 'fim',
            'disciplina_id' => 'disciplina',
            'professor_id' => 'professor',
        ];
    }

    public function editar(int $id): void
    {
        $aula = $this->aulaDaTurma($id);

        $this->authorize('update', $aula);

        $this->editando = $aula->id;
        $this->dia = (string) $aula->dia_semana->value;
        $this->aula_numero = (string) $aula->aula_numero;
        $this->hora_inicio = $aula->horaInicio();
        $this->hora_fim = $aula->horaFim();
        $this->disciplina_id = (string) $aula->disciplina_id;
        $this->professor_id = (string) $aula->professor_id;
        $this->resetErrorBag();
    }

    public function cancelarEdicao(): void
    {
        $this->limpar();
    }

    public function salvar(SalvarHorarioAction $action): void
    {
        $existente = $this->editando !== null ? $this->aulaDaTurma($this->editando) : null;

        $existente !== null
            ? $this->authorize('update', $existente)
            : $this->authorize('update', $this->turma);

        $dados = $this->validate();

        try {
            $action->executar($this->turma, [
                'dia' => DiaDaSemana::from((int) $dados['dia']),
                'aula_numero' => (int) $dados['aula_numero'],
                'hora_inicio' => $dados['hora_inicio'],
                'hora_fim' => $dados['hora_fim'],
                'disciplina_id' => (int) $dados['disciplina_id'],
                'professor_id' => (int) $dados['professor_id'],
            ], $existente);
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso($existente !== null ? 'Aula atualizada no quadro.' : 'Aula incluída no quadro.');
        $this->limpar();
    }

    public function remover(int $id): void
    {
        $aula = $this->aulaDaTurma($id);

        $this->authorize('delete', $aula);

        $descricao = $aula->descricaoDaAula();
        $aula->delete();

        if ($this->editando === $id) {
            $this->limpar();
        }

        $this->notificarSucesso(ucfirst($descricao).' removida do quadro.');
    }

    /**
     * A aula pelo id, confinada a esta turma. O id chega do navegador:
     * buscar pelo relacionamento é o que impede mexer no quadro de outra
     * turma trocando o número.
     */
    protected function aulaDaTurma(int $id): QuadroHorario
    {
        return QuadroHorario::query()
            ->where('turma_id', $this->turma->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    protected function limpar(): void
    {
        $this->reset(['editando', 'hora_inicio', 'hora_fim', 'disciplina_id', 'professor_id']);
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $aulas = QuadroHorario::query()
            ->where('turma_id', $this->turma->id)
            ->with('disciplina:id,nome', 'professor:id,nome', 'turma:id,curso_id', 'turma.curso:id,eixo_id')
            ->orderBy('dia_semana')
            ->orderBy('aula_numero')
            ->get();

        // Sábado só aparece quando há aula nele.
        $dias = collect(DiaDaSemana::cases())
            ->reject(fn (DiaDaSemana $dia) => $dia === DiaDaSemana::Sabado
                && $aulas->every(fn (QuadroHorario $a) => $a->dia_semana !== DiaDaSemana::Sabado))
            ->values();

        $professores = $this->disciplina_id === ''
            ? collect()
            : User::query()
                ->where('ativo', true)
                ->whereHas('vinculosDocentes', fn (Builder $q) => $q
                    ->where('disciplina_id', (int) $this->disciplina_id)
                    ->where('ativo', true)
                    ->where(fn (Builder $sub) => $sub->whereNull('turma_id')->orWhere('turma_id', $this->turma->id)))
                ->orderBy('nome')
                ->get(['id', 'nome']);

        return view('horarios.quadro', [
            'dias' => $dias,
            'numeros' => $aulas->pluck('aula_numero')->unique()->sort()->values(),
            'porCelula' => $aulas->keyBy(fn (QuadroHorario $a) => $a->dia_semana->value.'-'.$a->aula_numero),
            // A grade é foto e ainda lista a disciplina na Lixeira; no quadro ela não entra.
            'disciplinas' => $this->turma->disciplinasDoPeriodo()->map->disciplina->reject->trashed()->sortBy('nome')->values(),
            'professores' => $professores,
            'diasDoFormulario' => DiaDaSemana::cases(),
        ])->layout('components.layouts.app', [
            'titulo' => 'Quadro de horários',
            'subtitulo' => "Turma {$this->turma->nome} · {$this->turma->periodo_letivo}",
        ]);
    }
}
