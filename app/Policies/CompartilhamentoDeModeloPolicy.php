<?php

namespace App\Policies;

use App\Models\CompartilhamentoDeModelo;
use App\Models\User;

/**
 * Um compartilhamento não pertence a um Eixo — pertence a duas pessoas.
 *
 * Por isso esta policy não estende {@see PolicyBase}: a pergunta "este
 * recurso é seu?" aqui se responde por remetente e destinatário, e um
 * PAEET de fora não vê a troca nem sabe que ela existiu.
 *
 * "Já dá para responder?" não se decide aqui: um compartilhamento já
 * respondido continua sendo do destinatário. Quem recusa a segunda
 * resposta é a Action, com o motivo na tela.
 */
class CompartilhamentoDeModeloPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    public function view(User $usuario, CompartilhamentoDeModelo $compartilhamento): bool
    {
        return $usuario->ehGestao() && $this->envolvido($usuario, $compartilhamento);
    }

    public function create(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    /** Aceitar e recusar são do destinatário, e só dele. */
    public function responder(User $usuario, CompartilhamentoDeModelo $compartilhamento): bool
    {
        return $usuario->ehGestao() && $compartilhamento->ehDestinatario($usuario);
    }

    /** Cancelar uma oferta pendente é de quem a fez. */
    public function cancelar(User $usuario, CompartilhamentoDeModelo $compartilhamento): bool
    {
        return $usuario->ehGestao() && $compartilhamento->remetente_id === $usuario->getKey();
    }

    public function update(User $usuario, CompartilhamentoDeModelo $compartilhamento): bool
    {
        return false;
    }

    public function delete(User $usuario, CompartilhamentoDeModelo $compartilhamento): bool
    {
        return false;
    }

    protected function envolvido(User $usuario, CompartilhamentoDeModelo $compartilhamento): bool
    {
        return in_array($usuario->getKey(), [
            $compartilhamento->remetente_id,
            $compartilhamento->destinatario_id,
        ], true);
    }
}
