<?php

namespace App\Actions\Avaliacao;

use App\Enums\StatusQuestao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Questao;
use App\Models\SolicitacaoParte;
use App\Models\User;
use App\Support\PlanilhaDeQuestoes;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Preenche as questões de uma parte a partir de uma planilha.
 *
 * Em dois passos: {@see conferir()} não grava nada e diz, linha por
 * linha, o que precisa de correção e o que vai acontecer — quais
 * rascunhos serão substituídos, quais questões ficam como estão. Só
 * depois se confirma, e só com a planilha inteira certa: importar metade
 * e deixar o resto para a tela é como uma questão some sem ninguém ver.
 *
 * As questões entram como **rascunho**. O envio para análise continua
 * sendo o da tela, depois de o professor revisar — a planilha preenche,
 * não entrega.
 */
class ImportarQuestoesAction
{
    public function __construct(
        private SalvarQuestaoAction $salvar,
        private SalvarBlocosDaQuestaoAction $blocos,
    ) {}

    /**
     * @return array{
     *     linhas: array<int, array{linha: int, questao: ?int, resumo: ?string, pontuacao: ?float, correta: ?string, substitui: bool, erros: array<int, string>}>,
     *     nao_preenchidas: array<int, int>,
     *     com_erro: int,
     * }
     */
    public function conferir(SolicitacaoParte $parte, string $caminho, User $autor): array
    {
        [$linhas, $naoPreenchidas] = $this->ler($parte, $caminho, $autor);

        return [
            'linhas' => array_map(fn (array $l) => [
                'linha' => $l['linha'],
                'questao' => $l['questao'],
                'resumo' => $l['enunciado'] === null ? null : Str::limit(preg_replace('/\s+/', ' ', $l['enunciado']), 90),
                'pontuacao' => $l['pontuacao'],
                'correta' => $l['correta'],
                'substitui' => $l['substitui'],
                'erros' => $l['erros'],
            ], $linhas),
            'nao_preenchidas' => $naoPreenchidas,
            'com_erro' => count(array_filter($linhas, fn (array $l) => $l['erros'] !== [])),
        ];
    }

    /** @return int quantas questões foram preenchidas */
    public function executar(SolicitacaoParte $parte, string $caminho, User $autor): int
    {
        [$linhas] = $this->ler($parte, $caminho, $autor);

        $comErro = count(array_filter($linhas, fn (array $l) => $l['erros'] !== []));

        if ($comErro > 0) {
            throw RegraDeNegocioException::porque(
                "A planilha tem {$comErro} linha(s) com erro. Corrija as linhas indicadas e confira de novo."
            );
        }

        $questoes = $this->questoesPorOrdem($parte);

        DB::transaction(function () use ($linhas, $questoes, $autor) {
            foreach ($linhas as $linha) {
                /** @var Questao $questao */
                $questao = $questoes->get($linha['questao']);

                $this->salvar->executar(
                    questao: $questao,
                    autor: $autor,
                    enunciado: $linha['enunciado'],
                    alternativas: collect($linha['alternativas'])->map(fn (string $texto, string $letra) => [
                        'letra' => $letra,
                        'texto' => $texto,
                        'correta' => $letra === $linha['correta'],
                    ])->values()->all(),
                    peso: $linha['pontuacao'],
                    habilidade: $linha['habilidade'],
                );

                // O enunciado da planilha substitui o inteiro: código ou
                // imagem de um rascunho anterior não ficam pendurados nele.
                if ($questao->blocos()->exists()) {
                    $this->blocos->executar($questao, $autor, []);
                }
            }
        });

        return count($linhas);
    }

    /**
     * As linhas preenchidas, com os erros de formato e os que dependem do
     * banco, e os números deixados em branco.
     *
     * @return array{0: array<int, array<string, mixed>>, 1: array<int, int>}
     */
    private function ler(SolicitacaoParte $parte, string $caminho, User $autor): array
    {
        Gate::forUser($autor)->authorize('responder', $parte);

        $parte->loadMissing('solicitacao', 'disciplina');

        if ($parte->enviada_em !== null) {
            throw RegraDeNegocioException::porque(
                "As questões de {$parte->disciplina->nome} já foram enviadas. A planilha só preenche questões ainda em rascunho."
            );
        }

        if (! $parte->solicitacao->aceitaEnvio()) {
            throw RegraDeNegocioException::porque('Esta solicitação foi encerrada e não aceita mais alterações.');
        }

        $planilha = PlanilhaDeQuestoes::ler($caminho, (int) $parte->solicitacao->quantidade_alternativas);

        if ($planilha->linhas === []) {
            throw RegraDeNegocioException::porque(
                'Nenhuma questão preenchida na planilha. Preencha a partir da segunda linha, uma questão por linha.'
            );
        }

        $questoes = $this->questoesPorOrdem($parte);
        $total = $questoes->count();
        $vistas = [];
        $linhas = [];

        foreach ($planilha->linhas as $linha) {
            $numero = $linha['questao'];
            $questao = $numero === null ? null : $questoes->get($numero);
            $erros = $linha['erros'];

            if ($numero !== null && $questao === null) {
                $erros[] = "a questão {$numero} não existe: esta disciplina tem {$total} questão(ões), de 1 a {$total}";
            } elseif ($numero !== null && isset($vistas[$numero])) {
                $erros[] = "a questão {$numero} já aparece na linha {$vistas[$numero]}";
            } elseif ($questao !== null && $questao->status === StatusQuestao::Rejeitada) {
                $erros[] = "a questão {$numero} foi devolvida pela coordenação: corrija-a na tela e reenvie";
            } elseif ($questao !== null && $questao->status !== StatusQuestao::Rascunho) {
                $erros[] = "a questão {$numero} já foi enviada e não pode ser substituída";
            }

            if ($numero !== null) {
                $vistas[$numero] ??= $linha['linha'];
            }

            $linhas[] = [
                ...$linha,
                'erros' => $erros,
                'substitui' => $questao !== null && $this->temConteudo($questao),
            ];
        }

        return [$linhas, $planilha->naoPreenchidas];
    }

    /** @return Collection<int, Questao> pela ordem na parte */
    private function questoesPorOrdem(SolicitacaoParte $parte): Collection
    {
        return $parte->questoes()->with('alternativas')->withCount('blocos')->get()->keyBy('ordem');
    }

    /** O rascunho que já tem algo escrito, e que a planilha vai substituir. */
    private function temConteudo(Questao $questao): bool
    {
        return filled($questao->enunciado)
            || filled($questao->habilidade)
            || $questao->blocos_count > 0
            || $questao->alternativas->contains(fn ($a) => filled($a->texto));
    }
}
