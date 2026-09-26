<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quando o próprio usuário escolheu a sua senha.
 *
 * Nulo significa que a senha em uso foi definida por outra pessoa — quem
 * criou a conta — e que o dono ainda não escolheu a dele. Enquanto
 * estiver nulo, o primeiro acesso pede a troca antes de qualquer outra
 * tela.
 *
 * As contas que já existem são marcadas como definidas: elas estão em
 * uso, e forçar a troca de todo mundo numa atualização não é o que se
 * pede aqui.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->timestamp('senha_definida_em')->nullable()->after('password');
        });

        DB::table('usuarios')->update(['senha_definida_em' => now()]);
    }

    public function down(): void
    {
        Schema::table('usuarios', fn (Blueprint $table) => $table->dropColumn('senha_definida_em'));
    }
};
