<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusQuestao;
use App\Enums\StatusSolicitacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\SolicitacaoParte;
use App\Models\SolicitacaoProva;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Envia para análise as questões de uma parte — a cota de uma disciplina.
 *
 * Cada professor entrega a sua parte quando termina, sem esperar pelos
 * outros. A solicitação só fica "enviada" quando todas as partes chegam.
 *
 * Regra de prazo: prazo vencido **não bloqueia** o envio. A parte apenas
 * aparece como "Atrasada" enquanto pendente e, quando entregue depois do
 * prazo, fica marcada como "Enviada em atraso". O que fecha o envio é o
 * encerramento ou o cancelamento manual pelo PAEET.
 */
class EnviarParteAction
{
    public function executar(SolicitacaoParte $parte, User $autor): SolicitacaoParte
    {
        Gate::forUser($autor)->authorize('responder', $parte);

        $solicitacao = $parte->loadMissing('solicitacao')->solicitacao;

        if (! $solicitacao->aceitaEnvio()) {
            throw RegraDeNegocioException::porque(
                'Esta solicitação foi encerrada pela coordenação e não aceita mais envios.'
            );
        }

        // A entrega de uma parte acontece uma vez. Se depois a coordenação
        // devolver alguma questão, o que se reenvia é a questão corrigida,
        // não a parte inteira.
        if ($parte->enviada_em !== null) {
            $disciplina = $parte->loadMissing('disciplina')->disciplina->nome;

            throw RegraDeNegocioException::porque(
                "As questões de {$disciplina} já foram enviadas em "
                .$parte->enviada_em->format('d/m/Y H:i').'.'
            );
        }

        $pendencias = $this->pendenciasPorQuestao($parte);

        if ($pendencias->isNotEmpty()) {
            $ordens = $pendencias->keys()->join(', ');

            throw RegraDeNegocioException::porque(
                $pendencias->count() === 1
                    ? "A questão {$ordens} ainda não está completa."
                    : "As questões {$ordens} ainda não estão completas."
            );
        }

        return DB::transaction(function () use ($parte, $autor, $solicitacao) {
            $agora = now();

            // O prazo original nunca é reescrito; registra-se a data real
            // do envio e se ela passou do combinado.
            $emAtraso = $agora->greaterThan($solicitacao->prazo);

            $parte->update([
                'status' => StatusSolicitacao::Enviada,
                'enviada_em' => $agora,
                'enviada_em_atraso' => $emAtraso,
            ]);

            $parte->questoes()->each(function (Questao $questao) use ($agora) {
                $questao->update([
                    'status' => StatusQuestao::Enviada,
                    'enviada_em' => $agora,
                ]);
            });

            $this->atualizarSolicitacao($solicitacao);

            activity('solicitacao')
                ->performedOn($parte)
                ->causedBy($autor)
                ->withProperties([
                    'disciplina' => $parte->loadMissing('disciplina')->disciplina->nome,
                    'prazo' => $solicitacao->prazo->format('Y-m-d H:i'),
                    'enviada_em' => $agora->format('Y-m-d H:i'),
                    'em_atraso' => $emAtraso,
                    'questoes' => $parte->quantidade_questoes,
                ])
                ->log($emAtraso
                    ? 'Questões enviadas em atraso'
                    : 'Questões enviadas dentro do prazo');

            return $parte->refresh();
        });
    }

    /**
     * A solicitação acompanha as partes: enviada quando todas chegaram.
     */
    public function atualizarSolicitacao(SolicitacaoProva $solicitacao): void
    {
        if (! $solicitacao->aceitaEnvio()) {
            return;
        }

        $total = $solicitacao->partes()->count();
        $enviadas = $solicitacao->partes()->whereNotNull('enviada_em')->count();

        $novo = match (true) {
            $total > 0 && $enviadas === $total => StatusSolicitacao::Enviada,
            default => StatusSolicitacao::Aberta,
        };

        // Uma vez em análise ou concluída, quem manda é a análise.
        if (in_array($solicitacao->status, [StatusSolicitacao::EmAnalise, StatusSolicitacao::Concluida], true)) {
            return;
        }

        if ($solicitacao->status !== $novo) {
            $solicitacao->update(['status' => $novo]);
        }
    }

    /**
     * O que falta em cada questão da parte, indexado pela ordem.
     *
     * @return Collection<int, array<int, string>>
     */
    public function pendenciasPorQuestao(SolicitacaoParte $parte): Collection
    {
        $esperadas = (int) $parte->loadMissing('solicitacao')->solicitacao->quantidade_alternativas;

        return $parte->questoes()
            ->with('alternativas')
            ->get()
            ->mapWithKeys(fn (Questao $questao) => [
                (int) $questao->ordem => $questao->pendencias($esperadas),
            ])
            ->reject(fn (array $pendencias) => $pendencias === [])
            ->sortKeys();
    }

    /** @return Collection<int, int> */
    public function questoesIncompletas(SolicitacaoParte $parte): Collection
    {
        return $this->pendenciasPorQuestao($parte)->keys()->values();
    }
}
