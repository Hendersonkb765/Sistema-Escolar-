<?php

namespace App\Livewire\Ausencias;

use App\Actions\Ausencias\TomarCienciaAction;
use App\Enums\StatusAusencia;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Ausencia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * A ausência com cada aula do dia e o que foi planejado para ela.
 *
 * O professor vê todas as suas aulas. A coordenação vê só as das turmas
 * do seu Eixo — e a tela diz quantas ficaram de fora, para que ninguém
 * pense que o dia tem menos aula do que tem.
 */
class DetalheAusencia extends Component
{
    use AuthorizesRequests;
    use Notifica;

    #[Locked]
    public Ausencia $ausencia;

    public function mount(Ausencia $ausencia): void
    {
        $this->authorize('view', $ausencia);

        $this->ausencia = $ausencia;
    }

    public function tomarCiencia(TomarCienciaAction $action): void
    {
        $this->authorize('tomarCiencia', $this->ausencia);

        try {
            $action->executar($this->ausencia->load('professor:id,nome', 'cientePor:id,nome'), auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso("Ciência registrada. {$this->ausencia->professor->nome} verá que a coordenação recebeu o aviso.");
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $ehDele = (int) $this->ausencia->professor_id === $usuario->id;

        $this->ausencia->load([
            'professor:id,nome',
            'cientePor:id,nome',
            'aulas' => fn ($q) => $q
                ->when(! $ehDele, fn (Builder $aulas) => $aulas->nosEixos($usuario->eixoIds()))
                ->with('turma:id,nome', 'disciplina:id,nome')
                ->orderBy('hora_inicio'),
        ]);

        return view('ausencias.detalhe', [
            'ehDele' => $ehDele,
            'deOutrosEixos' => $ehDele ? 0 : $this->ausencia->aulas()->count() - $this->ausencia->aulas->count(),
            'aguardando' => $this->ausencia->status === StatusAusencia::Pendente,
        ])->layout('components.layouts.app', [
            'titulo' => "Ausência de {$this->ausencia->data->format('d/m/Y')}",
            'subtitulo' => $this->ausencia->professor->nome,
        ]);
    }
}
