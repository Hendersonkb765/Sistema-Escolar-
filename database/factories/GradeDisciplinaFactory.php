<?php

namespace Database\Factories;

use App\Models\Disciplina;
use App\Models\GradeCurricular;
use App\Models\GradeDisciplina;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeDisciplina>
 */
class GradeDisciplinaFactory extends Factory
{
    protected $model = GradeDisciplina::class;

    public function definition(): array
    {
        return [
            'grade_curricular_id' => GradeCurricular::factory(),
            'disciplina_id' => Disciplina::factory(),
            'ano_curso' => 1,
            'carga_horaria' => fake()->randomElement([40, 60, 80]),
        ];
    }

    public function noAno(int $ano): static
    {
        return $this->state(fn () => ['ano_curso' => $ano]);
    }
}
