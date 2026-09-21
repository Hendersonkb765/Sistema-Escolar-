<?php

namespace App\Livewire\Cursos;

use App\Enums\StatusRegistro;
use App\Models\Curso;
use App\Models\Eixo;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class FormularioCurso extends Component
{
    use AuthorizesRequests;

    public ?Curso $curso = null;

    public ?int $eixo_id = null;

    public string $nome = '';

    public string $codigo = '';

    public int $duracao_anos = 3;

    public string $status = 'ativo';

    public function mount(?Curso $curso = null): void
    {
        if ($curso?->exists) {
            $this->authorize('update', $curso);

            $this->curso = $curso;
            $this->eixo_id = $curso->eixo_id;
            $this->nome = $curso->nome;
            $this->codigo = $curso->codigo;
            $this->duracao_anos = $curso->duracao_anos;
            $this->status = $curso->status->value;

            return;
        }

        $this->authorize('create', Curso::class);

        // Com um único Eixo no escopo, não faz sentido perguntar.
        $this->eixo_id = auth()->user()->eixoIds()[0] ?? null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $eixosDoUsuario = auth()->user()->eixoIds();

        return [
            // O Eixo precisa estar no escopo de quem edita — sem isso,
            // um id adivinhado moveria o curso para fora do escopo.
            'eixo_id' => [
                'required',
                Rule::exists('eixos', 'id')->where(
                    fn ($consulta) => $consulta->whereIn('id', $eixosDoUsuario)
                ),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('cursos', 'codigo')
                    ->where(fn ($consulta) => $consulta->where('eixo_id', $this->eixo_id))
                    ->ignore($this->curso?->getKey()),
            ],
            'duracao_anos' => ['required', 'integer', Rule::in([2, 3])],
            'status' => ['required', Rule::in(StatusRegistro::valores())],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'eixo_id' => 'eixo',
            'nome' => 'nome',
            'codigo' => 'código',
            'duracao_anos' => 'duração em anos',
            'status' => 'status',
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'codigo.unique' => 'Já existe um curso com este código neste eixo.',
        ];
    }

    public function salvar(): void
    {
        $this->curso !== null
            ? $this->authorize('update', $this->curso)
            : $this->authorize('create', Curso::class);

        $dados = $this->validate();

        if ($this->curso === null) {
            $this->curso = Curso::create($dados);
        } else {
            $this->impedirReducaoDeDuracaoComTurmas($dados['duracao_anos']);
            $this->curso->update($dados);
        }

        session()->flash('sucesso', 'Curso salvo com sucesso.');

        $this->redirectRoute('cursos.index', navigate: true);
    }

    /**
     * Encurtar o curso deixaria turmas e disciplinas em um período que
     * passou a não existir.
     */
    protected function impedirReducaoDeDuracaoComTurmas(int $novaDuracao): void
    {
        $periodoMaisAvancado = max(
            (int) $this->curso->turmas()->max('periodo'),
            (int) $this->curso->disciplinas()->max('periodo'),
        );

        if ($periodoMaisAvancado > $novaDuracao) {
            throw ValidationException::withMessages([
                'duracao_anos' => "Há turmas ou disciplinas no {$periodoMaisAvancado}º período; a duração não pode ser menor que isso.",
            ]);
        }
    }

    public function render(): View
    {
        return view('cursos.formulario', [
            'eixosDisponiveis' => Eixo::query()->visivelPara(auth()->user())->orderBy('nome')->get(),
            'situacoes' => StatusRegistro::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->curso === null ? 'Novo curso' : 'Editar curso',
            'subtitulo' => $this->curso?->nome,
        ]);
    }
}
