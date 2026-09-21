<?php

namespace App\Livewire\Turmas;

use App\Actions\Academico\RegistrarHistoricoDeTurma;
use App\Enums\EventoHistorico;
use App\Enums\StatusGrade;
use App\Enums\StatusTurma;
use App\Models\Curso;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class FormularioTurma extends Component
{
    use AuthorizesRequests;

    public ?Turma $turma = null;

    public ?int $curso_id = null;

    public ?int $grade_curricular_id = null;

    public int $ano_curso = 1;

    public string $identificacao = '';

    public string $periodo_letivo = '';

    public string $status = 'ativa';

    public function mount(?Turma $turma = null): void
    {
        $this->periodo_letivo = (string) now()->format('Y');

        if ($turma?->exists) {
            $this->authorize('update', $turma);

            $this->turma = $turma;
            $this->curso_id = $turma->curso_id;
            $this->grade_curricular_id = $turma->grade_curricular_id;
            $this->ano_curso = $turma->ano_curso;
            $this->identificacao = $turma->identificacao;
            $this->periodo_letivo = $turma->periodo_letivo;
            $this->status = $turma->status->value;

            return;
        }

        $this->authorize('create', Turma::class);

        $this->curso_id = (int) request()->query('curso') ?: null;
        $this->sugerirGradeVigente();
    }

    public function updatedCursoId(): void
    {
        $this->grade_curricular_id = null;
        $this->sugerirGradeVigente();
    }

    /** Ao abrir uma turma, o padrão é congelar a grade vigente do curso. */
    protected function sugerirGradeVigente(): void
    {
        if ($this->curso_id === null) {
            return;
        }

        $this->grade_curricular_id = GradeCurricular::query()
            ->where('curso_id', $this->curso_id)
            ->where('status', StatusGrade::Vigente)
            ->orderByDesc('versao')
            ->value('id');
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $usuario = auth()->user();
        $duracao = (int) (Curso::query()->find($this->curso_id)?->duracao_anos ?? 3);

        return [
            'curso_id' => [
                'required',
                Rule::exists('cursos', 'id')->where(
                    fn ($consulta) => $consulta->whereIn('eixo_id', $usuario->eixoIds())
                ),
            ],
            // A grade precisa ser do mesmo curso — não se congela em uma
            // turma a grade de outro curso.
            'grade_curricular_id' => [
                'required',
                Rule::exists('grades_curriculares', 'id')->where(
                    fn ($consulta) => $consulta->where('curso_id', $this->curso_id)
                ),
            ],
            'ano_curso' => ['required', 'integer', 'min:1', 'max:'.$duracao],
            'identificacao' => [
                'required', 'string', 'max:30',
                Rule::unique('turmas', 'identificacao')
                    ->where(fn ($consulta) => $consulta
                        ->where('curso_id', $this->curso_id)
                        ->where('periodo_letivo', $this->periodo_letivo))
                    ->ignore($this->turma?->getKey()),
            ],
            'periodo_letivo' => ['required', 'string', 'max:20'],
            'status' => ['required', Rule::in(StatusTurma::valores())],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'curso_id' => 'curso',
            'grade_curricular_id' => 'grade curricular',
            'ano_curso' => 'ano do curso',
            'identificacao' => 'identificação',
            'periodo_letivo' => 'período letivo',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'identificacao.unique' => 'Já existe uma turma com esta identificação neste curso e período.',
            'grade_curricular_id.exists' => 'Selecione uma grade curricular do curso escolhido.',
            'ano_curso.max' => 'O curso selecionado não chega a esse ano.',
        ];
    }

    public function salvar(RegistrarHistoricoDeTurma $historico): void
    {
        $this->turma !== null
            ? $this->authorize('update', $this->turma)
            : $this->authorize('create', Turma::class);

        $dados = $this->validate();

        DB::transaction(function () use ($dados, $historico) {
            if ($this->turma === null) {
                $this->turma = Turma::create($dados);

                $historico->executar(
                    turma: $this->turma,
                    evento: EventoHistorico::TurmaCriada,
                    autor: auth()->user(),
                );

                return;
            }

            // Estado anterior guardado antes de sobrescrever.
            $historico->executar(
                turma: $this->turma,
                evento: EventoHistorico::TurmaAlterada,
                autor: auth()->user(),
                metadados: ['alteracoes' => array_keys($this->turma->fill($dados)->getDirty())],
            );

            $this->turma->update($dados);
        });

        session()->flash('sucesso', 'Turma salva com sucesso.');

        $this->redirectRoute('turmas.show', $this->turma, navigate: true);
    }

    public function render(): View
    {
        $usuario = auth()->user();

        return view('turmas.formulario', [
            'cursosDisponiveis' => Curso::query()->visivelPara($usuario)->orderBy('nome')->get(),
            'gradesDisponiveis' => $this->curso_id === null
                ? collect()
                : GradeCurricular::query()
                    ->where('curso_id', $this->curso_id)
                    ->orderByDesc('versao')
                    ->get(),
            'situacoes' => StatusTurma::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->turma === null ? 'Nova turma' : 'Editar turma',
            'subtitulo' => $this->turma?->identificacao,
        ]);
    }
}
