<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questao_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questao_id')->constrained('questoes')->restrictOnDelete();
            $table->foreignId('analisado_por')->constrained('usuarios')->restrictOnDelete();
            $table->string('acao', 20)->index();
            $table->text('comentario')->nullable();
            $table->unsignedInteger('versao_questao');
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questao_feedbacks');
    }
};
