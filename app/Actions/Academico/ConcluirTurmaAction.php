<?php

namespace App\Actions\Academico;

use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Enums\StatusTurma;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * O fim do percurso de uma turma.
 *
 * `AvancarTurmaAction` leva a turma de um período ao seguinte; no último
 * não há seguinte, e o que resta é encerrar. A turma passa a
 * {@see StatusTurma::Concluida} e sai de circulação sozinha: o formulário
 * de solicitação só oferece turmas ativas.
 *
 * Concluída, e não arquivada: a turma chegou ao fim do curso, que é
 * diferente de ter sido tirada de circulação no meio do caminho.
 * `StatusTurma::Arquivada` continua reservada para esse outro caso.
 *
 * Os alunos ativos passam a {@see StatusAluno::Concluinte} — é o que o
 * status existe para dizer, e cada mudança gera `aluno_historicos`. Quem
 * já estava transferido ou inativo fica como está: não concluiu.
 *
 * Como em todo o resto do módulo, o estado anterior é gravado em
 * `turma_historicos` antes de qualquer escrita, dentro da transação.
 */
class ConcluirTurmaAction
{
    public function __construct(
        protected RegistrarHistoricoDeTurma $historico,
    ) {}

    /**
     * @return array{turma: Turma, concluintes: int}
     */
    public function executar(
        Turma $turma,
        User $autor,
        ?string $observacoes = null,
    ): array {
        Gate::forUser($autor)->authorize('encerrar', $turma);

        $this->validar($turma);

        return DB::transaction(function () use ($turma, $autor, $observacoes) {
            // 1. Estado anterior preservado antes de qualquer escrita.
            $this->historico->executar(
                turma: $turma,
                evento: EventoHistorico::TurmaEncerrada,
                autor: $autor,
                observacoes: $observacoes,
                metadados: ['periodo_final' => $turma->periodoFinal()],
            );

            $concluintes = $this->concluirAlunos($turma, $autor);

            $turma->update(['status' => StatusTurma::Concluida]);

            activity('turma')
                ->performedOn($turma)
                ->causedBy($autor)
                ->withProperties([
                    'periodo' => $turma->periodo,
                    'periodo_final' => $turma->periodoFinal(),
                    'concluintes' => $concluintes,
                ])
                ->log("Turma concluída no {$turma->periodo}º período");

            return ['turma' => $turma->refresh(), 'concluintes' => $concluintes];
        });
    }

    protected function validar(Turma $turma): void
    {
        if ($turma->status !== StatusTurma::Ativa) {
            throw RegraDeNegocioException::porque(
                "Esta turma já está {$turma->status->rotulo()} e não pode ser concluída de novo."
            );
        }

        if ($turma->periodo < $turma->periodoFinal()) {
            throw RegraDeNegocioException::porque(
                "A turma está no {$turma->periodo}º período, e o curso vai até o "
                ."{$turma->periodoFinal()}º. Avance o ano até o último período antes de concluir."
            );
        }
    }

    /**
     * Quem estava ativo passa a concluinte, um histórico por aluno.
     *
     * `AlunoHistorico` é append-only: o registro guarda o status que
     * existia antes, e é por ele que se responde depois "quando este
     * aluno concluiu".
     */
    protected function concluirAlunos(Turma $turma, User $autor): int
    {
        $concluidos = 0;

        $turma->alunos()
            ->where('status', StatusAluno::Ativo)
            ->each(function (Aluno $aluno) use ($turma, $autor, &$concluidos) {
                $anterior = $aluno->status;

                $aluno->update(['status' => StatusAluno::Concluinte]);

                AlunoHistorico::create([
                    'aluno_id' => $aluno->getKey(),
                    'evento' => EventoHistorico::AlunoStatusAlterado,
                    'turma_anterior_id' => $turma->getKey(),
                    'turma_nova_id' => $turma->getKey(),
                    'status_anterior' => $anterior->value,
                    'status_novo' => StatusAluno::Concluinte->value,
                    'motivo' => 'Conclusão da turma',
                    'registrado_por' => $autor->getKey(),
                ]);

                $concluidos++;
            });

        return $concluidos;
    }
}
