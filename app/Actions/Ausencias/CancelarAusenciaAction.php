<?php

namespace App\Actions\Ausencias;

use App\Actions\Notificacoes\AvisarCoordenacaoAction;
use App\Enums\StatusAusencia;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Ausencia;
use App\Notifications\AvisoACoordenacao;

/**
 * O professor desiste da ausência — vai estar na escola, afinal.
 *
 * A ausência não é apagada: a coordenação pode já ter se organizado em
 * torno dela, e precisa ver que foi cancelada.
 */
class CancelarAusenciaAction
{
    public function executar(Ausencia $ausencia): Ausencia
    {
        if ($ausencia->status === StatusAusencia::Cancelada) {
            throw RegraDeNegocioException::porque(
                "A ausência de {$ausencia->data->format('d/m/Y')} já estava cancelada."
            );
        }

        if ($ausencia->data->isBefore(today())) {
            throw RegraDeNegocioException::porque(
                "{$ausencia->data->format('d/m/Y')} já passou. Ausência que já aconteceu não se cancela."
            );
        }

        $ausencia->update([
            'status' => StatusAusencia::Cancelada,
            'cancelada_em' => now(),
        ]);

        app(AvisarCoordenacaoAction::class)->executar(
            $ausencia->eixoIds(), AvisoACoordenacao::ausenciaCancelada($ausencia->load('professor:id,nome')), $ausencia->professor,
        );

        return $ausencia;
    }
}
