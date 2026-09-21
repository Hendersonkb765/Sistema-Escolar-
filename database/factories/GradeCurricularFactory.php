<?php

namespace Database\Factories;

use App\Enums\StatusGrade;
use App\Models\Curso;
use App\Models\GradeCurricular;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeCurricular>
 */
class GradeCurricularFactory extends Factory
{
    protected $model = GradeCurricular::class;

    public function definition(): array
    {
        return [
            'curso_id' => Curso::factory(),
            // Sequencial por curso: (curso_id, versao) é único.
            'versao' => fn (array $atributos) => (int) GradeCurricular::query()
                ->where('curso_id', $atributos['curso_id'])
                ->max('versao') + 1,
            'ano_vigencia' => (int) now()->format('Y'),
            'status' => StatusGrade::Vigente,
            'observacoes' => null,
        ];
    }

    public function doCurso(Curso $curso): static
    {
        return $this->state(fn () => ['curso_id' => $curso->getKey()]);
    }

    public function versao(int $versao): static
    {
        return $this->state(fn () => ['versao' => $versao]);
    }

    public function rascunho(): static
    {
        return $this->state(fn () => ['status' => StatusGrade::Rascunho]);
    }

    public function arquivada(): static
    {
        return $this->state(fn () => ['status' => StatusGrade::Arquivada]);
    }
}
