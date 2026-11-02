<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O calendário próprio de uma turma, exceção ao da escola.
 *
 * O calendário letivo é da escola e vale para todas as turmas sem
 * cadastro nenhum. A turma que funciona de outro jeito ganha aqui as
 * próprias datas, que substituem as da escola inteiras para ela. Sem
 * linha aqui, vale o da escola.
 *
 * Os intervalos não têm tabela por turma: cada intervalo da Configuração
 * da escola diz a quais turmas se aplica.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turma_bimestres', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->cascadeOnDelete();
            $table->unsignedTinyInteger('bimestre');
            $table->date('inicio');
            $table->date('fim');
            $table->timestamps();

            $table->unique(['turma_id', 'bimestre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_bimestres');
    }
};
