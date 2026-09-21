<?php

namespace Database\Factories;

use App\Enums\StatusRegistro;
use App\Models\Eixo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Eixo>
 */
class EixoFactory extends Factory
{
    protected $model = Eixo::class;

    public function definition(): array
    {
        $nome = fake()->randomElement(['Tecnologia', 'Administração', 'Saúde', 'Indústria', 'Ambiente']);

        return [
            'nome' => $nome,
            'codigo' => Str::upper(Str::substr(Str::slug($nome), 0, 3)).fake()->unique()->numberBetween(100, 999),
            'status' => StatusRegistro::Ativo,
        ];
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['status' => StatusRegistro::Inativo]);
    }
}
