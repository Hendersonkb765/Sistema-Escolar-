<?php

namespace App\Policies;

use App\Enums\StatusGrade;
use App\Models\GradeCurricular;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma grade é uma foto do curso, não um documento editável. Só se cria
 * (publicando uma versão) e se consulta.
 */
class GradeCurricularPolicy extends PolicyBase
{
    public function update(User $usuario, Model $registro): bool
    {
        return false;
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return false;
    }

    /** Arquivar retira a versão de circulação sem apagar nada. */
    public function arquivar(User $usuario, GradeCurricular $grade): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $grade)
            && $grade->status === StatusGrade::Vigente;
    }
}
