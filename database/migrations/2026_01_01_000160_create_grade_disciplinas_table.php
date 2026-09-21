<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grade_disciplinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_curricular_id')->constrained('grades_curriculares')->cascadeOnDelete();
            $table->foreignId('disciplina_id')->constrained('disciplinas')->restrictOnDelete();
            $table->unsignedTinyInteger('ano_curso');
            $table->unsignedSmallInteger('carga_horaria')->default(0);
            $table->timestamps();

            $table->unique(['grade_curricular_id', 'disciplina_id', 'ano_curso'], 'grade_disc_ano_unique');
            $table->index(['grade_curricular_id', 'ano_curso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grade_disciplinas');
    }
};
