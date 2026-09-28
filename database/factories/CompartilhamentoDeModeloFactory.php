<?php

namespace Database\Factories;

use App\Enums\StatusCompartilhamento;
use App\Models\CompartilhamentoDeModelo;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompartilhamentoDeModelo>
 */
class CompartilhamentoDeModeloFactory extends Factory
{
    protected $model = CompartilhamentoDeModelo::class;

    public function definition(): array
    {
        return [
            'modelo_documento_id' => ModeloDocumento::factory(),
            'remetente_id' => User::factory(),
            'destinatario_id' => User::factory(),
            'status' => StatusCompartilhamento::Pendente,
            'mensagem' => null,
        ];
    }
}
