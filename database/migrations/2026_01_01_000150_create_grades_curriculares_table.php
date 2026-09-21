<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('grades_curriculares', function (Blueprint $table) {
            $table->id();
            $table->foreignId('curso_id')->constrained('cursos')->restrictOnDelete();
            $table->unsignedInteger('versao');
            $table->unsignedSmallInteger('ano_vigencia');
            $table->string('status', 20)->default('rascunho')->index();
            $table->text('observacoes')->nullable();
            $table->foreignId('criado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('origem_grade_id')->nullable()->constrained('grades_curriculares')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['curso_id', 'versao']);
            $table->index(['curso_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('grades_curriculares');
    }
};
