<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Quantas vias cabem numa página deixa de ser um número escolhido à mão.
 *
 * O modelo dizia 1, 2 ou 3, e 3 era o teto. Num documento curto isso
 * desperdiça meia folha: cabiam seis. Agora `0` significa "quantas
 * couberem", e é o padrão; `1` continua existindo para o documento que
 * precisa sair sozinho na folha.
 *
 * Quem tinha 2 ou 3 passa a automático: era uma tentativa de aproveitar a
 * folha, e o automático faz isso melhor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('modelos_documento', function (Blueprint $table) {
            $table->unsignedTinyInteger('por_pagina')->default(0)->change();
        });

        DB::table('modelos_documento')->whereIn('por_pagina', [2, 3])->update(['por_pagina' => 0]);
    }

    public function down(): void
    {
        DB::table('modelos_documento')->where('por_pagina', 0)->update(['por_pagina' => 3]);

        Schema::table('modelos_documento', function (Blueprint $table) {
            $table->unsignedTinyInteger('por_pagina')->default(1)->change();
        });
    }
};
