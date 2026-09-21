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
            $table->foreignId('eixo_id')->constrained('eixos')->restrictOnDelete();
            $table->string('nome');
            $table->string('codigo', 30);
            $table->string('status', 20)->default('ativo')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['eixo_id', 'codigo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disciplinas');
    }
};
