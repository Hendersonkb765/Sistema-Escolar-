<?php

namespace App\Policies;

use App\Models\User;

/**
 * O calendário é da escola, não de um Eixo: todos consultam, porque é
 * por ele que a data de uma aula vira semana do escopo. Alterar é do
 * PAEET Admin — um bimestre deslocado muda a semana de todas as turmas.
 */
class BimestreLetivoPolicy
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
