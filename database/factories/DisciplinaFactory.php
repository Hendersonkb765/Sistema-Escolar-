<?php

namespace Database\Factories;

use App\Enums\StatusRegistro;
use App\Models\Curso;
use App\Models\Disciplina;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Disciplina>
 */
class DisciplinaFactory extends Factory
{
    protected $model = Disciplina::class;

    public function definition(): array
    {
        $nome = fake()->randomElement([
            'Lógica de Programação',
            'Redes de Computadores',
            'Back-end',
            'Front-end',
            'Banco de Dados',
        ]);

        return [
            'curso_id' => Curso::factory(),
            'nome' => $nome,
            'codigo' => Str::upper(Str::substr(Str::slug($nome), 0, 3)).fake()->unique()->numberBetween(100, 999),
            'periodo' => 1,
            'carga_horaria' => fake()->randomElement([40, 60, 80]),
            'status' => StatusRegistro::Ativo,
        ];
    }

    public function doCurso(Curso $curso): static
    {
        return $this->state(fn () => ['curso_id' => $curso->getKey()]);
    }

    public function noPeriodo(int $periodo): static
    {
        return $this->state(fn () => ['periodo' => $periodo]);
    }

    public function chamada(string $nome): static
    {
        return $this->state(fn () => ['nome' => $nome]);
    }
}
