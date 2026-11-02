<?php

namespace App\Policies;

use App\Models\Ausencia;
use App\Models\User;

/**
 * A ausência é de quem a informou, e é acompanhada pela coordenação dos
 * Eixos das turmas em que ele daria aula naquele dia.
 *
 * Se ainda dá para cancelar ou tomar ciência — o dia não passou, não foi
 * cancelada antes — quem decide são as Actions.
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

    /** A lista da coordenação: as ausências com aula nos Eixos de quem olha. */
    public function acompanhar(User $usuario): bool
    {
        return $usuario->ativo && $usuario->ehGestao();
    }

    public function view(User $usuario, Ausencia $ausencia): bool
    {
        return $this->ehDele($usuario, $ausencia) || $this->acompanha($usuario, $ausencia);
    }

    public function cancelar(User $usuario, Ausencia $ausencia): bool
    {
        return $this->ehDele($usuario, $ausencia);
    }

    public function tomarCiencia(User $usuario, Ausencia $ausencia): bool
    {
        return $this->acompanha($usuario, $ausencia);
    }

    private function ehDele(User $usuario, Ausencia $ausencia): bool
    {
        return $usuario->ativo && (int) $ausencia->professor_id === $usuario->id;
    }

    private function acompanha(User $usuario, Ausencia $ausencia): bool
    {
        return $this->acompanhar($usuario) && $ausencia->afetaEixosDe($usuario);
    }
}
