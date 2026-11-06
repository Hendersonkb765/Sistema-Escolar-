<?php

namespace App\Policies;

use App\Enums\StatusGrade;
use App\Models\GradeCurricular;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Uma grade é uma foto do curso, não um documento editável. Cria-se
 * (publicando uma versão), consulta-se e, se nenhuma turma a usa, exclui-se.
 */
class GradeCurricularPolicy extends PolicyBase
{
    public function update(User $usuario, Model $registro): bool
    {
        return false;
    }

    /**
     * Responde só "é sua?". A versão usada por turmas não sai, mas quem
     * diz isso — e quais turmas — é GradeCurricular::impedimentoDaExclusao.
     */
    public function delete(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }

    /** Arquivar retira a versão de circulação sem apagar nada. */
    public function arquivar(User $usuario, GradeCurricular $grade): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $grade)
            && $grade->status === StatusGrade::Vigente;
    }
}
