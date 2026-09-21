<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alunos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turma_id')->constrained('turmas')->restrictOnDelete();
            $table->string('nome');
            $table->string('matricula', 40);
            $table->string('status', 20)->default('ativo')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['turma_id', 'matricula']);
            $table->index('matricula');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alunos');
    }
};
