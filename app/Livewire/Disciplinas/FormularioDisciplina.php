<?php

namespace App\Livewire\Disciplinas;

use App\Enums\StatusRegistro;
use App\Models\Curso;
use App\Models\Disciplina;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * A disciplina pertence a um curso e é cursada em um período dele.
 * Mudar o período aqui não altera turmas já abertas: elas seguem na foto
 * da grade que congelaram até que uma nova versão seja publicada.
 */
class FormularioDisciplina extends Component
{
    use AuthorizesRequests;

    public ?Disciplina $disciplina = null;

    public ?int $curso_id = null;

    public string $nome = '';

    public string $codigo = '';

    public int $periodo = 1;

    public int $carga_horaria = 80;

    public string $status = 'ativo';

    public function mount(?Disciplina $disciplina = null): void
    {
        if ($disciplina?->exists) {
            $this->authorize('update', $disciplina);

            $this->disciplina = $disciplina;
            $this->curso_id = $disciplina->curso_id;
            $this->nome = $disciplina->nome;
            $this->codigo = $disciplina->codigo;
            $this->periodo = $disciplina->periodo;
            $this->carga_horaria = $disciplina->carga_horaria;
            $this->status = $disciplina->status->value;

            return;
        }

        $this->authorize('create', Disciplina::class);

        $this->curso_id = (int) request()->query('curso') ?: null;
        $this->periodo = max(1, (int) request()->query('periodo'));
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $usuario = auth()->user();

        return [
            'curso_id' => [
                'required',
                Rule::exists('cursos', 'id')->where(
                    fn ($consulta) => $consulta->whereIn('eixo_id', $usuario->eixoIds())
                ),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('disciplinas', 'codigo')
                    ->where(fn ($consulta) => $consulta->where('curso_id', $this->curso_id))
                    ->ignore($this->disciplina?->getKey()),
            ],
            // Não existe 3º período em um curso de dois anos.
            'periodo' => ['required', 'integer', 'min:1', 'max:'.$this->duracaoDoCurso()],
            'carga_horaria' => ['required', 'integer', 'min:1', 'max:2000'],
            'status' => ['required', Rule::in(StatusRegistro::valores())],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'curso_id' => 'curso',
            'nome' => 'nome',
            'codigo' => 'código',
            'periodo' => 'período',
            'carga_horaria' => 'carga horária',
            'status' => 'status',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'codigo.unique' => 'Já existe uma disciplina com este código neste curso.',
            'periodo.max' => 'O curso selecionado não tem esse período.',
        ];
    }

    public function salvar(): void
    {
        $this->disciplina !== null
            ? $this->authorize('update', $this->disciplina)
            : $this->authorize('create', Disciplina::class);

        $dados = $this->validate();

        $mudouPeriodo = $this->disciplina !== null
            && (int) $this->disciplina->periodo !== (int) $dados['periodo'];

        $this->disciplina === null
            ? Disciplina::create($dados)
            : $this->disciplina->update($dados);

        session()->flash('sucesso', $mudouPeriodo
            ? 'Disciplina salva. As turmas já abertas seguem na grade que congelaram — publique uma nova versão para que a mudança valha nas próximas.'
            : 'Disciplina salva com sucesso.');

        $this->redirectRoute('disciplinas.index', navigate: true);
    }

    protected function duracaoDoCurso(): int
    {
        return (int) (Curso::query()->find($this->curso_id)?->duracao_anos ?? 3);
    }

    public function render(): View
    {
        return view('disciplinas.formulario', [
            'cursosDisponiveis' => Curso::query()->visivelPara(auth()->user())->orderBy('nome')->get(),
            'situacoes' => StatusRegistro::opcoes(),
            'duracao' => $this->duracaoDoCurso(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->disciplina === null ? 'Nova disciplina' : 'Editar disciplina',
            'subtitulo' => $this->disciplina?->nome,
        ]);
    }
}
