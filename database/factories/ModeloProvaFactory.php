<?php

namespace Database\Factories;

use App\Models\Eixo;
use App\Models\ModeloProva;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ModeloProva>
 */
class ModeloProvaFactory extends Factory
{
    protected $model = ModeloProva::class;

    public function definition(): array
    {
        return [
            'eixo_id' => Eixo::factory(),
            'nome' => 'Modelo padrão',
            'nome_avaliacao' => 'Avaliação',
            'instituicao' => 'Escola Técnica',
            'cabecalho' => null,
            'instrucoes' => 'Leia com atenção. Marque apenas uma alternativa por questão.',
            'campos_identificacao' => ['aluno', 'matricula', 'turma', 'curso', 'data'],
            'layout' => ['colunas' => 2, 'fonte' => 'sans', 'tamanho' => 11],
            'rodape' => 'Boa prova!',
            'versao' => 1,
            'ativo' => true,
        ];
    }
}
