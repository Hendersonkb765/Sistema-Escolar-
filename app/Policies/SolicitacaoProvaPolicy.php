<?php

namespace App\Policies;

use App\Models\SolicitacaoProva;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SolicitacaoProvaPolicy extends PolicyBase
{
    /** O professor acessa a área dele para ver o que lhe foi pedido. */
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function view(User $usuario, Model $registro): bool
    {
        if ($usuario->ehGestao()) {
            return $this->noEscopo($usuario, $registro);
        }

        return (int) $registro->professor_id === (int) $usuario->getKey();
    }

    /** Professor nunca cria solicitação. */
    public function create(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    /** Encerrar manualmente — o que de fato bloqueia novos envios. */
    public function encerrar(User $usuario, SolicitacaoProva $solicitacao): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $solicitacao)
            && $solicitacao->aceitaEnvio();
    }

    public function cancelar(User $usuario, SolicitacaoProva $solicitacao): bool
    {
        return $this->encerrar($usuario, $solicitacao);
    }

    /**
     * Responder a solicitação: exclusivo do professor destinatário.
     * Prazo vencido não bloqueia — só encerramento ou cancelamento.
     */
    public function responder(User $usuario, SolicitacaoProva $solicitacao): bool
    {
        return (int) $solicitacao->professor_id === (int) $usuario->getKey()
            && $usuario->ativo
            && $solicitacao->aceitaEnvio();
    }
}
