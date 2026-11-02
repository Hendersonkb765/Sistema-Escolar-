<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A configuração da escola que o PAEET Admin altera pela tela: nome da
 * instituição, aulas por dia, intervalos padrão.
 *
 * Chave e valor, porque são poucos itens e de formatos diferentes. Chave
 * ausente é "não alterado": vale o que está em config/instituicao.php.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuracoes', function (Blueprint $table) {
            $table->string('chave', 60)->primary();
            $table->json('valor');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuracoes');
    }
};
