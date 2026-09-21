<?php

namespace App\Actions\Academico;

use App\Enums\StatusGrade;
use App\Exceptions\RegraDeNegocioException;
use App\Models\GradeCurricular;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Publica um rascunho como versão vigente e arquiva a que estava em uso.
 * Arquivar não apaga: turmas antigas continuam ligadas à versão que
 * congelaram, e as provas já geradas seguem intocadas.
 */
class PublicarGradeAction
{
    public function executar(GradeCurricular $grade, User $autor): GradeCurricular
    {
        Gate::forUser($autor)->authorize('publicar', $grade);

        if (! $grade->disciplinas()->exists()) {
            throw RegraDeNegocioException::porque(
                'Uma grade sem disciplinas não pode entrar em vigência.'
            );
        }

        return DB::transaction(function () use ($grade, $autor) {
            $anteriores = GradeCurricular::query()
                ->where('curso_id', $grade->curso_id)
                ->where('status', StatusGrade::Vigente)
                ->whereKeyNot($grade->getKey())
                ->get();

            foreach ($anteriores as $anterior) {
                $anterior->update(['status' => StatusGrade::Arquivada]);

                activity('grade')
                    ->performedOn($anterior)
                    ->causedBy($autor)
                    ->withProperties(['substituida_por' => $grade->versao])
                    ->log("Versão {$anterior->versao} arquivada");
            }

            $grade->update(['status' => StatusGrade::Vigente]);

            activity('grade')
                ->performedOn($grade)
                ->causedBy($autor)
                ->withProperties([
                    'versoes_arquivadas' => $anteriores->pluck('versao')->all(),
                    'turmas_preservadas' => $anteriores->sum(fn ($g) => $g->turmas()->count()),
                ])
                ->log("Versão {$grade->versao} entrou em vigência");

            return $grade->refresh();
        });
    }
}
