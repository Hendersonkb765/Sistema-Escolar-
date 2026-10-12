<?php

namespace App\Policies;

use App\Models\Turma;
use App\Models\User;

class TurmaPolicy extends PolicyBase
{
    /**
     * Quem pode avançar o ano de uma turma. Responde apenas "este recurso
     * é seu?" — se a turma já está no último ano ou não está ativa, quem
     * recusa é a AvancarTurmaAction, com a razão explicada ao usuário.
     * Misturar as duas coisas transformaria "não dá agora" em 403.
     */
    public function avancarAno(User $usuario, Turma $turma): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $turma);
    }

    /** Trocar a versão da grade congelada — operação consciente e registrada. */
    public function trocarGrade(User $usuario, Turma $turma): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $turma);
    }

    /**
     * Quem pode encerrar a turma. Como em `avancarAno`, responde só
     * "este recurso é seu?": se a turma ainda não chegou ao último
     * período, ou já foi concluída, quem recusa é a ConcluirTurmaAction,
     * com a razão escrita. Estava aqui, e um "ainda não dá" virava 403
     * mudo.
     */
    public function encerrar(User $usuario, Turma $turma): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $turma);
    }
}
