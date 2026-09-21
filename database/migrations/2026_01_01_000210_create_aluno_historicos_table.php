<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aluno_historicos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aluno_id')->constrained('alunos')->restrictOnDelete();
            $table->string('evento', 30)->index();
            $table->foreignId('turma_anterior_id')->nullable()->constrained('turmas')->restrictOnDelete();
            $table->foreignId('turma_nova_id')->nullable()->constrained('turmas')->restrictOnDelete();
            $table->string('status_anterior', 20)->nullable();
            $table->string('status_novo', 20)->nullable();
            $table->text('motivo')->nullable();
            $table->json('metadados')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aluno_historicos');
    }
};
