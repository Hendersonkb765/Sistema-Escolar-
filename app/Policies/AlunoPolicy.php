<?php

namespace App\Policies;

use App\Models\Aluno;
use App\Models\User;

class AlunoPolicy extends PolicyBase
{
    /** Mover o aluno para outra turma, gravando o histórico da mudança. */
    public function moverDeTurma(User $usuario, Aluno $aluno): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $aluno);
    }

    /** Um aluno com resultado de prova lançado nunca é removido. */
    public function delete(User $usuario, $registro): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $registro)
            && ! $registro->resultados()->exists();
    }
}
