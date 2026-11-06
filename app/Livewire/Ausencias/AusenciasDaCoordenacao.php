<?php

namespace App\Livewire\Ausencias;

use App\Actions\Ausencias\TomarCienciaAction;
use App\Enums\StatusAusencia;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Attributes\LembrarFiltro;
use App\Livewire\Concerns\LembraFiltros;
use App\Livewire\Concerns\Notifica;
use App\Models\Ausencia;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * As ausências que a coordenação acompanha: as que têm aula nas turmas
 * do Eixo de quem olha. Cada linha traz só as aulas daquele Eixo.
 *
 * Abre nas que esperam ciência, das mais próximas para as mais distantes.
 */
class AusenciasDaCoordenacao extends Component
{
    use AuthorizesRequests;
    use LembraFiltros;
    use Notifica;

    public const SITUACOES = ['pendente', 'ciente', 'cancelada', 'todas'];

    public const QUANDO = ['proximas', 'passadas', 'todas'];

    #[Url(as: 'situacao', except: 'pendente')]
    #[LembrarFiltro]
    public string $situacao = 'pendente';

    #[Url(as: 'quando', except: 'proximas')]
    #[LembrarFiltro]
    public string $quando = 'proximas';

    public function mount(): void
    {
        $this->authorize('acompanhar', Ausencia::class);

        // O endereço vem de fora: valor desconhecido volta ao padrão em
        // vez de virar um filtro que não filtra nada.
        $this->situacao = in_array($this->situacao, self::SITUACOES, true) ? $this->situacao : 'pendente';
        $this->quando = in_array($this->quando, self::QUANDO, true) ? $this->quando : 'proximas';
    }

    public function tomarCiencia(int $id, TomarCienciaAction $action): void
    {
        $ausencia = Ausencia::query()->with('professor:id,nome', 'cientePor:id,nome')->findOrFail($id);

        $this->authorize('tomarCiencia', $ausencia);

        try {
            $action->executar($ausencia, auth()->user());
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->notificarSucesso("Ciência registrada da ausência de {$ausencia->professor->nome} em {$ausencia->data->format('d/m/Y')}.");
    }

    public function render(): View
    {
        $usuario = auth()->user();
        $eixoIds = $usuario->eixoIds();

        $ausencias = Ausencia::query()
            ->daCoordenacaoDe($usuario)
            ->when($this->situacao !== 'todas', fn (Builder $q) => $q->where('status', $this->situacao))
            ->when($this->quando === 'proximas', fn (Builder $q) => $q->whereDate('data', '>=', today()))
            ->when($this->quando === 'passadas', fn (Builder $q) => $q->whereDate('data', '<', today()))
            ->with([
                'professor:id,nome',
                'cientePor:id,nome',
                'aulas' => fn ($q) => $q->nosEixos($eixoIds)
                    ->with('turma:id,nome', 'disciplina:id,nome')
                    ->orderBy('hora_inicio'),
            ])
            ->orderBy('data', $this->quando === 'passadas' ? 'desc' : 'asc')
            ->get();

        return view('ausencias.coordenacao', [
            'ausencias' => $ausencias,
            'situacoes' => [
                'pendente' => StatusAusencia::Pendente->rotulo(),
                'ciente' => StatusAusencia::Ciente->rotulo(),
                'cancelada' => StatusAusencia::Cancelada->rotulo(),
                'todas' => 'Todas',
            ],
            'periodos' => ['proximas' => 'Próximas', 'passadas' => 'Passadas', 'todas' => 'Todas as datas'],
        ])->layout('components.layouts.app', [
            'titulo' => 'Ausências de professores',
            'subtitulo' => 'Os dias em que professores avisaram que não estarão, com as aulas nas turmas do seu Eixo',
        ]);
    }
}
