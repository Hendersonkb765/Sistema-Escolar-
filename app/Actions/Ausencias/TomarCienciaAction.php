<?php

namespace App\Actions\Ausencias;

use App\Enums\StatusAusencia;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Ausencia;
use App\Models\User;

/**
 * A coordenação registra que viu a ausência. Não aprova nem recusa: a
 * substituição é organizada fora do sistema, e a ciência só diz ao
 * professor que o aviso chegou — e quem o recebeu.
 */
class TomarCienciaAction
{
    public function executar(Ausencia $ausencia, User $quem): Ausencia
    {
        if ($ausencia->status === StatusAusencia::Cancelada) {
            throw RegraDeNegocioException::porque(
                "{$ausencia->professor->nome} cancelou a ausência de {$ausencia->data->format('d/m/Y')}: não há do que tomar ciência."
            );
        }

        if ($ausencia->status === StatusAusencia::Ciente) {
            throw RegraDeNegocioException::porque(
                "{$ausencia->cientePor?->nome} já tomou ciência da ausência de {$ausencia->data->format('d/m/Y')}"
                ." em {$ausencia->ciente_em->format('d/m/Y \à\s H:i')}."
            );
        }

        $ausencia->update([
            'status' => StatusAusencia::Ciente,
            'ciente_por' => $quem->id,
            'ciente_em' => now(),
        ]);

        return $ausencia;
    }
}
