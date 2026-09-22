<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitacao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes_prova')->cascadeOnDelete();
            $table->unsignedSmallInteger('ordem');
            // O peso vive em `questoes`: quem o define é o professor que
            // escreve a questão, não quem abre a solicitação.
            $table->timestamps();

            $table->unique(['solicitacao_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_itens');
    }
};
