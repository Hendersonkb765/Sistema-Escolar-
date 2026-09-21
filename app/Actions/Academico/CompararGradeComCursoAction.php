<?php

namespace App\Actions\Academico;

use App\Models\Curso;
use App\Models\GradeCurricular;
use Illuminate\Support\Collection;

/**
 * Compara o cadastro atual de disciplinas do curso com a foto da grade
 * vigente, para a interface avisar que há mudanças ainda não publicadas.
 */
class CompararGradeComCursoAction
{
    /**
     * @return array{divergente: bool, incluidas: Collection, removidas: Collection, movidas: Collection}
     */
    public function executar(Curso $curso, ?GradeCurricular $grade = null): array
    {
        $grade ??= $curso->gradeVigente();

        $atuais = $curso->disciplinas()->ativas()->get()->keyBy('id');

        $naFoto = $grade === null
            ? collect()
            : $grade->disciplinas()->with('disciplina')->get()->keyBy('disciplina_id');

        $incluidas = $atuais->reject(fn ($disciplina, $id) => $naFoto->has($id))->values();

        $removidas = $naFoto
            ->reject(fn ($item, $id) => $atuais->has($id))
            ->map(fn ($item) => $item->disciplina)
            ->filter()
            ->values();

        $movidas = $atuais
            ->filter(function ($disciplina, $id) use ($naFoto) {
                $item = $naFoto->get($id);

                return $item !== null
                    && ((int) $item->periodo !== (int) $disciplina->periodo
                        || (int) $item->carga_horaria !== (int) $disciplina->carga_horaria);
            })
            ->map(fn ($disciplina) => [
                'disciplina' => $disciplina,
                'periodo_publicado' => (int) $naFoto->get($disciplina->id)->periodo,
                'periodo_atual' => (int) $disciplina->periodo,
            ])
            ->values();

        return [
            'divergente' => $incluidas->isNotEmpty() || $removidas->isNotEmpty() || $movidas->isNotEmpty(),
            'incluidas' => $incluidas,
            'removidas' => $removidas,
            'movidas' => $movidas,
        ];
    }
}
