<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * O escopo-sequência é material de consulta: o professor precisa dele
 * tanto quanto a coordenação, e é por isso que `viewAny` não exige
 * gestão como nas outras telas da estrutura acadêmica.
 *
 * Importar, esse sim, é da coordenação: o arquivo substitui o percurso
 * inteiro de um período.
 */
class EscopoAulaPolicy extends PolicyBase
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->ativo;
    }

    public function view(User $usuario, Model $registro): bool
    {
        return $usuario->ativo && $this->noEscopo($usuario, $registro);
    }

    /** Importar é da gestão, e substitui o que estiver lá. */
    public function importar(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    public function create(User $usuario): bool
    {
        return $this->importar($usuario);
    }

    /** O escopo não se edita linha a linha: importa-se o arquivo de novo. */
    public function update(User $usuario, Model $registro): bool
    {
        return false;
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return false;
    }
}
