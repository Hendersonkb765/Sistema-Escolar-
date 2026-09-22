<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\SolicitacaoProva;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Envia as questões respondidas para análise.
 *
 * Regra de prazo: prazo vencido **não bloqueia** o envio. A solicitação
 * apenas aparece como "Atrasada" enquanto pendente e, quando enviada
 * depois do prazo, fica marcada como "Enviada em atraso". O que fecha o
 * envio é o encerramento ou o cancelamento manual pelo PAEET.
 */
class EnviarSolicitacaoAction
{
    public function executar(SolicitacaoProva $solicitacao, User $autor): SolicitacaoProva
    {
        Gate::forUser($autor)->authorize('responder', $solicitacao);

        if (! $solicitacao->aceitaEnvio()) {
            throw RegraDeNegocioException::porque(
                'Esta solicitação foi encerrada pela coordenação e não aceita mais envios.'
            );
        }

        $incompletas = $this->questoesIncompletas($solicitacao);

        if ($incompletas->isNotEmpty()) {
            $ordens = $incompletas->join(', ');

            throw RegraDeNegocioException::porque(
                $incompletas->count() === 1
                    ? "A questão {$ordens} ainda não está completa: preencha o enunciado, todas as alternativas e marque a correta."
                    : "As questões {$ordens} ainda não estão completas: preencha o enunciado, todas as alternativas e marque a correta."
            );
        }

        return DB::transaction(function () use ($solicitacao, $autor) {
            $agora = now();

            // O prazo original nunca é reescrito; o que se registra é a
            // data real do envio e se ela passou do combinado.
            $emAtraso = $agora->greaterThan($solicitacao->prazo);

            $solicitacao->update([
                'status' => StatusSolicitacao::Enviada,
                'enviada_em' => $agora,
                'enviada_em_atraso' => $emAtraso,
            ]);

            $solicitacao->questoes()->each(function (Questao $questao) use ($agora) {
                $questao->update([
                    'status' => StatusQuestao::Enviada,
                    'enviada_em' => $agora,
                ]);
            });

            activity('solicitacao')
                ->performedOn($solicitacao)
                ->causedBy($autor)
                ->withProperties([
                    'prazo' => $solicitacao->prazo->format('Y-m-d H:i'),
                    'enviada_em' => $agora->format('Y-m-d H:i'),
                    'em_atraso' => $emAtraso,
                    'questoes' => $solicitacao->quantidade_questoes,
                ])
                ->log($emAtraso
                    ? 'Questões enviadas em atraso'
                    : 'Questões enviadas dentro do prazo');

            return $solicitacao->refresh();
        });
    }

    /**
     * Ordens das questões que ainda não podem ser enviadas.
     *
     * @return Collection<int, int>
     */
    public function questoesIncompletas(SolicitacaoProva $solicitacao): Collection
    {
        return $this->pendenciasPorQuestao($solicitacao)->keys()->values();
    }

    /**
     * O que falta em cada questão, indexado pela ordem — é o texto que a
     * tela mostra ao professor, em vez de um "faltam 3 questões" que não
     * diz o que fazer.
     *
     * @return Collection<int, array<int, string>>
     */
    public function pendenciasPorQuestao(SolicitacaoProva $solicitacao): Collection
    {
        return $solicitacao->questoes()
            ->with(['alternativas', 'item'])
            ->get()
            ->mapWithKeys(fn (Questao $questao) => [
                (int) $questao->item->ordem => $questao->pendencias($solicitacao->quantidade_alternativas),
            ])
            ->reject(fn (array $pendencias) => $pendencias === [])
            ->sortKeys();
    }
}
