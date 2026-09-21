<?php

namespace App\Actions\Academico;

use App\Enums\StatusGrade;
use App\Exceptions\RegraDeNegocioException;
use App\Models\Curso;
use App\Models\GradeCurricular;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Tira uma foto das disciplinas do curso e a congela como nova versão
 * vigente da grade.
 *
 * O cadastro de disciplinas é o estado vivo e editável; a grade é o
 * registro de como o curso estava num momento. Turmas já abertas seguem
 * apontando para a foto com que começaram, e é isso que impede que mover
 * Back-end do 2º para o 3º período reescreva o percurso de quem já
 * cursou.
 */
class PublicarVersaoDeGradeAction
{
    public function executar(
        Curso $curso,
        User $autor,
        ?int $anoVigencia = null,
        ?string $observacoes = null,
    ): GradeCurricular {
        Gate::forUser($autor)->authorize('create', GradeCurricular::class);
        Gate::forUser($autor)->authorize('view', $curso);

        $disciplinas = $curso->disciplinas()->ativas()->orderBy('periodo')->orderBy('nome')->get();

        if ($disciplinas->isEmpty()) {
            throw RegraDeNegocioException::porque(
                'Cadastre ao menos uma disciplina ativa neste curso antes de publicar a grade.'
            );
        }

        $foraDoCurso = $disciplinas->filter(
            fn ($disciplina) => $disciplina->periodo > $curso->duracao_anos
        );

        if ($foraDoCurso->isNotEmpty()) {
            throw RegraDeNegocioException::porque(
                'Há disciplinas em períodos além da duração do curso: '
                .$foraDoCurso->pluck('nome')->join(', ').'.'
            );
        }

        return DB::transaction(function () use ($curso, $autor, $anoVigencia, $observacoes, $disciplinas) {
            $proximaVersao = (int) GradeCurricular::query()
                ->withTrashed()
                ->where('curso_id', $curso->getKey())
                ->max('versao') + 1;

            $anteriores = GradeCurricular::query()
                ->where('curso_id', $curso->getKey())
                ->where('status', StatusGrade::Vigente)
                ->get();

            foreach ($anteriores as $anterior) {
                $anterior->update(['status' => StatusGrade::Arquivada]);

                activity('grade')
                    ->performedOn($anterior)
                    ->causedBy($autor)
                    ->withProperties([
                        'substituida_por' => $proximaVersao,
                        'turmas_preservadas' => $anterior->turmas()->count(),
                    ])
                    ->log("Versão {$anterior->versao} arquivada");
            }

            $grade = GradeCurricular::create([
                'curso_id' => $curso->getKey(),
                'versao' => $proximaVersao,
                'ano_vigencia' => $anoVigencia ?? (int) now()->format('Y'),
                'status' => StatusGrade::Vigente,
                'observacoes' => $observacoes,
                'criado_por' => $autor->getKey(),
                'origem_grade_id' => $anteriores->first()?->getKey(),
            ]);

            foreach ($disciplinas as $disciplina) {
                $grade->disciplinas()->create([
                    'disciplina_id' => $disciplina->getKey(),
                    'periodo' => $disciplina->periodo,
                    'carga_horaria' => $disciplina->carga_horaria,
                ]);
            }

            activity('grade')
                ->performedOn($grade)
                ->causedBy($autor)
                ->withProperties([
                    'disciplinas' => $disciplinas->count(),
                    'por_periodo' => $disciplinas->groupBy('periodo')->map->count()->all(),
                ])
                ->log("Versão {$proximaVersao} publicada com {$disciplinas->count()} disciplina(s)");

            return $grade->refresh();
        });
    }

    /**
     * Garante que o curso tenha uma grade vigente, publicando a primeira
     * versão quando ainda não há nenhuma. Usado ao abrir a primeira turma
     * de um curso, para não travar o fluxo com uma etapa extra.
     */
    public function garantirVigente(Curso $curso, User $autor): GradeCurricular
    {
        $vigente = $curso->gradeVigente();

        return $vigente ?? $this->executar($curso, $autor);
    }
}
