<?php

namespace App\Actions\Ausencias;

use App\Enums\StatusAusencia;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Ausencia;
use App\Models\User;
use App\Support\AulaPrevista;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Grava a ausência do professor com todas as aulas que ele daria no dia.
 *
 * As aulas não vêm do formulário: são previstas de novo aqui, a partir
 * do quadro. Do formulário vem só o que o professor decidiu para cada
 * uma — a aula do escopo e o conteúdo escrito.
 *
 * - a data é hoje ou adiante: a ausência é avisada com antecedência;
 * - não há outra ausência ativa no mesmo dia;
 * - o dia tem aula no quadro;
 * - cada aula tem a aula do escopo ou o conteúdo descrito.
 */
class RegistrarAusenciaAction
{
    public function __construct(private PreverAulasDoDiaAction $prever) {}

    /**
     * @param  array<int, array{escopo_aula_id: ?int, conteudo: ?string}>  $planos  Por id do quadro de horários.
     */
    public function executar(User $professor, CarbonInterface $data, ?string $motivo, array $planos): Ausencia
    {
        $data = $data->copy()->startOfDay();

        if ($data->isBefore(today())) {
            throw RegraDeNegocioException::porque(
                "{$data->format('d/m/Y')} já passou. A ausência é informada com antecedência."
            );
        }

        $existente = Ausencia::query()
            ->where('professor_id', $professor->id)
            ->whereDate('data', $data)
            ->where('status', '!=', StatusAusencia::Cancelada)
            ->exists();

        if ($existente) {
            throw RegraDeNegocioException::porque(
                "Você já informou ausência em {$data->format('d/m/Y')}. Cancele a anterior para informar de novo."
            );
        }

        $previstas = $this->prever->executar($professor, $data);

        if ($previstas->vazio()) {
            throw RegraDeNegocioException::porque($previstas->motivo);
        }

        $pendencias = $previstas->aulas
            ->map(fn (AulaPrevista $aula) => $aula->pendencia(
                $planos[$aula->quadro->id]['escopo_aula_id'] ?? null,
                $planos[$aula->quadro->id]['conteudo'] ?? null,
            ))
            ->filter();

        if ($pendencias->isNotEmpty()) {
            throw RegraDeNegocioException::porque($pendencias->implode(' '));
        }

        return DB::transaction(function () use ($professor, $data, $motivo, $previstas, $planos) {
            $ausencia = Ausencia::query()->create([
                'professor_id' => $professor->id,
                'data' => $data,
                'motivo' => trim((string) $motivo) === '' ? null : trim($motivo),
                'status' => StatusAusencia::Pendente,
            ]);

            foreach ($previstas->aulas as $aula) {
                $plano = $planos[$aula->quadro->id] ?? [];
                $escopo = $aula->opcoes->firstWhere('id', $plano['escopo_aula_id'] ?? null);
                $conteudo = trim((string) ($plano['conteudo'] ?? ''));

                $ausencia->aulas()->create([
                    'quadro_horario_id' => $aula->quadro->id,
                    'turma_id' => $aula->quadro->turma_id,
                    'disciplina_id' => $aula->quadro->disciplina_id,
                    'aula_numero' => $aula->quadro->aula_numero,
                    'hora_inicio' => $aula->quadro->horaInicio(),
                    'hora_fim' => $aula->quadro->horaFim(),
                    'escopo_aula_id' => $escopo?->id,
                    'escopo_titulo' => $escopo?->titulo_aula,
                    'conteudo_planejado' => $conteudo === '' ? null : $conteudo,
                ]);
            }

            return $ausencia;
        });
    }
}
