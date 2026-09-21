<?php

namespace Database\Factories;

use App\Enums\StatusAluno;
use App\Models\Aluno;
use App\Models\Turma;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Aluno>
 */
class AlunoFactory extends Factory
{
    protected $model = Aluno::class;

    public function definition(): array
    {
        return [
            'turma_id' => Turma::factory(),
            'nome' => fake()->name(),
            'matricula' => (string) fake()->unique()->numberBetween(100000, 999999),
            'status' => StatusAluno::Ativo,
        ];
    }

    public function naTurma(Turma $turma): static
    {
        return $this->state(fn () => ['turma_id' => $turma->getKey()]);
    }
}
