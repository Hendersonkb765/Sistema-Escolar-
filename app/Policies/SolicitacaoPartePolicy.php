<?php

namespace App\Policies;

use App\Models\SolicitacaoParte;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Responde só "esta parte é sua?". Se já foi enviada ou a solicitação foi
 * encerrada, quem recusa é a EnviarParteAction, com a razão explicada.
 */
class SolicitacaoPartePolicy extends PolicyBase
{
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

    /**
     * Responder é exclusivo do professor daquela disciplina. Prazo vencido
     * não entra nesta conta.
     */
    public function responder(User $usuario, SolicitacaoParte $parte): bool
    {
        return (int) $parte->professor_id === (int) $usuario->getKey()
            && $usuario->ativo;
    }

    public function create(User $usuario): bool
    {
        return $usuario->ehGestao();
    }
}
