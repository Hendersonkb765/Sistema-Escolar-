<?php

namespace App\Actions\Academico;

use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\GradeDisciplina;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Avanço de ano de uma turma.
 *
 * O estado anterior é sempre preservado em `turma_historicos` antes da
 * mudança, e a grade congelada na turma não é trocada: uma versão nova de
 * grade jamais reescreve retroativamente o percurso de quem já começou.
 *
 * Duas modalidades:
 *
 * - promoção da própria turma (padrão): 2DS passa a ser 3DS e os alunos
 *   seguem juntos;
 * - remanejamento: os alunos vão para uma turma destino já existente do
 *   ano seguinte, e cada movimentação gera `aluno_historicos`.
 */
class AvancarTurmaAction
{
    public function __construct(
        protected RegistrarHistoricoDeTurma $historico,
    ) {}

    /**
     * @return array{turma: Turma, disciplinas: Collection<int, GradeDisciplina>, alunos_movidos: int}
     */
    public function executar(
        Turma $turma,
        User $autor,
        ?Turma $turmaDestino = null,
        ?string $novaIdentificacao = null,
        ?string $novoPeriodoLetivo = null,
        ?string $observacoes = null,
    ): array {
        Gate::forUser($autor)->authorize('avancarAno', $turma);

        $anoAnterior = $turma->ano_curso;
        $novoAno = $anoAnterior + 1;

        $this->validar($turma, $turmaDestino);

        if ($turmaDestino === null) {
            $this->recusarColisaoDeIdentificacao(
                turma: $turma,
                identificacao: $novaIdentificacao ?: $turma->identificacaoParaAno($novoAno),
                periodoLetivo: $novoPeriodoLetivo ?: $turma->periodo_letivo,
            );
        }

        return DB::transaction(function () use (
            $turma, $autor, $turmaDestino, $novaIdentificacao,
            $novoPeriodoLetivo, $observacoes, $anoAnterior, $novoAno
        ) {
            // 1. Estado anterior preservado antes de qualquer escrita.
            $this->historico->executar(
                turma: $turma,
                evento: EventoHistorico::TurmaAvancoAno,
                autor: $autor,
                observacoes: $observacoes,
                metadados: [
                    'ano_destino' => $novoAno,
                    'turma_destino_id' => $turmaDestino?->getKey(),
                ],
            );

            $alunosMovidos = 0;

            if ($turmaDestino !== null) {
                // 2a. Remanejamento: a turma atual encerra e os alunos vão
                //     para a turma do ano seguinte, um histórico por aluno.
                $alunosMovidos = $this->moverAlunos($turma, $turmaDestino, $autor);

                $turma->update(['status' => StatusTurma::Concluida]);

                $turmaAtualizada = $turmaDestino->refresh();
            } else {
                // 2b. Promoção: a própria turma avança de ano.
                $turma->update([
                    'ano_curso' => $novoAno,
                    'identificacao' => $novaIdentificacao ?: $turma->identificacaoParaAno($novoAno),
                    'periodo_letivo' => $novoPeriodoLetivo ?: $turma->periodo_letivo,
                ]);

                $turmaAtualizada = $turma->refresh();
            }

            // 3. Disciplinas do novo ano, lidas da grade congelada da turma.
            $disciplinas = $turmaAtualizada->disciplinasDoAno();

            activity('turma')
                ->performedOn($turmaAtualizada)
                ->causedBy($autor)
                ->withProperties([
                    'ano_anterior' => $anoAnterior,
                    'ano_novo' => $turmaAtualizada->ano_curso,
                    'turma_destino_id' => $turmaDestino?->getKey(),
                    'alunos_movidos' => $alunosMovidos,
                    'disciplinas_do_ano' => $disciplinas->pluck('disciplina_id')->all(),
                ])
                ->log("Turma avançada do {$anoAnterior}º para o {$turmaAtualizada->ano_curso}º ano");

            return [
                'turma' => $turmaAtualizada,
                'disciplinas' => $disciplinas,
                'alunos_movidos' => $alunosMovidos,
            ];
        });
    }

    protected function validar(Turma $turma, ?Turma $turmaDestino): void
    {
        if (! $turma->podeAvancar()) {
            throw RegraDeNegocioException::porque(
                $turma->ano_curso >= $turma->anoFinal()
                    ? "A turma já está no {$turma->anoFinal()}º ano, último do curso."
                    : 'Apenas turmas ativas podem avançar de ano.'
            );
        }

        if ($turmaDestino === null) {
            return;
        }

        if ($turmaDestino->is($turma)) {
            throw RegraDeNegocioException::porque('A turma de destino deve ser diferente da turma de origem.');
        }

        if ((int) $turmaDestino->curso_id !== (int) $turma->curso_id) {
            throw RegraDeNegocioException::porque('A turma de destino pertence a outro curso.');
        }

        if ((int) $turmaDestino->ano_curso !== $turma->ano_curso + 1) {
            throw RegraDeNegocioException::porque(
                "A turma de destino precisa estar no {$turma->ano_curso}º+1 ano do curso."
            );
        }
    }

    /**
     * Promover a turma mantendo o período letivo esbarra na turma do ano
     * seguinte que já existe nesse mesmo período — o caso comum de uma
     * escola com 1DS, 2DS e 3DS rodando juntas. Em vez de estourar a
     * unicidade no banco, explicamos as duas saídas reais.
     */
    protected function recusarColisaoDeIdentificacao(
        Turma $turma,
        string $identificacao,
        string $periodoLetivo,
    ): void {
        $conflitante = Turma::query()
            ->where('curso_id', $turma->curso_id)
            ->where('identificacao', $identificacao)
            ->where('periodo_letivo', $periodoLetivo)
            ->whereKeyNot($turma->getKey())
            ->first();

        if ($conflitante === null) {
            return;
        }

        throw RegraDeNegocioException::porque(
            "Já existe a turma {$identificacao} em {$periodoLetivo}. "
            .'Mova os alunos para ela, avance para um novo período letivo '
            .'ou use outra identificação.'
        );
    }

    protected function moverAlunos(Turma $origem, Turma $destino, User $autor): int
    {
        $movidos = 0;

        $origem->alunos()
            ->where('status', StatusAluno::Ativo)
            ->each(function (Aluno $aluno) use ($destino, $autor, &$movidos) {
                $turmaAnteriorId = $aluno->turma_id;

                $aluno->update(['turma_id' => $destino->getKey()]);

                AlunoHistorico::create([
                    'aluno_id' => $aluno->getKey(),
                    'evento' => EventoHistorico::AlunoTrocaTurma,
                    'turma_anterior_id' => $turmaAnteriorId,
                    'turma_nova_id' => $destino->getKey(),
                    'status_anterior' => $aluno->status->value,
                    'status_novo' => $aluno->status->value,
                    'motivo' => 'Avanço de ano da turma',
                    'registrado_por' => $autor->getKey(),
                ]);

                $movidos++;
            });

        return $movidos;
    }
}
