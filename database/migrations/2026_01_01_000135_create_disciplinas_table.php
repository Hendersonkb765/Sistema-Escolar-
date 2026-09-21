<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disciplinas', function (Blueprint $table) {
            $table->id();
            // A disciplina pertence a um curso: uma grade de Desenvolvimento
            // de Sistemas não pode puxar uma disciplina de Logística.
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->string('nome');
            $table->string('codigo', 30);
            // Período do curso em que a disciplina é cursada (1º ano, 2º…).
            $table->unsignedTinyInteger('periodo');
            $table->unsignedSmallInteger('carga_horaria')->default(80);
            $table->string('status', 20)->default('ativo')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['curso_id', 'codigo']);
            $table->index(['curso_id', 'periodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinas');
    }
};
