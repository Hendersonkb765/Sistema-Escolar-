<?php

namespace App\Policies;

use App\Models\Aluno;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AlunoPolicy extends PolicyBase
{
    /**
     * O professor também abre a lista: consulta os alunos das turmas em
     * que dá aula, e é dali que ele chega ao reset de senha na SED. O
     * escopo da consulta é `visivelPara`; criar e editar seguem da gestão.
     */
    public function viewAny(User $usuario): bool
    {
        return $usuario->ativo;
    }

    public function view(User $usuario, Model $registro): bool
    {
        return $usuario->ativo && $this->noEscopo($usuario, $registro);
    }

    /** Abrir a página da SED que redefine a senha do aluno. */
    public function redefinirSenhaNaSed(User $usuario, Aluno $aluno): bool
    {
        return $usuario->ativo && $this->noEscopo($usuario, $aluno);
    }

    /** Mover o aluno para outra turma, gravando o histórico da mudança. */
    public function moverDeTurma(User $usuario, Aluno $aluno): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $aluno);
    }

    /**
     * Retirar o aluno da turma, marcando a saída. Como `moverDeTurma`,
     * responde só "este aluno é seu?": se ele já saiu, quem recusa é a
     * DesligarAlunoAction, com a razão escrita.
     */
    public function desligar(User $usuario, Aluno $aluno): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $aluno);
    }

    /** Um aluno com resultado de prova lançado nunca é removido. */
    public function delete(User $usuario, $registro): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $registro)
            && ! $registro->resultados()->exists();
    }
}
