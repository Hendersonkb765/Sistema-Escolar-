<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O quadro de horários: quem dá qual aula, em que turma, dia e hora.
 *
 * Uma linha é uma aula da semana. A turma é de um ano letivo, então o
 * quadro também é — o do ano seguinte nasce com a turma seguinte.
 *
 * As ausências copiam turma, disciplina e horário da aula no momento do
 * pedido: mudar o quadro no meio do ano não reescreve o que já foi
 * pedido.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quadro_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->unsignedTinyInteger('dia_semana');
            // A 1ª, a 2ª… aula do dia, como a escola numera.
            $table->unsignedTinyInteger('aula_numero');
            $table->time('hora_inicio');
            $table->time('hora_fim');
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->foreignId('professor_id')->constrained('usuarios')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['turma_id', 'dia_semana', 'aula_numero'], 'quadro_turma_aula_unique');
            $table->index(['professor_id', 'dia_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quadro_horarios');
    }
};
