<?php

namespace App\Actions\Academico;

use App\Enums\StatusGrade;
use App\Models\GradeCurricular;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Alterar uma grade em uso nunca edita a versão existente: cria-se uma
 * nova versão em rascunho, copiando as disciplinas, para então ajustar o
 * que mudou (por exemplo, mover Banco de Dados do 2º para o 3º ano).
 *
 * As turmas já abertas continuam apontando para a versão antiga, que
 * permanece intacta.
 */
class CriarNovaVersaoDeGradeAction
{
    public function executar(
        GradeCurricular $origem,
        User $autor,
        ?int $anoVigencia = null,
        ?string $observacoes = null,
    ): GradeCurricular {
        Gate::forUser($autor)->authorize('novaVersao', $origem);

        return DB::transaction(function () use ($origem, $autor, $anoVigencia, $observacoes) {
            $proximaVersao = (int) GradeCurricular::query()
                ->withTrashed()
                ->where('curso_id', $origem->curso_id)
                ->max('versao') + 1;

            $nova = GradeCurricular::create([
                'curso_id' => $origem->curso_id,
                'versao' => $proximaVersao,
                'ano_vigencia' => $anoVigencia ?? (int) now()->format('Y'),
                'status' => StatusGrade::Rascunho,
                'observacoes' => $observacoes,
                'criado_por' => $autor->getKey(),
                'origem_grade_id' => $origem->getKey(),
            ]);

            foreach ($origem->disciplinas()->get() as $item) {
                $nova->disciplinas()->create([
                    'disciplina_id' => $item->disciplina_id,
                    'ano_curso' => $item->ano_curso,
                    'carga_horaria' => $item->carga_horaria,
                ]);
            }

            activity('grade')
                ->performedOn($nova)
                ->causedBy($autor)
                ->withProperties([
                    'origem_versao' => $origem->versao,
                    'disciplinas_copiadas' => $origem->disciplinas()->count(),
                ])
                ->log("Versão {$proximaVersao} criada a partir da versão {$origem->versao}");

            return $nova->refresh();
        });
    }
}
