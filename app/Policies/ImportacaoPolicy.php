<?php

namespace App\Policies;

use App\Enums\StatusImportacao;
use App\Models\Importacao;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Só PAEET e PAEET Admin importam resultados. */
class ImportacaoPolicy extends PolicyBase
{
    public function confirmar(User $usuario, Importacao $importacao): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $importacao)
            && $importacao->status === StatusImportacao::Validada;
    }

    /** Importação é registro de auditoria: não se apaga. */
    public function delete(User $usuario, Model $registro): bool
    {
        return false;
    }
}
