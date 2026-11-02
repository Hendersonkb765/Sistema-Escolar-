<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os eventos e feriados do calendário da escola, para que todos saibam.
 *
 * São da escola, não de um Eixo. Evento de um dia tem início e fim no
 * mesmo dia; recesso e semana de provas ocupam um período.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eventos_escolares', function (Blueprint $table) {
            $table->id();
            $table->string('titulo', 120);
            $table->string('tipo', 20);
            $table->date('inicio');
            $table->date('fim');
            $table->text('descricao')->nullable();
            $table->timestamps();

            $table->index(['inicio', 'fim']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eventos_escolares');
    }
};
