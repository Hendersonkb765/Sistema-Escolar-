<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turma_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->string('evento', 30)->index();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('grade_curricular_id')->constrained('grades_curriculares')->restrictOnDelete();
            $table->unsignedTinyInteger('ano_curso');
            $table->string('identificacao', 30);
            $table->string('periodo_letivo', 20);
            $table->string('status', 20);
            $table->json('metadados')->nullable();
            $table->text('observacoes')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turma_historicos');
    }
};
