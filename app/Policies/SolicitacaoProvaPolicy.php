<?php

namespace App\Policies;

use App\Models\SolicitacaoProva;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Responde só "este recurso é seu?". Se a solicitação já foi encerrada ou
 * ainda tem questões incompletas, quem recusa são as Actions, com a razão
 * explicada — um "ainda não dá" nunca vira 403 mudo.
 */
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
        return $usuario->ehGestao() && $this->noEscopo($usuario, $solicitacao);
    }

    public function cancelar(User $usuario, SolicitacaoProva $solicitacao): bool
    {
        return $this->encerrar($usuario, $solicitacao);
    }

    /**
     * Responder a solicitação é exclusivo do professor destinatário.
     * Prazo vencido não entra nesta conta: vencer o prazo não tira de
     * ninguém o direito de responder.
     */
    public function responder(User $usuario, SolicitacaoProva $solicitacao): bool
    {
        return (int) $solicitacao->professor_id === (int) $usuario->getKey()
            && $usuario->ativo;
    }
}
