<?php

namespace App\Actions\Notificacoes;

use App\Models\User;
use App\Notifications\AvisoACoordenacao;
use Illuminate\Support\Facades\Notification;

/**
 * Entrega um aviso à coordenação dos Eixos envolvidos: PAEETs e PAEET
 * Admins ativos vinculados a eles.
 *
 * Quem provocou o aviso não o recebe — o PAEET que também dá aula não
 * precisa ser avisado da ausência que ele mesmo informou.
 */
class AvisarCoordenacaoAction
{
    /**
     * @param  array<int, int>  $eixoIds
     */
    public function executar(array $eixoIds, AvisoACoordenacao $aviso, ?User $autor = null): void
    {
        $destinatarios = User::query()
            ->coordenacaoDosEixos($eixoIds)
            ->when($autor, fn ($q) => $q->whereKeyNot($autor->getKey()))
            ->get();

        Notification::send($destinatarios, $aviso);
    }
}
