<?php

namespace App\Livewire\Eixos;

use App\Enums\StatusRegistro;
use App\Models\Eixo;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class FormularioEixo extends Component
{
    use AuthorizesRequests;

    public ?Eixo $eixo = null;

    public string $nome = '';

    public string $codigo = '';

    public string $status = 'ativo';

    public function mount(?Eixo $eixo = null): void
    {
        if ($eixo?->exists) {
            $this->authorize('update', $eixo);

            $this->eixo = $eixo;
            $this->nome = $eixo->nome;
            $this->codigo = $eixo->codigo;
            $this->status = $eixo->status->value;

            return;
        }

        $this->authorize('create', Eixo::class);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'nome' => ['required', 'string', 'max:255'],
            'codigo' => [
                'required', 'string', 'max:30',
                Rule::unique('eixos', 'codigo')->ignore($this->eixo?->getKey()),
            ],
            'status' => ['required', Rule::in(StatusRegistro::valores())],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['nome' => 'nome', 'codigo' => 'código', 'status' => 'status'];
    }

    public function salvar(): void
    {
        $this->eixo !== null
            ? $this->authorize('update', $this->eixo)
            : $this->authorize('create', Eixo::class);

        $dados = $this->validate();

        DB::transaction(function () use ($dados) {
            if ($this->eixo === null) {
                $this->eixo = Eixo::create($dados);

                // Quem cria o Eixo passa a enxergá-lo — sem isso o próprio
                // autor ficaria fora do escopo que acabou de abrir.
                auth()->user()->eixos()->syncWithoutDetaching([$this->eixo->getKey()]);
                auth()->user()->esquecerEscopo();
            } else {
                $this->eixo->update($dados);
            }
        });

        session()->flash('sucesso', 'Eixo salvo com sucesso.');

        $this->redirectRoute('eixos.index', navigate: true);
    }

    public function render(): View
    {
        return view('eixos.formulario', [
            'situacoes' => StatusRegistro::opcoes(),
        ])->layout('components.layouts.app', [
            'titulo' => $this->eixo === null ? 'Novo eixo' : 'Editar eixo',
            'subtitulo' => $this->eixo?->nome,
        ]);
    }
}
