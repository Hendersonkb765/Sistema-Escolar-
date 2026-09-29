<?php

namespace App\Policies;

use App\Models\Prova;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * A prova montada é documento da coordenação.
 *
 * O professor escreve as questões dele, acompanha a análise e vê os
 * resultados — mas não abre a folha pronta. Ela existe para ser impressa
 * e aplicada, e nela estão as questões dos colegas, na ordem e no recorte
 * que a coordenação escolheu.
 *
 * O escopo de consulta (`Prova::visivelPara`) continua devolvendo ao
 * professor as provas com questões dele: é por ele que as telas de notas
 * e de desempenho sabem o que mostrar a quem. Uma coisa é "esta prova me
 * diz respeito"; outra é "posso abrir o documento dela".
 */
class ProvaPolicy extends PolicyBase
{
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
