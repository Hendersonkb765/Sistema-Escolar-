<?php

namespace App\Livewire\Grades;

use App\Enums\StatusGrade;
use App\Models\Curso;
use App\Models\Disciplina;
use App\Models\GradeCurricular;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Cria ou edita uma grade **em rascunho**. Uma versão vigente ou já
 * congelada por turmas não passa por aqui: para alterá-la, gera-se uma
 * nova versão em DetalheGrade.
 */
class FormularioGrade extends Component
{
    use AuthorizesRequests;

    public ?GradeCurricular $grade = null;

    public ?int $curso_id = null;

    public int $ano_vigencia;

    public string $observacoes = '';

    /**
     * Linhas do editor: cada uma é uma disciplina posicionada em um ano
     * do curso, com sua carga horária.
     *
     * @var array<int, array{disciplina_id: int|string, ano_curso: int, carga_horaria: int}>
     */
    public array $itens = [];

    public function mount(?GradeCurricular $grade = null): void
    {
        $this->ano_vigencia = (int) now()->format('Y');

        if ($grade?->exists) {
            $this->authorize('update', $grade);

            $this->grade = $grade;
            $this->curso_id = $grade->curso_id;
            $this->ano_vigencia = $grade->ano_vigencia;
            $this->observacoes = (string) $grade->observacoes;
            $this->itens = $grade->disciplinas
                ->map(fn ($item) => [
                    'disciplina_id' => $item->disciplina_id,
                    'ano_curso' => $item->ano_curso,
                    'carga_horaria' => $item->carga_horaria,
                ])
                ->values()
                ->all();

            return;
        }

        $this->authorize('create', GradeCurricular::class);

        $this->curso_id = (int) request()->query('curso') ?: null;
    }

    public function adicionarItem(): void
    {
        $this->itens[] = [
            'disciplina_id' => '',
            'ano_curso' => 1,
            'carga_horaria' => 80,
        ];
    }

    public function removerItem(int $indice): void
    {
        unset($this->itens[$indice]);

        $this->itens = array_values($this->itens);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $usuario = auth()->user();
        $duracao = $this->duracaoDoCurso();

        return [
            'curso_id' => [
                'required',
                Rule::exists('cursos', 'id')->where(
                    fn ($consulta) => $consulta->whereIn('eixo_id', $usuario->eixoIds())
                ),
            ],
            'ano_vigencia' => ['required', 'integer', 'min:2000', 'max:2100'],
            'observacoes' => ['nullable', 'string', 'max:2000'],
            'itens' => ['array', 'min:1'],
            'itens.*.disciplina_id' => [
                'required',
                Rule::exists('disciplinas', 'id')->where(
                    fn ($consulta) => $consulta->whereIn('eixo_id', $usuario->eixoIds())
                ),
            ],
            // O ano precisa caber na duração do curso: não existe 3º ano
            // em um curso de 2 anos.
            'itens.*.ano_curso' => ['required', 'integer', 'min:1', 'max:'.$duracao],
            'itens.*.carga_horaria' => ['required', 'integer', 'min:1', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'curso_id' => 'curso',
            'ano_vigencia' => 'ano de vigência',
            'itens' => 'disciplinas',
            'itens.*.disciplina_id' => 'disciplina',
            'itens.*.ano_curso' => 'ano do curso',
            'itens.*.carga_horaria' => 'carga horária',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'itens.min' => 'Inclua ao menos uma disciplina na grade.',
            'itens.*.ano_curso.max' => 'O curso selecionado não tem esse ano.',
        ];
    }

    public function salvar(): void
    {
        $this->grade !== null
            ? $this->authorize('update', $this->grade)
            : $this->authorize('create', GradeCurricular::class);

        $dados = $this->validate();

        $this->recusarDuplicatas();

        DB::transaction(function () use ($dados) {
            if ($this->grade === null) {
                $proximaVersao = (int) GradeCurricular::query()
                    ->withTrashed()
                    ->where('curso_id', $dados['curso_id'])
                    ->max('versao') + 1;

                $this->grade = GradeCurricular::create([
                    'curso_id' => $dados['curso_id'],
                    'versao' => $proximaVersao,
                    'ano_vigencia' => $dados['ano_vigencia'],
                    'status' => StatusGrade::Rascunho,
                    'observacoes' => $dados['observacoes'] ?: null,
                    'criado_por' => auth()->id(),
                ]);
            } else {
                $this->grade->update([
                    'ano_vigencia' => $dados['ano_vigencia'],
                    'observacoes' => $dados['observacoes'] ?: null,
                ]);
            }

            // O rascunho é reescrito por inteiro; versões em uso nunca
            // chegam aqui, então nenhum histórico é perdido.
            $this->grade->disciplinas()->delete();

            foreach ($this->itens as $item) {
                $this->grade->disciplinas()->create([
                    'disciplina_id' => (int) $item['disciplina_id'],
                    'ano_curso' => (int) $item['ano_curso'],
                    'carga_horaria' => (int) $item['carga_horaria'],
                ]);
            }
        });

        session()->flash('sucesso', "Grade versão {$this->grade->versao} salva como rascunho.");

        $this->redirectRoute('grades.show', $this->grade, navigate: true);
    }

    /** A mesma disciplina não pode aparecer duas vezes no mesmo ano. */
    protected function recusarDuplicatas(): void
    {
        $vistos = [];

        foreach ($this->itens as $indice => $item) {
            $chave = $item['disciplina_id'].'-'.$item['ano_curso'];

            if (isset($vistos[$chave])) {
                throw ValidationException::withMessages([
                    "itens.{$indice}.disciplina_id" => 'Esta disciplina já está neste ano da grade.',
                ]);
            }

            $vistos[$chave] = true;
        }
    }

    protected function duracaoDoCurso(): int
    {
        return (int) (Curso::query()->find($this->curso_id)?->duracao_anos ?? 3);
    }

    public function render(): View
    {
        $usuario = auth()->user();

        return view('grades.formulario', [
            'cursosDisponiveis' => Curso::query()->visivelPara($usuario)->orderBy('nome')->get(),
            'disciplinasDisponiveis' => Disciplina::query()->visivelPara($usuario)->orderBy('nome')->get(),
            'duracao' => $this->duracaoDoCurso(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->grade === null ? 'Nova grade curricular' : "Editar grade v{$this->grade->versao}",
            'subtitulo' => $this->grade?->curso?->nome,
        ]);
    }
}
