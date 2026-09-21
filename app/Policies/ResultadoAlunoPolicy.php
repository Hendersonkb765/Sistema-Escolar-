<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ResultadoAlunoPolicy extends PolicyBase
{
    /** O professor vê resultados, mas só das disciplinas que leciona. */
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function view(User $usuario, Model $registro): bool
    {
        if ($usuario->ehGestao()) {
            return $this->noEscopo($usuario, $registro);
        }

        return $registro->notas()
            ->whereIn('disciplina_id', $usuario->disciplinaIds())
            ->exists();
    }

    public function create(User $usuario): bool
    {
        return false;
    }

    public function update(User $usuario, Model $registro): bool
    {
        return false;
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return false;
    }
}
