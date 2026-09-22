<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Grava o rascunho de uma questão: enunciado, peso e alternativas.
 *
 * O peso é do professor — é ele quem sabe quanto a questão vale dentro
 * da disciplina.
 */
class SalvarQuestaoAction
{
    /**
     * @param  array<int, array{letra?: string, texto: ?string, correta?: bool}>  $alternativas
     */
    public function executar(
        Questao $questao,
        User $autor,
        ?string $enunciado,
        array $alternativas,
        float|string|null $peso = null,
    ): Questao {
        Gate::forUser($autor)->authorize('update', $questao);

        // Carregamento explícito: a action precisa da solicitação para
        // conhecer as regras da questão, venha a questão de onde vier.
        $solicitacao = $questao->loadMissing('solicitacao')->solicitacao;

        // Fechamento manual é o que impede alterações. Uma solicitação
        // concluída por aprovação ainda aceita a correção de uma questão
        // que voltou para o professor.
        if ($solicitacao->encerrada_em !== null || $solicitacao->cancelada_em !== null) {
            throw RegraDeNegocioException::porque(
                'Esta solicitação foi encerrada e não aceita mais alterações.'
            );
        }

        if (! $questao->status->editavelPeloProfessor()) {
            throw RegraDeNegocioException::porque(
                'Esta questão já foi enviada e está aguardando análise.'
            );
        }

        $esperadas = (int) $solicitacao->quantidade_alternativas;

        if (count($alternativas) !== $esperadas) {
            throw RegraDeNegocioException::porque(
                "Esta solicitação pede {$esperadas} alternativas por questão."
            );
        }

        $corretas = collect($alternativas)->where('correta', true)->count();

        if ($corretas > 1) {
            throw RegraDeNegocioException::porque('Marque apenas uma alternativa como correta.');
        }

        if ($peso !== null && (float) $peso <= 0) {
            throw RegraDeNegocioException::porque('O peso da questão precisa ser maior que zero.');
        }

        return DB::transaction(function () use ($questao, $enunciado, $alternativas, $esperadas, $peso) {
            $atualizacao = ['enunciado' => $enunciado];

            // Peso ausente significa "não mexa", e não "zere".
            if ($peso !== null) {
                $atualizacao['peso'] = (float) $peso;
            }

            $questao->update($atualizacao);

            foreach (array_values($alternativas) as $indice => $dados) {
                $letra = $dados['letra'] ?? chr(65 + $indice);

                $questao->alternativas()->updateOrCreate(
                    ['letra' => $letra],
                    [
                        'texto' => $dados['texto'] ?? null,
                        'correta' => (bool) ($dados['correta'] ?? false),
                    ],
                );
            }

            // Se a solicitação encolheu o número de alternativas, as sobras
            // saem — nunca deixamos uma letra órfã na questão.
            $letrasValidas = array_map(
                fn (int $indice) => chr(65 + $indice),
                range(0, $esperadas - 1),
            );

            $questao->alternativas()->whereNotIn('letra', $letrasValidas)->delete();

            return $questao->refresh();
        });
    }

    /** Cria as alternativas vazias de uma questão que ainda não as tem. */
    public function prepararAlternativas(Questao $questao): Questao
    {
        $esperadas = (int) $questao->loadMissing('solicitacao')->solicitacao->quantidade_alternativas;

        for ($indice = 0; $indice < $esperadas; $indice++) {
            $questao->alternativas()->firstOrCreate(
                ['letra' => chr(65 + $indice)],
                ['texto' => null, 'correta' => false],
            );
        }

        return $questao->refresh();
    }

    /** Devolve a questão ao estado de rascunho após uma correção. */
    public function reabrirParaCorrecao(Questao $questao): Questao
    {
        if ($questao->status === StatusQuestao::Rejeitada) {
            $questao->update(['status' => StatusQuestao::Rascunho]);
        }

        return $questao;
    }
}
