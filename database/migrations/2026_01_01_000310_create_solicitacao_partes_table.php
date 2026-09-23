<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada parte é um par disciplina + professor dentro da solicitação.
     * Uma prova reúne várias, e cada professor responde só a sua.
     */
    public function up(): void
    {
        Schema::create('solicitacao_partes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitacao_id')->constrained('solicitacoes_prova')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('professor_id')->constrained('usuarios')->restrictOnDelete();
            $table->unsignedSmallInteger('ordem');
            $table->unsignedSmallInteger('quantidade_questoes');
            $table->string('status', 20)->default('aberta')->index();
            // O envio é por parte: cada professor entrega quando termina.
            $table->dateTime('enviada_em')->nullable();
            $table->boolean('enviada_em_atraso')->default(false);
            $table->text('observacoes')->nullable();
            $table->timestamps();

            // A mesma disciplina não se repete na mesma solicitação.
            $table->unique(['solicitacao_id', 'disciplina_id']);
            $table->unique(['solicitacao_id', 'ordem']);
            $table->index(['professor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solicitacao_partes');
    }
};
