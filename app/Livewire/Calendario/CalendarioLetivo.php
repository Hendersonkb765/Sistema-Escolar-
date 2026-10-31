<?php

namespace App\Livewire\Calendario;

use App\Actions\Calendario\SalvarCalendarioAction;
use App\Enums\Bimestre;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\BimestreLetivo;
use App\Models\Turma;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

class CalendarioLetivo extends Component
{
    use AuthorizesRequests;
    use Notifica;

    #[Url(as: 'ano', except: '')]
    public string $ano = '';

    /** @var array<int, array{inicio: string, fim: string}> */
    public array $datas = [];

    public function mount(?string $ano = null): void
    {
        $this->authorize('viewAny', BimestreLetivo::class);

        $this->ano = $ano ?: ($this->ano ?: (string) now()->format('Y'));
        $this->carregar();
    }

    public function updatedAno(): void
    {
        $this->resetErrorBag();
        $this->carregar();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'ano' => ['required', 'string', 'max:20'],
            // `date_format`, e não `date`: "03/02/2026" passaria como 2 de
            // março. O campo de data do navegador manda AAAA-MM-DD.
            'datas.*.inicio' => ['nullable', 'date_format:Y-m-d', 'required_with:datas.*.fim'],
            'datas.*.fim' => ['nullable', 'date_format:Y-m-d', 'required_with:datas.*.inicio', 'after_or_equal:datas.*.inicio'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        $nomes = ['ano' => 'ano letivo'];

        foreach (Bimestre::cases() as $bimestre) {
            $nomes["datas.{$bimestre->value}.inicio"] = "início do {$bimestre->rotulo()}";
            $nomes["datas.{$bimestre->value}.fim"] = "fim do {$bimestre->rotulo()}";
        }

        return $nomes;
    }

    public function salvar(SalvarCalendarioAction $action): void
    {
        $this->authorize('gerenciar', BimestreLetivo::class);

        $this->validate();

        $datas = [];

        foreach (Bimestre::cases() as $bimestre) {
            $par = $this->datas[$bimestre->value] ?? ['inicio' => '', 'fim' => ''];

            $datas[$bimestre->value] = ($par['inicio'] ?? '') === '' ? null : $par;
        }

        try {
            $action->executar($this->ano, $datas);
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso("Calendário letivo de {$this->ano} salvo.");
    }

    protected function carregar(): void
    {
        $gravados = BimestreLetivo::query()
            ->where('periodo_letivo', $this->ano)
            ->get()
            ->keyBy(fn (BimestreLetivo $b) => $b->bimestre->value);

        $this->datas = [];

        foreach (Bimestre::cases() as $bimestre) {
            $gravado = $gravados->get($bimestre->value);

            $this->datas[$bimestre->value] = [
                'inicio' => $gravado?->inicio->toDateString() ?? '',
                'fim' => $gravado?->fim->toDateString() ?? '',
            ];
        }
    }

    public function render(): View
    {
        $anos = Turma::query()->distinct()->pluck('periodo_letivo')
            ->merge(BimestreLetivo::query()->distinct()->pluck('periodo_letivo'))
            ->push((string) now()->format('Y'), (string) now()->addYear()->format('Y'), $this->ano)
            ->filter()
            ->unique()
            ->sortDesc()
            ->values();

        return view('calendario.letivo', [
            'anos' => $anos,
            'bimestres' => Bimestre::cases(),
            'podeEditar' => auth()->user()->can('gerenciar', BimestreLetivo::class),
        ])->layout('components.layouts.app', [
            'titulo' => 'Calendário letivo',
            'subtitulo' => 'As datas de cada bimestre, que ligam o dia da aula à semana do escopo-sequência',
        ]);
    }
}
