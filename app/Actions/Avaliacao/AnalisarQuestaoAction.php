<?php

namespace App\Actions\Avaliacao;

use App\Enums\AcaoFeedback;
use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\QuestaoFeedback;
use App\Models\SolicitacaoProva;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Análise de uma questão enviada: aprovar ou devolver para correção.
 *
 * Cada decisão vira um registro em `questao_feedbacks`, que é append-only:
 * o histórico guarda todas as idas e vindas, com a versão da questão a que
 * cada uma se referia.
 */
class AnalisarQuestaoAction
{
    /** Aprovar torna a questão elegível para entrar em uma prova. */
    public function aprovar(Questao $questao, User $analista, ?string $comentario = null): Questao
    {
        return $this->decidir($questao, $analista, AcaoFeedback::Aprovada, $comentario);
    }

    /**
     * Devolver para correção. O comentário é obrigatório: uma rejeição sem
     * motivo deixa o professor sem saber o que mudar.
     */
    public function rejeitar(Questao $questao, User $analista, string $comentario): Questao
    {
        if (blank($comentario)) {
            throw RegraDeNegocioException::porque(
                'Explique o que precisa ser corrigido: o professor só vê o motivo que você escrever.'
            );
        }

        return $this->decidir($questao, $analista, AcaoFeedback::Rejeitada, $comentario);
    }

    protected function decidir(
        Questao $questao,
        User $analista,
        AcaoFeedback $acao,
        ?string $comentario,
    ): Questao {
        Gate::forUser($analista)->authorize('analisar', $questao);

        $this->validar($questao);

        return DB::transaction(function () use ($questao, $analista, $acao, $comentario) {
            $aprovando = $acao === AcaoFeedback::Aprovada;

            $questao->update([
                'status' => $aprovando ? StatusQuestao::Aprovada : StatusQuestao::Rejeitada,
                'analisada_em' => now(),
            ]);

            // Append-only: o feedback registra a versão analisada, para o
            // histórico continuar fazendo sentido depois de uma correção.
            QuestaoFeedback::create([
                'questao_id' => $questao->getKey(),
                'analisado_por' => $analista->getKey(),
                'acao' => $acao,
                'comentario' => $comentario,
                'versao_questao' => $questao->versao,
            ]);

            $this->atualizarSolicitacao($questao->loadMissing('solicitacao')->solicitacao, $analista);

            activity('questao')
                ->performedOn($questao)
                ->causedBy($analista)
                ->withProperties([
                    'versao' => $questao->versao,
                    'comentario' => $comentario,
                ])
                ->log($aprovando ? 'Questão aprovada' : 'Questão devolvida para correção');

            return $questao->refresh();
        });
    }

    protected function validar(Questao $questao): void
    {
        if (! $questao->status->analisavel()) {
            throw RegraDeNegocioException::porque(match ($questao->status) {
                StatusQuestao::Rascunho => 'Esta questão ainda não foi enviada pelo professor.',
                StatusQuestao::Aprovada => 'Esta questão já está aprovada.',
                StatusQuestao::Rejeitada => 'Esta questão já foi devolvida e aguarda a correção do professor.',
                default => 'Esta questão não está em análise.',
            });
        }
    }

    /**
     * O status da solicitação acompanha o das questões: concluída quando
     * todas estão aprovadas, em análise enquanto houver o que decidir.
     */
    protected function atualizarSolicitacao(SolicitacaoProva $solicitacao, User $analista): void
    {
        if ($solicitacao->encerrada_em !== null || $solicitacao->cancelada_em !== null) {
            return;
        }

        $total = $solicitacao->questoes()->count();
        $aprovadas = $solicitacao->questoes()->where('status', StatusQuestao::Aprovada)->count();

        $novoStatus = $aprovadas === $total && $total > 0
            ? StatusSolicitacao::Concluida
            : StatusSolicitacao::EmAnalise;

        if ($solicitacao->status === $novoStatus) {
            return;
        }

        // `encerrada_em` fica reservado ao encerramento manual: é ele que
        // bloqueia o envio. Concluir por aprovação não fecha a porta — se
        // a coordenação devolver uma questão depois, a solicitação volta a
        // aceitar a correção.
        $solicitacao->update(['status' => $novoStatus]);

        if ($novoStatus === StatusSolicitacao::Concluida) {
            activity('solicitacao')
                ->performedOn($solicitacao)
                ->causedBy($analista)
                ->withProperties(['questoes_aprovadas' => $aprovadas])
                ->log('Todas as questões foram aprovadas');
        }
    }
}
