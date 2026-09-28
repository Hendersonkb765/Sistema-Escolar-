<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quem mandou o modelo para quem, e no que deu.
 *
 * A linha não some quando o destinatário recusa: saber que houve uma
 * oferta e que ela foi recusada é parte do que se quer registrar, e sem
 * isso o remetente ficaria reenviando sem entender.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compartilhamentos_de_modelo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modelo_documento_id')->constrained('modelos_documento')->cascadeOnDelete();
            $table->foreignId('remetente_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('destinatario_id')->constrained('usuarios')->restrictOnDelete();

            $table->string('status', 20)->default('pendente')->index();
            $table->string('mensagem')->nullable();

            // A cópia que o "aceitar" criou. Nula enquanto pendente ou
            // recusado.
            $table->foreignId('copia_id')->nullable()
                ->constrained('modelos_documento')->nullOnDelete();

            $table->timestamp('respondido_em')->nullable();
            $table->timestamps();

            $table->index(['destinatario_id', 'status']);
            $table->index(['modelo_documento_id', 'destinatario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('compartilhamentos_de_modelo');
    }
};
