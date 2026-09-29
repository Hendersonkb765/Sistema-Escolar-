<?php

namespace App\Actions\Resultado;

use App\Enums\StatusImportacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\Importacao;
use App\Models\ProvaQuestao;
use App\Models\User;
use App\Support\ConciliadorDeAlunos;
use App\Support\LinhaDeResultado;
use App\Support\PlanilhaDeResultados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Primeiro passo da importação: conferir sem gravar nada.
 *
 * O relatório diz, linha a linha, de qual aluno ela é e por qual
 * critério foi reconhecida — ou por que não deu. Só depois de ver isso é
 * que se confirma.
 *
 * Nada aqui toca no cadastro do aluno: o nome no sistema continua como
 * está, e a planilha serve apenas para dizer de quem é cada resultado.
 */
class ConferirImportacaoAction
{
    public function executar(Importacao $importacao, User $autor): Importacao
    {
        Gate::forUser($autor)->authorize('update', $importacao);

        $prova = $importacao->loadMissing('prova.turma')->prova;

        if (! $prova->status->aceitaImportacao()) {
            throw RegraDeNegocioException::porque(
                'A prova ainda não foi gerada ou já foi encerrada, e não aceita importação de resultados.'
            );
        }

        $planilha = PlanilhaDeResultados::ler(Storage::disk('local')->path($importacao->arquivo));

        $questoes = ProvaQuestao::query()
            ->where('prova_id', $prova->getKey())
            ->orderBy('numero')
            ->get(['id', 'numero', 'disciplina_id', 'peso']);

        $conciliador = new ConciliadorDeAlunos(
            Aluno::query()
                ->where('turma_id', $prova->turma_id)
                ->orderBy('nome')
                ->get(['id', 'nome', 'ra', 'turma_id'])
        );

        $relatorio = [];
        $vistos = [];

        foreach ($planilha->linhas as $linha) {
            $relatorio[] = $this->conferirLinha($linha, $conciliador, $questoes, $vistos);
        }

        $erros = collect($relatorio)->where('erro', '!=', null)->count();

        $importacao->update([
            'status' => $erros === count($relatorio) && $relatorio !== []
                ? StatusImportacao::Falhou
                : StatusImportacao::Validada,
            'relatorio' => [
                'questoes_na_prova' => $questoes->count(),
                'questoes_na_planilha' => count($planilha->questoes),
                'linhas' => $relatorio,
            ],
            'total_linhas' => count($relatorio),
            'total_erros' => $erros,
        ]);

        activity('importacao')
            ->performedOn($importacao)
            ->causedBy($autor)
            ->withProperties(['linhas' => count($relatorio), 'erros' => $erros])
            ->log('Importação conferida');

        return $importacao->refresh();
    }

    /**
     * @param  Collection<int, ProvaQuestao>  $questoes
     * @param  array<int, int>  $vistos  aluno_id => linha onde já apareceu
     * @return array<string, mixed>
     */
    protected function conferirLinha(
        LinhaDeResultado $linha,
        ConciliadorDeAlunos $conciliador,
        $questoes,
        array &$vistos,
    ): array {
        $achado = $conciliador->conciliar($linha);
        $aluno = $achado['aluno'];

        $registro = [
            'linha' => $linha->linha,
            'nome_na_planilha' => $linha->nomeCompleto(),
            'identificacao' => $linha->identificacao,
            'aluno_id' => $aluno?->getKey(),
            'aluno' => $aluno?->nome,
            'criterio' => $achado['criterio'],
            'candidatos' => $achado['candidatos'],
            'acertos' => $linha->acertos(),
            'total' => $questoes->count(),
            'erro' => null,
            'avisos' => [],
        ];

        if ($aluno === null) {
            $registro['erro'] = $achado['criterio'] === ConciliadorDeAlunos::AMBIGUO
                ? 'Mais de um aluno da turma tem este nome. Ajuste o nome na planilha ou use o RA.'
                : 'Nenhum aluno desta turma corresponde a este nome.';

            return $registro;
        }

        if (isset($vistos[$aluno->getKey()])) {
            $registro['erro'] = "Este aluno já aparece na linha {$vistos[$aluno->getKey()]}.";

            return $registro;
        }

        $vistos[$aluno->getKey()] = $linha->linha;

        // Questão da prova sem coluna na planilha viraria erro silencioso.
        $faltando = $questoes
            ->pluck('numero')
            ->reject(fn (int $numero) => array_key_exists($numero, $linha->respostas));

        if ($faltando->isNotEmpty()) {
            $registro['erro'] = 'A planilha não traz a(s) questão(ões) '
                .$faltando->implode(', ').' desta prova.';

            return $registro;
        }

        if (! $linha->contagemConfere()) {
            $registro['avisos'][] = "O leitor informou {$linha->acertosInformados} acerto(s), "
                ."mas as colunas de questão somam {$linha->acertos()}.";
        }

        $sobrando = collect(array_keys($linha->respostas))
            ->reject(fn (int $numero) => $questoes->contains('numero', $numero));

        if ($sobrando->isNotEmpty()) {
            $registro['avisos'][] = 'A planilha traz a(s) questão(ões) '
                .$sobrando->implode(', ').', que não existe(m) nesta prova; será(ão) ignorada(s).';
        }

        return $registro;
    }
}
