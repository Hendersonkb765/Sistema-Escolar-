<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Duas logos no cabeçalho: uma em cada extremo. A coluna antiga passa a
 * dizer de qual lado ela é.
 *
 * Alteração em migration própria, e não na criação da tabela, para não
 * exigir `migrate:fresh` de quem já tem modelos cadastrados.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelos_prova', function (Blueprint $table) {
            $table->renameColumn('logo_path', 'logo_esquerda_path');
        });

        Schema::table('modelos_prova', function (Blueprint $table) {
            $table->string('logo_direita_path')->nullable()->after('logo_esquerda_path');
        });
    }

    public function down(): void
    {
        Schema::table('modelos_prova', function (Blueprint $table) {
            $table->dropColumn('logo_direita_path');
        });

        Schema::table('modelos_prova', function (Blueprint $table) {
            $table->renameColumn('logo_esquerda_path', 'logo_path');
        });
    }
};
