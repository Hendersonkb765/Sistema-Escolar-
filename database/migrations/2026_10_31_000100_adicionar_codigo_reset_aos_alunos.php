<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * O código com que a SED identifica o aluno na página de redefinição de
 * senha. Não é o RA: a SED usa um número próprio, e só ela o fornece.
 *
 * Nulo enquanto ninguém o cadastrou — e então a tela diz que falta, em
 * vez de abrir a SED sem aluno.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->string('codigo_reset', 40)->nullable()->after('ra');
        });
    }

    public function down(): void
    {
        Schema::table('alunos', function (Blueprint $table) {
            $table->dropColumn('codigo_reset');
        });
    }
};
