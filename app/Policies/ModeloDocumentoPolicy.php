<?php

namespace App\Policies;

use App\Models\ModeloDocumento;
use App\Models\User;

/**
 * Escopo puro por Eixo, como os demais recursos.
 *
 * O compartilhamento não abre exceção aqui: um modelo de outro Eixo
 * continua dando 403 mesmo com o id na URL, inclusive para quem o
 * recebeu. Quem recebeu vê o conteúdo pela tela do compartilhamento, que
 * tem policy própria, e ao aceitar ganha uma cópia sua — a partir daí o
 * escopo normal já responde sim.
 */
class ModeloDocumentoPolicy extends PolicyBase
{
    /** Só se compartilha o que se pode ver. */
    public function compartilhar(User $usuario, ModeloDocumento $modelo): bool
    {
        return $this->view($usuario, $modelo);
    }

    /** Gerar documentos é usar o modelo, não alterá-lo. */
    public function gerar(User $usuario, ModeloDocumento $modelo): bool
    {
        return $this->view($usuario, $modelo);
    }

    public function duplicar(User $usuario, ModeloDocumento $modelo): bool
    {
        return $this->view($usuario, $modelo) && $usuario->ehGestao();
    }
}
