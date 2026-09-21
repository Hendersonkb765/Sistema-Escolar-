<?php

namespace Database\Factories;

use App\Enums\StatusRegistro;
use App\Models\Disciplina;
use App\Models\Eixo;
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
            'Processos de Desenvolvimento',
            'Banco de Dados',
            'Redes de Computadores',
            'Gestão de Projetos',
        ]);

        return [
            'eixo_id' => Eixo::factory(),
            'nome' => $nome,
            'codigo' => Str::upper(Str::substr(Str::slug($nome), 0, 3)).fake()->unique()->numberBetween(100, 999),
            'status' => StatusRegistro::Ativo,
        ];
    }

    public function noEixo(Eixo $eixo): static
    {
        return $this->state(fn () => ['eixo_id' => $eixo->getKey()]);
    }

    public function chamada(string $nome): static
    {
        return $this->state(fn () => ['nome' => $nome]);
    }
}
