<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\SolicitacaoProva;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Encerra ou cancela uma solicitação — as duas únicas formas de fechar o
 * envio. Vencer o prazo, por si só, não fecha nada.
 */
class EncerrarSolicitacaoAction
{
    public function encerrar(SolicitacaoProva $solicitacao, User $autor, ?string $motivo = null): SolicitacaoProva
    {
        return $this->fechar($solicitacao, $autor, StatusSolicitacao::Concluida, $motivo);
    }

    public function cancelar(SolicitacaoProva $solicitacao, User $autor, ?string $motivo = null): SolicitacaoProva
    {
        return $this->fechar($solicitacao, $autor, StatusSolicitacao::Cancelada, $motivo);
    }

    /** Reabre uma solicitação fechada por engano. */
    public function reabrir(SolicitacaoProva $solicitacao, User $autor, ?string $motivo = null): SolicitacaoProva
    {
        Gate::forUser($autor)->authorize('encerrar', $solicitacao);

        if ($solicitacao->aceitaEnvio()) {
            throw RegraDeNegocioException::porque('Esta solicitação já está aberta para envio.');
        }

        return DB::transaction(function () use ($solicitacao, $autor, $motivo) {
            $solicitacao->update([
                // Volta ao estado que as partes indicam.
                'status' => $solicitacao->partes()->whereNull('enviada_em')->exists()
                    ? StatusSolicitacao::Aberta
                    : StatusSolicitacao::Enviada,
                'encerrada_em' => null,
                'cancelada_em' => null,
            ]);

            activity('solicitacao')
                ->performedOn($solicitacao)
                ->causedBy($autor)
                ->withProperties(['motivo' => $motivo])
                ->log('Solicitação reaberta');

            return $solicitacao->refresh();
        });
    }

    protected function fechar(
        SolicitacaoProva $solicitacao,
        User $autor,
        StatusSolicitacao $status,
        ?string $motivo,
    ): SolicitacaoProva {
        Gate::forUser($autor)->authorize(
            $status === StatusSolicitacao::Cancelada ? 'cancelar' : 'encerrar',
            $solicitacao,
        );

        if (! $solicitacao->aceitaEnvio()) {
            throw RegraDeNegocioException::porque('Esta solicitação já está fechada.');
        }

        return DB::transaction(function () use ($solicitacao, $autor, $status, $motivo) {
            $cancelando = $status === StatusSolicitacao::Cancelada;

            $solicitacao->update([
                'status' => $status,
                'encerrada_em' => $cancelando ? null : now(),
                'cancelada_em' => $cancelando ? now() : null,
            ]);

            activity('solicitacao')
                ->performedOn($solicitacao)
                ->causedBy($autor)
                ->withProperties([
                    'motivo' => $motivo,
                    'questoes_recebidas' => $solicitacao->questoesCompletas(),
                    'questoes_pedidas' => $solicitacao->totalDeQuestoes(),
                ])
                ->log($cancelando ? 'Solicitação cancelada' : 'Solicitação encerrada');

            return $solicitacao->refresh();
        });
    }
}
