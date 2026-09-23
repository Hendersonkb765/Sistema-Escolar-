<?php

namespace App\Policies;

use App\Models\Prova;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProvaPolicy extends PolicyBase
{
    /** A listagem do professor já vem filtrada pelas questões dele. */
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    /** O professor consulta as provas que contêm questões dele. */
    public function view(User $usuario, Model $registro): bool
    {
        if ($usuario->ehGestao()) {
            return $this->noEscopo($usuario, $registro);
        }

        return $registro->questoes()
            ->where('professor_id', $usuario->getKey())
            ->exists();
    }

    /** Prova já gerada tem snapshot imutável: não se remonta nem se edita. */
    public function update(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $registro)
            && $registro->status->editavel();
    }

    public function gerar(User $usuario, Prova $prova): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $prova)
            && $prova->status->editavel();
    }

    /**
     * Registrar que a prova foi aplicada não mexe no snapshot, então não
     * passa pelo `update` — que é justamente o que a prova gerada proíbe.
     * Se a prova ainda não foi gerada, quem recusa é a Action, com a
     * razão explicada.
     */
    public function aplicar(User $usuario, Prova $prova): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $prova);
    }

    /**
     * O gabarito completo é da coordenação. O professor consulta a prova
     * porque tem questões nela, mas não as respostas das outras.
     */
    public function verGabarito(User $usuario, Prova $prova): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $prova);
    }

    public function baixarPdf(User $usuario, Prova $prova): bool
    {
        return $this->view($usuario, $prova) && $prova->pdf_path !== null;
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao()
            && $this->noEscopo($usuario, $registro)
            && $registro->status->editavel();
    }
}
