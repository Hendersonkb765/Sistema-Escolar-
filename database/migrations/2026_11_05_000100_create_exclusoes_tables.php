<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Lixeira.
 *
 * Excluir um registro tira de circulação ele e o que depende dele, numa
 * vez só: uma linha em `exclusoes` (o lote) e uma em `exclusao_itens` por
 * registro que saiu. Restaurar devolve exatamente esses — e não o que já
 * estava na Lixeira por outra exclusão.
 *
 * A maioria sai por exclusão lógica (`deleted_at`) e volta com restore().
 * O que não pode ficar ocupando lugar — a aula do quadro de horários, que
 * tem chave única por horário — é retirado de fato, e o item guarda em
 * `dados` uma cópia para recriá-lo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exclusoes', function (Blueprint $table) {
            $table->id();
            $table->string('registro_type');
            $table->unsignedBigInteger('registro_id');
            $table->string('rotulo');
            // Gravado na hora: depois de excluído, o caminho até o Eixo pode estar na Lixeira também.
            $table->unsignedBigInteger('eixo_id')->nullable()->index();
            $table->json('resumo');
            $table->foreignId('excluido_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('restaurado_em')->nullable();
            $table->foreignId('restaurado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['registro_type', 'registro_id']);
        });

        Schema::create('exclusao_itens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exclusao_id')->constrained('exclusoes')->cascadeOnDelete();
            $table->string('registro_type');
            $table->unsignedBigInteger('registro_id');
            // Cópia do registro retirado de fato; nula quando a exclusão foi lógica.
            $table->json('dados')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exclusao_itens');
        Schema::dropIfExists('exclusoes');
    }
};
