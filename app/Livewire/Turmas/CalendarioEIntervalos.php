<?php

namespace App\Livewire\Turmas;

use App\Actions\Academico\DefinirCalendarioDaTurmaAction;
use App\Enums\Bimestre;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\BimestreLetivo;
use App\Models\Turma;
use App\Support\IntervaloDoDia;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * O calendário e os intervalos da turma.
 *
 * O calendário pode ter exceção aqui. O caso comum é não ter, e a tela
 * abre dizendo isso — "segue o calendário da escola", com as datas. Só
 * quem precisa abre o formulário, já preenchido com o padrão.
 *
 * Os intervalos só se consultam: quem decide a quais turmas cada um se
 * aplica é o PAEET Admin, em Configuração da escola.
 */
class CalendarioEIntervalos extends Component
{
    use AuthorizesRequests;
    use Notifica;

    #[Locked]
    public Turma $turma;

    #[Locked]
    public bool $editandoCalendario = false;

    /** @var array<int, array{inicio: string, fim: string}> */
    public array $datas = [];

    public function mount(Turma $turma): void
    {
        $this->authorize('update', $turma);

        $this->turma = $turma;
    }

    // --- Calendário ---------------------------------------------------------

    /** Abre o formulário com as datas em uso: as próprias, ou as da escola para ajustar. */
    public function definirCalendario(): void
    {
        $this->authorize('update', $this->turma);

        $emUso = $this->turma->bimestresProprios()->get();

        if ($emUso->isEmpty()) {
            $emUso = BimestreLetivo::query()->where('periodo_letivo', $this->turma->periodo_letivo)->get();
        }

        $porBimestre = $emUso->keyBy(fn ($b) => $b->bimestre->value);

        $this->datas = collect(Bimestre::cases())->mapWithKeys(fn (Bimestre $b) => [$b->value => [
            'inicio' => $porBimestre->get($b->value)?->inicio->format('Y-m-d') ?? '',
            'fim' => $porBimestre->get($b->value)?->fim->format('Y-m-d') ?? '',
        ]])->all();

        $this->editandoCalendario = true;
        $this->resetErrorBag();
    }

    public function salvarCalendario(DefinirCalendarioDaTurmaAction $action): void
    {
        $this->authorize('update', $this->turma);

        $this->validate([
            'datas.*.inicio' => ['nullable', 'date_format:Y-m-d', 'required_with:datas.*.fim'],
            'datas.*.fim' => ['nullable', 'date_format:Y-m-d', 'required_with:datas.*.inicio', 'after_or_equal:datas.*.inicio'],
        ], [], collect(Bimestre::cases())->flatMap(fn (Bimestre $b) => [
            "datas.{$b->value}.inicio" => "início do {$b->rotulo()}",
            "datas.{$b->value}.fim" => "fim do {$b->rotulo()}",
        ])->all());

        try {
            $action->executar($this->turma, collect($this->datas)
                ->map(fn (array $par) => $par['inicio'] === '' ? null : $par)
                ->all());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->editandoCalendario = false;
        $this->notificarSucesso("A turma {$this->turma->nome} passa a seguir o calendário próprio.");
    }

    public function voltarAoCalendarioDaEscola(DefinirCalendarioDaTurmaAction $action): void
    {
        $this->authorize('update', $this->turma);

        $action->voltarAoDaEscola($this->turma);

        $this->editandoCalendario = false;
        $this->notificarSucesso("A turma {$this->turma->nome} volta a seguir o calendário da escola.");
    }

    public function cancelarCalendario(): void
    {
        $this->editandoCalendario = false;
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $bimestresProprios = $this->turma->bimestresProprios()->get();

        return view('turmas.calendario-e-intervalos', [
            'bimestresProprios' => $bimestresProprios,
            'bimestresDaEscola' => BimestreLetivo::query()
                ->where('periodo_letivo', $this->turma->periodo_letivo)
                ->orderBy('bimestre')
                ->get(),
            'intervalos' => IntervaloDoDia::daTurma($this->turma),
        ])->layout('components.layouts.app', [
            'titulo' => 'Calendário e intervalos',
            'subtitulo' => "Turma {$this->turma->nome} · {$this->turma->periodo_letivo}",
        ]);
    }
}
