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

    /**
     * Uma leitura errada da folha sai, com os resultados que trouxe, para a
     * Lixeira — de onde volta. A auditoria registra quem tirou.
     */
    public function delete(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }
}
