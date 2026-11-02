<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ausência que o professor informa com antecedência, e as aulas que ele
 * daria naquele dia.
 *
 * Cada aula copia turma, disciplina e horário do quadro, e o título da
 * aula do escopo: o quadro e o escopo mudam ao longo do ano, e o pedido
 * diz o que era quando foi feito. O vínculo com a linha original fica,
 * nulo se ela sumir.
 *
 * Não há chave única por professor e data: uma ausência cancelada não
 * impede informar outra no mesmo dia. Quem barra a duplicata é a Action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ausencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('professor_id')->constrained('usuarios')->restrictOnDelete();
            $table->date('data');
            $table->string('motivo', 500)->nullable();
            $table->string('status', 20)->default('pendente');
            $table->foreignId('ciente_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('ciente_em')->nullable();
            $table->timestamp('cancelada_em')->nullable();
            $table->timestamps();

            $table->index(['professor_id', 'data']);
            $table->index(['data', 'status']);
        });

        Schema::create('ausencia_aulas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ausencia_id')->constrained('ausencias')->cascadeOnDelete();
            $table->foreignId('quadro_horario_id')->nullable()->constrained('quadro_horarios')->nullOnDelete();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->unsignedTinyInteger('aula_numero');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->foreignId('escopo_aula_id')->nullable()->constrained('escopo_aulas')->nullOnDelete();
            $table->string('escopo_titulo')->nullable();
            $table->text('conteudo_planejado')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ausencia_aulas');
        Schema::dropIfExists('ausencias');
    }
};
