<?php

namespace App\Policies;

use App\Models\Eixo;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class EixoPolicy extends PolicyBase
{
    /** Só o PAEET Admin cria e remove Eixos. */
    public function create(User $usuario): bool
    {
        return $usuario->ehPaeetAdmin();
    }

    public function update(User $usuario, Model $registro): bool
    {
        return $usuario->ehPaeetAdmin() && $this->noEscopo($usuario, $registro);
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return $usuario->ehPaeetAdmin() && $this->noEscopo($usuario, $registro);
    }

    /** Vincular ou desvincular usuários de um Eixo. */
    public function gerenciarAcessos(User $usuario, Eixo $eixo): bool
    {
        return $usuario->ehPaeetAdmin() && $this->noEscopo($usuario, $eixo);
    }
}
