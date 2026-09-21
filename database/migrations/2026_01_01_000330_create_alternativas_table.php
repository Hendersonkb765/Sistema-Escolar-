<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alternativas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questao_id')->constrained('questoes')->cascadeOnDelete();
            $table->char('letra', 1);
            $table->text('texto')->nullable();
            $table->boolean('correta')->default(false);
            $table->timestamps();

            $table->unique(['questao_id', 'letra']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alternativas');
    }
};
