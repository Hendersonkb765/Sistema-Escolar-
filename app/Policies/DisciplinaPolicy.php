<?php

namespace App\Policies;

use App\Models\Disciplina;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class DisciplinaPolicy extends PolicyBase
{
    /**
     * Mudar o período de uma disciplina não reescreve o passado: as
     * turmas seguem na foto que congelaram. A mudança só alcança turmas
     * novas depois que a próxima versão da grade for publicada.
     */
    public function update(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }

    /**
     * Vai para a Lixeira mesmo já fotografada em grade: as grades publicadas
     * continuam com ela, e a confirmação diz isso. Responde só "é sua?".
     */
    public function delete(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }
}
