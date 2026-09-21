<?php

namespace App\Policies;

use App\Enums\StatusGrade;
use App\Models\GradeCurricular;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class GradeCurricularPolicy extends PolicyBase
{
    /**
     * Uma grade só é editável enquanto está em rascunho e nenhuma turma a
     * congelou. Depois disso, a mudança passa obrigatoriamente por uma
     * nova versão.
     */
    public function update(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $registro)
            && $registro->editavel();
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return $this->update($usuario, $registro);
    }

    /** Clonar a grade em uma nova versão de rascunho. */
    public function novaVersao(User $usuario, GradeCurricular $grade): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $grade);
    }

    /** Publicar um rascunho, arquivando a versão vigente anterior. */
    public function publicar(User $usuario, GradeCurricular $grade): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $grade)
            && $grade->status === StatusGrade::Rascunho
            && $grade->disciplinas()->exists();
    }

    /** Arquivar retira a grade de uso sem apagar nada. */
    public function arquivar(User $usuario, GradeCurricular $grade): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $grade)
            && $grade->status === StatusGrade::Vigente;
    }
}
