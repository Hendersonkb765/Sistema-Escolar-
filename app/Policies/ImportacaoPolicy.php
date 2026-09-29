<?php

namespace App\Policies;

use App\Models\Importacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Só PAEET e PAEET Admin importam resultados. */
class ImportacaoPolicy extends PolicyBase
{
    /**
     * Responde só "esta importação é sua?". Se ela ainda não foi
     * conferida, quem recusa é a Action, com a razão — um "ainda não dá"
     * não pode virar 403 mudo.
     */
    public function confirmar(User $usuario, Importacao $importacao): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $importacao);
    }

    /** Importação é registro de auditoria: não se apaga. */
    public function delete(User $usuario, Model $registro): bool
    {
        return false;
    }
}
