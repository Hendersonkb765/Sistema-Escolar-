<?php

namespace Database\Factories;

use App\Enums\TipoDeDocumento;
use App\Models\Eixo;
use App\Models\ModeloDocumento;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModeloDocumento>
 */
class ModeloDocumentoFactory extends Factory
{
    protected $model = ModeloDocumento::class;

    public function definition(): array
    {
        return [
            'eixo_id' => Eixo::factory(),
            // Nome único de nascença: a busca e os assertDontSee dependem
            // de dois registros não colidirem.
            'nome' => 'Autorização '.fake()->unique()->numerify('####'),
            'descricao' => 'Documento de demonstração',
            'tipo' => TipoDeDocumento::Individual,
            'corpo' => 'Eu, {{ linha: Responsável }}, autorizo o aluno {{ aluno.nome }}, '
                ."RA {{ aluno.ra }}, da turma {{ turma.nome }}, a participar da atividade.\n\n"
                .'{{ turma.periodo_letivo }} — {{ data }}',
            'por_pagina' => 1,
            'ativo' => true,
        ];
    }

    public function noEixo(Eixo $eixo): static
    {
        return $this->state(fn () => ['eixo_id' => $eixo->getKey()]);
    }

    public function coletivo(): static
    {
        return $this->state(fn () => [
            'tipo' => TipoDeDocumento::Coletivo,
            'corpo' => "Relação dos alunos da turma {{ turma.nome }} em {{ data_curta }}.\n\n"
                .'{{ lista_de_alunos }}',
            'por_pagina' => 1,
        ]);
    }

    public function porPagina(int $quantidade): static
    {
        return $this->state(fn () => ['por_pagina' => $quantidade]);
    }
}
