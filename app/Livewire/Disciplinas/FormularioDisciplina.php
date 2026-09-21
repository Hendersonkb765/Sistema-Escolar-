<?php

namespace App\Livewire\Disciplinas;

use App\Enums\StatusRegistro;
use App\Models\Disciplina;
use App\Models\Eixo;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\Rule;
use Livewire\Component;

class FormularioDisciplina extends Component
{
    use AuthorizesRequests;

    public ?Disciplina $disciplina = null;

    public ?int $eixo_id = null;

    public string $nome = '';

    public string $codigo = '';

    public string $status = 'ativo';

    public function mount(?Disciplina $disciplina = null): void
    {
        if ($disciplina?->exists) {
            $this->authorize('update', $disciplina);

            $this->disciplina = $disciplina;
            $this->eixo_id = $disciplina->eixo_id;
            $this->nome = $disciplina->nome;
            $this->codigo = $disciplina->codigo;
            $this->status = $disciplina->status->value;

            return;
        }

        $this->authorize('create', Disciplina::class);

        $this->eixo_id = auth()->user()->eixoIds()[0] ?? null;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        $eixosDoUsuario = auth()->user()->eixoIds();

        return [
            'eixo_id' => [
                'required',
                Rule::exists('eixos', 'id')->where(
                    fn ($consulta) => $consulta->whereIn('id', $eixosDoUsuario)
                ),
            ],
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('disciplinas', 'codigo')
                    ->where(fn ($consulta) => $consulta->where('eixo_id', $this->eixo_id))
                    ->ignore($this->disciplina?->getKey()),
            ],
            'status' => ['required', Rule::in(StatusRegistro::valores())],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['eixo_id' => 'eixo', 'nome' => 'nome', 'codigo' => 'código', 'status' => 'status'];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return ['codigo.unique' => 'Já existe uma disciplina com este código neste eixo.'];
    }

    public function salvar(): void
    {
        $this->disciplina !== null
            ? $this->authorize('update', $this->disciplina)
            : $this->authorize('create', Disciplina::class);

        $dados = $this->validate();

        $this->disciplina === null
            ? Disciplina::create($dados)
            : $this->disciplina->update($dados);

        session()->flash('sucesso', 'Disciplina salva com sucesso.');

        $this->redirectRoute('disciplinas.index', navigate: true);
    }

    public function render(): View
    {
        return view('disciplinas.formulario', [
            'eixosDisponiveis' => Eixo::query()->visivelPara(auth()->user())->orderBy('nome')->get(),
            'situacoes' => StatusRegistro::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->disciplina === null ? 'Nova disciplina' : 'Editar disciplina',
            'subtitulo' => $this->disciplina?->nome,
        ]);
    }
}
