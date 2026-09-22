<?php

namespace App\Policies;

use App\Models\Questao;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class QuestaoPolicy extends PolicyBase
{
    public function viewAny(User $usuario): bool
    {
        return true;
    }

    public function view(User $usuario, Model $registro): bool
    {
        if ($usuario->ehGestao()) {
            return $this->noEscopo($usuario, $registro);
        }

        return (int) $registro->professor_id === (int) $usuario->getKey();
    }

    /** A questão é criada pelo professor destinatário da solicitação. */
    public function create(User $usuario): bool
    {
        return $usuario->ativo;
    }

    /**
     * Preencher ou corrigir é do autor da questão. Se o momento permite —
     * rascunho ou devolvida para correção, solicitação ainda aberta — quem
     * decide é a SalvarQuestaoAction, com a razão explicada.
     */
    public function update(User $usuario, Model $registro): bool
    {
        return (int) $registro->professor_id === (int) $usuario->getKey()
            && $usuario->ativo;
    }

    public function enviar(User $usuario, Questao $questao): bool
    {
        return $this->update($usuario, $questao);
    }

    /**
     * Analisar é atribuição da gestão, nunca de quem escreveu a questão —
     * vale inclusive para o PAEET que também leciona.
     *
     * A comparação é pela chave estrangeira, e não carregando a relação:
     * a Policy roda uma vez por linha nas listagens.
     */
    public function analisar(User $usuario, Questao $questao): bool
    {
        return $usuario->ehGestao()
            && (int) $questao->professor_id !== (int) $usuario->getKey()
            && $this->noEscopo($usuario, $questao);
    }

    public function delete(User $usuario, Model $registro): bool
    {
        return $usuario->ehGestao() && $this->noEscopo($usuario, $registro);
    }
}
