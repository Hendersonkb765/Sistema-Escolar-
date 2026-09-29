<?php

namespace App\Actions\Resultado;

use App\Enums\StatusImportacao;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Importacao;
use App\Models\ProvaQuestao;
use App\Models\ResultadoAluno;
use App\Models\User;
use App\Support\LinhaDeResultado;
use App\Support\PlanilhaDeResultados;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Segundo passo: gravar o que a conferência aprovou.
 *
 * Só entram as linhas sem erro — as recusadas continuam de fora, e o
 * relatório segue dizendo por quê. Tudo numa transação: ou a importação
 * inteira vale, ou nenhuma linha dela vale.
 */
class ConfirmarImportacaoAction
{
    public function __construct(
        protected CalcularNotasAction $calcular,
    ) {}

    public function executar(Importacao $importacao, User $autor): Importacao
    {
        Gate::forUser($autor)->authorize('update', $importacao);

        if ($importacao->status !== StatusImportacao::Validada) {
            throw RegraDeNegocioException::porque(
                'Confira a planilha antes de confirmar: só uma importação validada pode ser gravada.'
            );
        }

        $aprovadas = collect($importacao->relatorio['linhas'] ?? [])
            ->whereNull('erro')
            ->keyBy('linha');

        if ($aprovadas->isEmpty()) {
            throw RegraDeNegocioException::porque(
                'Nenhuma linha da planilha passou na conferência, então não há o que gravar.'
            );
        }

        $prova = $importacao->loadMissing('prova')->prova;

        $questoes = ProvaQuestao::query()
            ->where('prova_id', $prova->getKey())
            ->get(['id', 'numero', 'disciplina_id', 'peso'])
            ->keyBy('numero');

        $planilha = PlanilhaDeResultados::ler(Storage::disk('local')->path($importacao->arquivo));

        return DB::transaction(function () use ($importacao, $autor, $aprovadas, $planilha, $questoes) {
            foreach ($planilha->linhas as $linha) {
                $aprovada = $aprovadas->get($linha->linha);

                if ($aprovada === null) {
                    continue;
                }

                $this->gravar($importacao, $linha, (int) $aprovada['aluno_id'], $questoes);
            }

            $importacao->update([
                'status' => StatusImportacao::Confirmada,
                'confirmada_em' => now(),
            ]);

            activity('importacao')
                ->performedOn($importacao)
                ->causedBy($autor)
                ->withProperties(['alunos' => $aprovadas->count()])
                ->log('Importação confirmada');

            return $importacao->refresh();
        });
    }

    /**
     * @param  Collection<int, ProvaQuestao>  $questoes  indexada pelo número
     */
    protected function gravar(Importacao $importacao, LinhaDeResultado $linha, int $alunoId, Collection $questoes): void
    {
        // Reimportar a mesma prova substitui o resultado anterior do
        // aluno: o que vale é a última leitura da folha, não a soma.
        $resultado = ResultadoAluno::query()->updateOrCreate(
            ['prova_id' => $importacao->prova_id, 'aluno_id' => $alunoId],
            ['importacao_id' => $importacao->getKey()],
        );

        $resultado->respostas()->delete();

        foreach ($linha->respostas as $numero => $acertou) {
            $questao = $questoes->get($numero);

            // Coluna que não corresponde a questão desta prova é sobra.
            if ($questao === null) {
                continue;
            }

            $resultado->respostas()->create([
                'prova_questao_id' => $questao->getKey(),
                // O leitor entrega acerto ou erro, não a letra marcada.
                'alternativa_marcada' => null,
                'acertou' => $acertou,
                'peso' => $questao->peso,
                'pontuacao' => $acertou ? $questao->peso : 0,
            ]);
        }

        $this->calcular->executar($resultado->refresh());
    }
}
