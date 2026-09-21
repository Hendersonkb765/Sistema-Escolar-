<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AlternativaPolicy extends PolicyBase
{
    public function view(User $usuario, Model $registro): bool
    {
        if ($usuario->ehGestao()) {
            return $this->noEscopo($usuario, $registro);
        }

        return (int) $registro->questao->professor_id === (int) $usuario->getKey();
    }

    public function update(User $usuario, Model $registro): bool
    {
        return app(QuestaoPolicy::class)->update($usuario, $registro->questao);
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return $this->update($usuario, $registro);
    }
}
