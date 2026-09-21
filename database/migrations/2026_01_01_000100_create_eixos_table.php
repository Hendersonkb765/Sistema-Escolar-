<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eixos', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('codigo', 30)->unique();
            $table->string('status', 20)->default('ativo')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('eixos');
    }
};
