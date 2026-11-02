<?php

namespace App\Policies;

use App\Models\User;

/**
 * O calendário de eventos é da escola, como o dos bimestres: todos
 * consultam, para saber; o PAEET Admin cadastra.
 */
class EventoEscolarPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->ativo;
    }

    public function gerenciar(User $usuario): bool
    {
        return $usuario->ehPaeetAdmin();
    }
}
