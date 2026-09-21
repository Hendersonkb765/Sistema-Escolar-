<?php

namespace App\Actions\Academico;

use App\Enums\EventoHistorico;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Aluno;
use App\Models\AlunoHistorico;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Troca de turma individual, sempre acompanhada de histórico. */
class MoverAlunoDeTurmaAction
{
    public function executar(
        Aluno $aluno,
        Turma $destino,
        User $autor,
        ?string $motivo = null,
    ): Aluno {
        Gate::forUser($autor)->authorize('moverDeTurma', $aluno);
        Gate::forUser($autor)->authorize('view', $destino);

        if ((int) $aluno->turma_id === (int) $destino->getKey()) {
            throw RegraDeNegocioException::porque('O aluno já está nesta turma.');
        }

        $matriculaEmUso = Aluno::query()
            ->where('turma_id', $destino->getKey())
            ->where('matricula', $aluno->matricula)
            ->exists();

        if ($matriculaEmUso) {
            throw RegraDeNegocioException::porque(
                "A matrícula {$aluno->matricula} já existe na turma de destino."
            );
        }

        return DB::transaction(function () use ($aluno, $destino, $autor, $motivo) {
            $turmaAnteriorId = $aluno->turma_id;

            $aluno->update(['turma_id' => $destino->getKey()]);

            AlunoHistorico::create([
                'aluno_id' => $aluno->getKey(),
                'evento' => EventoHistorico::AlunoTrocaTurma,
                'turma_anterior_id' => $turmaAnteriorId,
                'turma_nova_id' => $destino->getKey(),
                'status_anterior' => $aluno->status->value,
                'status_novo' => $aluno->status->value,
                'motivo' => $motivo,
                'registrado_por' => $autor->getKey(),
            ]);

            activity('aluno')
                ->performedOn($aluno)
                ->causedBy($autor)
                ->withProperties([
                    'turma_anterior_id' => $turmaAnteriorId,
                    'turma_nova_id' => $destino->getKey(),
                ])
                ->log('Aluno movido de turma');

            return $aluno->refresh();
        });
    }
}
