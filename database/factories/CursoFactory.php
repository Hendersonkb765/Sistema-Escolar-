<?php

namespace Database\Factories;

use App\Enums\StatusRegistro;
use App\Models\Curso;
use App\Models\Eixo;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Curso>
 */
class CursoFactory extends Factory
{
    protected $model = Curso::class;

    public function definition(): array
    {
        // Nome único: dois cursos com o mesmo nome tornavam intermitente
        // qualquer teste que afirme "não vejo o curso do outro eixo".
        $nome = fake()->randomElement([
            'Desenvolvimento de Sistemas',
            'Administração',
            'Enfermagem',
            'Mecatrônica',
            'Logística',
        ]).' '.fake()->unique()->numberBetween(100, 999);

        return [
            'eixo_id' => Eixo::factory(),
            'nome' => $nome,
            'codigo' => Str::upper(Str::substr(Str::slug($nome), 0, 2)).fake()->unique()->numberBetween(100, 999),
            'duracao_anos' => fake()->randomElement([2, 3]),
            'status' => StatusRegistro::Ativo,
        ];
    }

    public function noEixo(Eixo $eixo): static
    {
        return $this->state(fn () => ['eixo_id' => $eixo->getKey()]);
    }
}
