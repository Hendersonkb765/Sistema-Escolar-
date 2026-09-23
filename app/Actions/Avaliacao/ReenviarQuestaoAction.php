<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Reenvio de uma questão devolvida para correção.
 *
 * Cada reenvio é uma versão nova: o feedback antigo continua no histórico
 * apontando para a versão a que se referia.
 */
class ReenviarQuestaoAction
{
    public function executar(Questao $questao, User $autor): Questao
    {
        Gate::forUser($autor)->authorize('update', $questao);

        $solicitacao = $questao->loadMissing('solicitacao')->solicitacao;

        // Só o fechamento manual bloqueia: uma solicitação marcada como
        // concluída volta a receber correções se a coordenação devolver
        // alguma questão.
        if ($solicitacao->encerrada_em !== null || $solicitacao->cancelada_em !== null) {
            throw RegraDeNegocioException::porque(
                'Esta solicitação foi encerrada pela coordenação e não aceita mais envios.'
            );
        }

        if ($questao->status !== StatusQuestao::Rejeitada) {
            throw RegraDeNegocioException::porque(
                $questao->status === StatusQuestao::Rascunho
                    ? 'Esta questão ainda não passou por análise; envie a solicitação inteira.'
                    : 'Só uma questão devolvida para correção precisa ser reenviada.'
            );
        }

        if (! $questao->estaCompleta($solicitacao->quantidade_alternativas)) {
            throw RegraDeNegocioException::porque(
                'Complete a questão antes de reenviar: enunciado, peso, todas as alternativas e uma marcada como correta.'
            );
        }

        return DB::transaction(function () use ($questao, $autor, $solicitacao) {
            $questao->update([
                'status' => StatusQuestao::Enviada,
                'versao' => $questao->versao + 1,
                'enviada_em' => now(),
                'analisada_em' => null,
            ]);

            if ($solicitacao->status === StatusSolicitacao::Concluida) {
                $solicitacao->update([
                    'status' => StatusSolicitacao::EmAnalise,
                    'encerrada_em' => null,
                ]);
            }

            // A parte volta para análise junto com a questão corrigida.
            $parte = $questao->loadMissing('parte')->parte;

            if ($parte !== null && $parte->status === StatusSolicitacao::Concluida) {
                $parte->update(['status' => StatusSolicitacao::EmAnalise]);
            }

            activity('questao')
                ->performedOn($questao)
                ->causedBy($autor)
                ->withProperties(['versao' => $questao->versao])
                ->log("Questão corrigida e reenviada (versão {$questao->versao})");

            return $questao->refresh();
        });
    }
}
