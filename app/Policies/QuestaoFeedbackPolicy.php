<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class QuestaoFeedbackPolicy extends PolicyBase
{
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    /** O professor lê os feedbacks das próprias questões. */
    public function view(User $usuario, Model $registro): bool
    {
        if ($usuario->ehGestao()) {
            return $this->noEscopo($usuario, $registro);
        }

        return (int) $registro->questao->professor_id === (int) $usuario->getKey();
    }

    /** Feedback é histórico: nunca editado nem apagado. */
    public function update(User $usuario, Model $registro): bool
    {
        return false;
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return false;
    }
}
