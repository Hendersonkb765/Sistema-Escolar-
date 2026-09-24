<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cada lado do cabeçalho passa a dizer de onde vem a sua logo.
 *
 * Só o caminho não bastava: `null` significava ao mesmo tempo "usa a
 * padrão do sistema" e "não quero logo deste lado". Os modelos que já
 * existem herdam a origem conforme tenham ou não arquivo enviado.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelos_prova', function (Blueprint $table) {
            $table->string('origem_logo_esquerda', 20)->default('padrao');
            $table->string('origem_logo_direita', 20)->default('padrao');
        });

        foreach (['esquerda', 'direita'] as $lado) {
            DB::table('modelos_prova')
                ->whereNotNull("logo_{$lado}_path")
                ->update(["origem_logo_{$lado}" => 'enviada']);
        }
    }

    public function down(): void
    {
        Schema::table('modelos_prova', function (Blueprint $table) {
            $table->dropColumn(['origem_logo_esquerda', 'origem_logo_direita']);
        });
    }
};
