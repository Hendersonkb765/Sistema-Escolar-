<?php

namespace App\Policies;

use App\Models\Concerns\AplicaEscopoDeEixo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Regra comum a todos os recursos escopados por Eixo.
 *
 * Duas barreiras se somam: o perfil precisa ter a atribuição e o registro
 * precisa estar dentro dos Eixos do usuário. Recurso de outro Eixo resulta
 * em 403 mesmo quando o id é adivinhado na URL (IDOR).
 */
abstract class PolicyBase
{
    /** Quem pode listar/abrir o recurso. */
    public function viewAny(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    public function view(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }

    public function create(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    public function update(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }

    public function restore(User $usuario, Model $registro): bool
    {
        return $this->delete($usuario, $registro);
    }

    /** Exclusão definitiva nunca é permitida: o histórico é preservado. */
    public function forceDelete(User $usuario, Model $registro): bool
    {
        return false;
    }

    /**
     * @param  Model&AplicaEscopoDeEixo  $registro
     */
    protected function noEscopo(User $usuario, Model $registro): bool
    {
        return $registro->dentroDoEscopoDe($usuario);
    }
}
