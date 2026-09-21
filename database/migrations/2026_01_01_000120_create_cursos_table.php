<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eixo_id')->constrained('eixos')->restrictOnDelete();
            $table->string('nome');
            $table->string('codigo', 30);
            $table->unsignedTinyInteger('duracao_anos');
            $table->string('status', 20)->default('ativo')->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['eixo_id', 'codigo']);
        });

        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE cursos ADD CONSTRAINT chk_cursos_duracao CHECK (duracao_anos IN (2, 3))');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cursos');
    }
};
