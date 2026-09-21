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
            $table->decimal('peso', 5, 2);
            $table->timestamps();

            $table->unique(['solicitacao_id', 'ordem']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_itens');
    }
};
