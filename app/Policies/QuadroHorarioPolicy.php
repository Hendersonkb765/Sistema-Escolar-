<?php

namespace App\Policies;

use App\Models\User;

/**
 * Montar o quadro é da coordenação do Eixo da turma (create, update e
 * delete herdados). Consultar as próprias aulas é de quem as dá — o
 * escopo de `visivelPara` já restringe o professor às dele.
 */
class QuadroHorarioPolicy extends PolicyBase
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->ativo;
    }
}
