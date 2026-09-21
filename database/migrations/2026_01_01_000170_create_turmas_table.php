<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->foreignId('grade_curricular_id')->constrained('grades_curriculares')->restrictOnDelete();
            $table->unsignedTinyInteger('ano_curso');
            $table->string('identificacao', 30);
            $table->string('periodo_letivo', 20);
            $table->string('status', 20)->default('ativa')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['curso_id', 'identificacao', 'periodo_letivo'], 'turmas_curso_ident_periodo_unique');
            $table->index(['curso_id', 'ano_curso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turmas');
    }
};
