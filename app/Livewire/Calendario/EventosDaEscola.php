<?php

namespace App\Livewire\Calendario;

use App\Actions\Calendario\ImportarEventosAction;
use App\Enums\TipoDeEvento;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\EventoEscolar;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Os eventos e feriados do ano, dentro do Calendário letivo. Todos veem,
 * para saber; o PAEET Admin cadastra.
 *
 * O ano vem do seletor da tela: trocar de ano monta este componente de
 * novo (a chave dele é o ano).
 */
class EventosDaEscola extends Component
{
    use AuthorizesRequests;
    use Notifica;
    use WithFileUploads;

    #[Locked]
    public string $ano = '';

    #[Locked]
    public ?int $editando = null;

    public string $titulo = '';

    public string $tipo = 'evento';

    public string $inicio = '';

    public string $fim = '';

    public string $descricao = '';

    public ?TemporaryUploadedFile $planilha = null;

    /** O que a importação faria, enquanto não é confirmada nem descartada. */
    public ?array $relatorio = null;

    public function mount(string $ano): void
    {
        $this->authorize('viewAny', EventoEscolar::class);

        $this->ano = $ano;
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'titulo' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(TipoDeEvento::valores())],
            'inicio' => ['required', 'date_format:Y-m-d'],
            // Em branco é um dia só.
            'fim' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:inicio'],
            'descricao' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['titulo' => 'título', 'tipo' => 'tipo', 'inicio' => 'data', 'fim' => 'até', 'descricao' => 'descrição'];
    }

    public function editar(int $id): void
    {
        $this->authorize('gerenciar', EventoEscolar::class);

        $evento = EventoEscolar::query()->findOrFail($id);

        $this->editando = $evento->id;
        $this->titulo = $evento->titulo;
        $this->tipo = $evento->tipo->value;
        $this->inicio = $evento->inicio->format('Y-m-d');
        $this->fim = $evento->inicio->isSameDay($evento->fim) ? '' : $evento->fim->format('Y-m-d');
        $this->descricao = (string) $evento->descricao;
        $this->resetErrorBag();
    }

    public function cancelarEdicao(): void
    {
        $this->limpar();
    }

    public function salvar(): void
    {
        $this->authorize('gerenciar', EventoEscolar::class);

        $dados = $this->validate();

        $campos = [
            'titulo' => trim($dados['titulo']),
            'tipo' => $dados['tipo'],
            'inicio' => $dados['inicio'],
            'fim' => $dados['fim'] ?: $dados['inicio'],
            'descricao' => trim((string) $dados['descricao']) ?: null,
        ];

        $evento = $this->editando !== null
            ? tap(EventoEscolar::query()->findOrFail($this->editando))->update($campos)
            : EventoEscolar::query()->create($campos);

        $this->notificarSucesso("{$evento->tipo->rotulo()} \"{$evento->titulo}\" em {$evento->periodo()} salvo no calendário.");
        $this->limpar();
    }

    public function remover(int $id): void
    {
        $this->authorize('gerenciar', EventoEscolar::class);

        $evento = EventoEscolar::query()->findOrFail($id);
        $evento->delete();

        if ($this->editando === $id) {
            $this->limpar();
        }

        $this->notificarSucesso("\"{$evento->titulo}\" removido do calendário.");
    }

    // --- Importação --------------------------------------------------------

    public function conferirImportacao(ImportarEventosAction $action): void
    {
        $this->authorize('gerenciar', EventoEscolar::class);

        $this->validarPlanilha();

        try {
            $this->relatorio = $action->conferir($this->planilha->getRealPath(), auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->relatorio = null;
            $this->notificarErro($excecao->getMessage());
        }
    }

    public function confirmarImportacao(ImportarEventosAction $action): void
    {
        $this->authorize('gerenciar', EventoEscolar::class);

        if ($this->relatorio === null) {
            $this->notificarErro('Confira a planilha antes de confirmar.');

            return;
        }

        $this->validarPlanilha();

        try {
            $quantos = $action->executar($this->planilha->getRealPath(), auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->reset('relatorio', 'planilha');
        $this->notificarSucesso("{$quantos} evento(s) importado(s) para o calendário.");
    }

    public function descartarImportacao(): void
    {
        $this->reset('relatorio', 'planilha');
        $this->notificarSucesso('Importação descartada. Nada foi gravado.');
    }

    /** Trocar o arquivo invalida a conferência do anterior. */
    public function updatedPlanilha(): void
    {
        $this->relatorio = null;
    }

    private function validarPlanilha(): void
    {
        $this->validate(
            ['planilha' => ['required', 'file', 'mimes:xlsx,xls,ods,csv,txt', 'max:2048']],
            [
                'planilha.required' => 'Escolha a planilha de eventos.',
                'planilha.mimes' => 'Envie a planilha em XLSX, ODS ou CSV.',
                'planilha.max' => 'O arquivo passa de 2 MB.',
            ],
        );
    }

    protected function limpar(): void
    {
        $this->reset(['editando', 'titulo', 'tipo', 'inicio', 'fim', 'descricao']);
        $this->resetErrorBag();
    }

    public function render(): View
    {
        // O ano letivo é um texto ("2026"); sem ser um ano, não há período para listar.
        $ehAno = preg_match('/^\d{4}$/', $this->ano) === 1;

        return view('calendario.eventos', [
            'eventos' => $ehAno
                ? EventoEscolar::query()
                    ->entre(Carbon::create((int) $this->ano)->startOfYear(), Carbon::create((int) $this->ano)->endOfYear())
                    ->orderBy('inicio')
                    ->get()
                : collect(),
            'tipos' => TipoDeEvento::cases(),
            'podeEditar' => auth()->user()->can('gerenciar', EventoEscolar::class),
        ]);
    }
}
