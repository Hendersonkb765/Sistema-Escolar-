<?php

namespace App\Livewire\Ausencias;

use App\Actions\Ausencias\CancelarAusenciaAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Ausencia;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

/** As ausências que o usuário informou: as próximas primeiro, depois as passadas. */
class MinhasAusencias extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public function mount(): void
    {
        $this->authorize('viewAny', Ausencia::class);
    }

    public function cancelar(int $id, CancelarAusenciaAction $action): void
    {
        $ausencia = Ausencia::query()->findOrFail($id);

        $this->authorize('cancelar', $ausencia);

        try {
            $action->executar($ausencia);
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso("Ausência de {$ausencia->data->format('d/m/Y')} cancelada. A coordenação verá o cancelamento.");
    }

    public function render(): View
    {
        $ausencias = Ausencia::query()
            ->where('professor_id', auth()->id())
            ->withCount('aulas')
            ->orderByDesc('data')
            ->get();

        [$proximas, $passadas] = $ausencias->partition(fn (Ausencia $a) => ! $a->data->isBefore(today()));

        return view('ausencias.minhas', [
            'proximas' => $proximas->sortBy('data')->values(),
            'passadas' => $passadas,
        ])->layout('components.layouts.app', [
            'titulo' => 'Minhas ausências',
            'subtitulo' => 'Os dias em que você avisou que não estará, e as aulas de cada um',
        ]);
    }
}
