<?php

namespace App\Actions\Academico;

use App\Enums\EventoHistorico;
use App\Enums\StatusAluno;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Retira um aluno da turma: ele saiu.
 *
 * Saiu não é sumiu. O vínculo com a turma permanece, e tem de permanecer
 * — as notas que ele tirou, os resultados importados e o histórico dele
 * só fazem sentido presos à turma em que foram tirados. O que muda é a
 * situação: ele deixa de ser contado como ativo, some das contagens de
 * matriculados e não entra em prova nova.
 *
 * É diferente de {@see MoverAlunoDeTurmaAction}, que troca a turma de
 * quem continua estudando, e de excluir o cadastro, que a Policy só
 * permite para quem nunca teve nota — matrícula duplicada, nome digitado
 * errado.
 *
 * O motivo é obrigatório. "Transferido" responde para onde, não por quê,
 * e é o porquê que alguém vai procurar no histórico daqui a um ano.
 */
class DesligarAlunoAction
{
    public function executar(
        Aluno $aluno,
        User $autor,
        StatusAluno $situacao,
        string $motivo,
    ): Aluno {
        Gate::forUser($autor)->authorize('desligar', $aluno);

        $motivo = trim($motivo);

        $this->validar($aluno, $situacao, $motivo);

        return DB::transaction(function () use ($aluno, $autor, $situacao, $motivo) {
            $anterior = $aluno->status;

            $aluno->update(['status' => $situacao]);

            /*
             * `turma_anterior` e `turma_nova` apontam para a mesma turma:
             * o aluno não mudou de lugar, mudou de situação. É o que
             * distingue este registro de uma troca de turma.
             */
            AlunoHistorico::create([
                'aluno_id' => $aluno->getKey(),
                'evento' => EventoHistorico::AlunoStatusAlterado,
                'turma_anterior_id' => $aluno->turma_id,
                'turma_nova_id' => $aluno->turma_id,
                'status_anterior' => $anterior->value,
                'status_novo' => $situacao->value,
                'motivo' => $motivo,
                'registrado_por' => $autor->getKey(),
            ]);

            activity('aluno')
                ->performedOn($aluno)
                ->causedBy($autor)
                ->withProperties([
                    'turma_id' => $aluno->turma_id,
                    'status_anterior' => $anterior->value,
                    'status_novo' => $situacao->value,
                    'motivo' => $motivo,
                ])
                ->log("Aluno desligado da turma como {$situacao->rotulo()}");

            return $aluno->refresh();
        });
    }

    protected function validar(Aluno $aluno, StatusAluno $situacao, string $motivo): void
    {
        if ($aluno->status !== StatusAluno::Ativo) {
            throw RegraDeNegocioException::porque(
                "Este aluno já está {$aluno->status->rotulo()} e não está mais ativo na turma."
            );
        }

        if (! in_array($situacao, StatusAluno::desligamentos(), true)) {
            throw RegraDeNegocioException::porque(
                'Um desligamento leva a Transferido ou a Inativo. '
                .'Concluinte é o fim do curso, e quem o atribui é a conclusão da turma.'
            );
        }

        if ($motivo === '') {
            throw RegraDeNegocioException::porque(
                'Diga por que o aluno está saindo: é o que vai ficar no histórico dele.'
            );
        }
    }
}
