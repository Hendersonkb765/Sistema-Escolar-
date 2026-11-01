<?php

namespace App\Policies;

use App\Models\Ausencia;
use App\Models\User;

/**
 * A ausência é de quem a informou. A leitura pela coordenação dos Eixos
 * das turmas afetadas entra na etapa seguinte.
 *
 * Se ainda dá para cancelar — o dia não passou, não foi cancelada antes —
 * quem decide é CancelarAusenciaAction.
 */
class AusenciaPolicy
{
    /** Qualquer usuário ativo vê a própria lista; o escopo é o `professor_id`. */
    public function viewAny(User $usuario): bool
    {
        return $usuario->ativo;
    }

    public function create(User $usuario): bool
    {
        return $usuario->ativo;
    }

    public function view(User $usuario, Ausencia $ausencia): bool
    {
        return $this->ehDele($usuario, $ausencia);
    }

    public function cancelar(User $usuario, Ausencia $ausencia): bool
    {
        return $this->ehDele($usuario, $ausencia);
    }

    private function ehDele(User $usuario, Ausencia $ausencia): bool
    {
        return $usuario->ativo && (int) $ausencia->professor_id === $usuario->id;
    }
}
