<?php

namespace App\Livewire\Ausencias;

use App\Actions\Ausencias\PreverAulasDoDiaAction;
use App\Actions\Ausencias\RegistrarAusenciaAction;
use App\Exceptions\RegraDeNegocioException;
use App\Livewire\Concerns\Notifica;
use App\Models\Ausencia;
use App\Support\AulaPrevista;
use App\Support\AulasDoDia;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Carbon;
use Livewire\Component;

/**
 * O professor escolhe o dia e a tela traz as aulas dele naquele dia, do
 * quadro de horários, cada uma com a aula do escopo sugerida. Ele confere,
 * troca a sugestão se for o caso e escreve o que planejou.
 */
class InformarAusencia extends Component
{
    use AuthorizesRequests;
    use Notifica;

    public string $data = '';

    public string $motivo = '';

    /**
     * O plano de cada aula, pelo id do quadro de horários.
     *
     * @var array<int|string, array{escopo_aula_id: string, conteudo: string}>
     */
    public array $planos = [];

    public function mount(): void
    {
        $this->authorize('create', Ausencia::class);
    }

    /** Trocar o dia troca as aulas: o plano recomeça pelas sugestões. */
    public function updatedData(): void
    {
        $this->resetErrorBag();

        $this->planos = $this->previsao()?->aulas
            ->mapWithKeys(fn (AulaPrevista $aula) => [$aula->quadro->id => [
                'escopo_aula_id' => (string) ($aula->sugerida?->id ?? ''),
                'conteudo' => '',
            ]])
            ->all() ?? [];
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'motivo' => ['nullable', 'string', 'max:500'],
            'planos.*.conteudo' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return [
            'data.after_or_equal' => 'A ausência é informada com antecedência: escolha hoje ou uma data adiante.',
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return ['data' => 'data', 'motivo' => 'motivo', 'planos.*.conteudo' => 'conteúdo planejado'];
    }

    public function informar(RegistrarAusenciaAction $action): void
    {
        $this->authorize('create', Ausencia::class);

        $this->validate();

        $previsao = $this->previsao();
        $temPendencia = false;

        foreach ($previsao?->aulas ?? [] as $aula) {
            $pendencia = $aula->pendencia(...$this->planoDe($aula));

            if ($pendencia !== null) {
                $this->addError("planos.{$aula->quadro->id}", $pendencia);
                $temPendencia = true;
            }
        }

        if ($temPendencia) {
            return;
        }

        try {
            $ausencia = $action->executar(
                auth()->user(),
                $this->dataEscolhida(),
                $this->motivo,
                collect($previsao?->aulas ?? [])
                    ->mapWithKeys(fn (AulaPrevista $aula) => [$aula->quadro->id => array_combine(
                        ['escopo_aula_id', 'conteudo'], $this->planoDe($aula),
                    )])
                    ->all(),
            );
        } catch (RegraDeNegocioException $excecao) {
            $this->notificarErro($excecao->getMessage());

            return;
        }

        $this->flashSucesso(
            "Ausência de {$ausencia->data->format('d/m/Y')} informada à coordenação, com {$ausencia->aulas()->count()} aula(s)."
        );

        $this->redirectRoute('ausencias.show', $ausencia, navigate: true);
    }

    /** @return array{0: ?int, 1: ?string} */
    protected function planoDe(AulaPrevista $aula): array
    {
        $plano = $this->planos[$aula->quadro->id] ?? [];
        $escopo = (string) ($plano['escopo_aula_id'] ?? '');

        return [$escopo === '' ? null : (int) $escopo, $plano['conteudo'] ?? null];
    }

    /**
     * As aulas do dia escolhido, ou null enquanto não há um dia válido
     * adiante — data passada é dita pela validação, não por uma lista.
     */
    protected function previsao(): ?AulasDoDia
    {
        $data = $this->dataEscolhida();

        if ($data === null || $data->isBefore(today())) {
            return null;
        }

        return app(PreverAulasDoDiaAction::class)->executar(auth()->user(), $data);
    }

    /** O dia do campo, se for uma data de verdade no formato do campo. */
    protected function dataEscolhida(): ?Carbon
    {
        try {
            $data = Carbon::createFromFormat('!Y-m-d', $this->data);
        } catch (InvalidFormatException) {
            return null;
        }

        return $data !== null && $data->format('Y-m-d') === $this->data ? $data : null;
    }

    public function render(): View
    {
        $previsao = $this->previsao();

        $pendencias = collect($previsao?->aulas ?? [])
            ->mapWithKeys(fn (AulaPrevista $aula) => [$aula->quadro->id => $aula->pendencia(...$this->planoDe($aula))]);

        return view('ausencias.informar', [
            'previsao' => $previsao,
            'pendencias' => $pendencias,
            'dataPassada' => $this->dataEscolhida()?->isBefore(today()) ?? false,
        ])->layout('components.layouts.app', [
            'titulo' => 'Informar ausência',
            'subtitulo' => 'Avise a coordenação com antecedência, com o que você planejou para cada aula',
        ]);
    }
}
