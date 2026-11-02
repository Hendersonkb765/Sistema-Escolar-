<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Os avisos de cada usuário — o sino no topo da tela. Um aviso é uma
 * linha por destinatário, com o próprio "lido": quando um PAEET abre o
 * aviso, o do colega continua lá.
 *
 * O model é App\Models\Aviso, que grava a hora com microssegundos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            // Microssegundos: avisos do mesmo segundo saem na ordem em que foram
            // criados. Sem isso, no MySQL a ordem dos empatados é a do UUID — sorteada.
            $table->timestamp('read_at', 6)->nullable();
            $table->timestamps(6);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
