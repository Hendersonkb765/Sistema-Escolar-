<?php

namespace Database\Factories;

use App\Enums\StatusTurma;
use App\Models\Curso;
use App\Models\GradeCurricular;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Turma>
 */
class TurmaFactory extends Factory
{
    protected $model = Turma::class;

    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            // A grade nasce do mesmo curso: a turma congela uma versão dele.
            'grade_curricular_id' => fn (array $atributos) => GradeCurricular::factory()
                ->create(['curso_id' => $atributos['curso_id']])
                ->getKey(),
            'ano_curso' => 1,
            'identificacao' => '1DS',
            'periodo_letivo' => (string) now()->format('Y'),
            'status' => StatusTurma::Ativa,
        ];
    }

    /** Amarra turma e grade ao mesmo curso — o caso real. */
    public function doCurso(Curso $curso, ?GradeCurricular $grade = null): static
    {
        $grade ??= GradeCurricular::factory()->doCurso($curso)->create();

        return $this->state(fn () => [
            'curso_id' => $curso->getKey(),
            'grade_curricular_id' => $grade->getKey(),
        ]);
    }

    public function noAno(int $ano, ?string $identificacao = null): static
    {
        return $this->state(fn (array $atributos) => [
            'ano_curso' => $ano,
            'identificacao' => $identificacao ?? $ano.substr((string) ($atributos['identificacao'] ?? '1DS'), 1),
        ]);
    }
}
