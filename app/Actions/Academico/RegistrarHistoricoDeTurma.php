<?php

namespace App\Actions\Academico;

use App\Enums\EventoHistorico;
use App\Models\Turma;
use App\Models\TurmaHistorico;
use App\Models\User;

/**
 * Congela o estado atual da turma antes de qualquer mudança. O registro é
 * append-only: nada aqui é atualizado ou apagado depois.
 */
class RegistrarHistoricoDeTurma
{
    public function executar(
        Turma $turma,
        EventoHistorico $evento,
        ?User $autor = null,
        ?string $observacoes = null,
        array $metadados = [],
    ): TurmaHistorico {
        return TurmaHistorico::create([
            'turma_id' => $turma->getKey(),
            'evento' => $evento,
            'curso_id' => $turma->curso_id,
            'grade_curricular_id' => $turma->grade_curricular_id,
            'periodo' => $turma->periodo,
            'nome' => $turma->nome,
            'periodo_letivo' => $turma->periodo_letivo,
            'status' => $turma->status->value,
            'metadados' => $metadados === [] ? null : $metadados,
            'observacoes' => $observacoes,
            'registrado_por' => $autor?->getKey(),
        ]);
    }
}
