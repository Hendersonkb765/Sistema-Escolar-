<?php

namespace App\Policies;

use App\Enums\PerfilUsuario;
use App\Models\User;

/**
 * Não existe cadastro público: todo usuário nasce criado por um PAEET
 * Admin (que pode criar PAEETs e Professores) ou por um PAEET (que só
 * cria Professores). O professor nunca cria ninguém.
 */
class UserPolicy
{
    public function viewAny(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    public function view(User $usuario, User $alvo): bool
    {
        if ($usuario->is($alvo)) {
            return true;
        }

        return $usuario->ehGestao() && $this->compartilhamEixo($usuario, $alvo);
    }

    public function create(User $usuario): bool
    {
        return $usuario->ehGestao();
    }

    /** Checagem de perfil pretendido — usada pelo Form Request. */
    public function criarComPerfil(User $usuario, PerfilUsuario $perfil): bool
    {
        return match ($perfil) {
            PerfilUsuario::PaeetAdmin => false,
            PerfilUsuario::Paeet => $usuario->ehPaeetAdmin(),
            PerfilUsuario::Professor => $usuario->ehGestao(),
        };
    }

    public function update(User $usuario, User $alvo): bool
    {
        if (! $usuario->ehGestao()) {
            return false;
        }

        if ($usuario->is($alvo)) {
            return true;
        }

        // Um PAEET não altera PAEETs nem PAEET Admins.
        if ($alvo->perfil !== PerfilUsuario::Professor && ! $usuario->ehPaeetAdmin()) {
            return false;
        }

        return $this->compartilhamEixo($usuario, $alvo);
    }

    /** Ativar/desativar acesso — o desligamento é imediato. */
    public function alternarAtivacao(User $usuario, User $alvo): bool
    {
        return ! $usuario->is($alvo) && $this->update($usuario, $alvo);
    }

    public function delete(User $usuario, User $alvo): bool
    {
        return ! $usuario->is($alvo)
            && $usuario->ehPaeetAdmin()
            && $alvo->perfil !== PerfilUsuario::PaeetAdmin
            && $this->compartilhamEixo($usuario, $alvo);
    }

    public function forceDelete(User $usuario, User $alvo): bool
    {
        return false;
    }

    /** Gerenciar vínculos docentes de um usuário. */
    public function gerenciarVinculos(User $usuario, User $alvo): bool
    {
        return $usuario->ehGestao() && $this->compartilhamEixo($usuario, $alvo);
    }

    /**
     * Um usuário de gestão só enxerga contas que tocam seus Eixos.
     * Professor recém-criado ainda sem vínculo é visível a quem o criou.
     */
    protected function compartilhamEixo(User $usuario, User $alvo): bool
    {
        if ($alvo->perfil === PerfilUsuario::Professor) {
            $eixosDoAlvo = $alvo->eixos()->pluck('eixos.id')->all();

            if ($eixosDoAlvo === []) {
                return (int) $alvo->criado_por === (int) $usuario->getKey()
                    || $usuario->ehPaeetAdmin();
            }

            return array_intersect($eixosDoAlvo, $usuario->eixoIds()) !== [];
        }

        return array_intersect($alvo->eixos()->pluck('eixos.id')->all(), $usuario->eixoIds()) !== [];
    }
}
