<?php

namespace App\Actions\Documento;

use App\Enums\StatusCompartilhamento;
use App\Exceptions\RegraDeNegocioException;
use App\Models\CompartilhamentoDeModelo;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Aceitar ou recusar um modelo que alguém ofereceu.
 *
 * Aceitar **copia**: o destinatário vira dono de um modelo no Eixo dele,
 * que pode editar e apagar sem afetar o original. Recusar não cria nada.
 * Nos dois casos a linha do compartilhamento fica, com quem ofereceu,
 * para quem, e no que deu.
 */
class ResponderCompartilhamentoAction
{
    public function aceitar(CompartilhamentoDeModelo $compartilhamento, User $usuario): ModeloDocumento
    {
        $this->conferir($compartilhamento, $usuario);

        $eixoId = $this->eixoDeDestino($usuario);

        return DB::transaction(function () use ($compartilhamento, $usuario, $eixoId) {
            $original = $compartilhamento->loadMissing('modelo')->modelo;

            $copia = ModeloDocumento::query()->create([
                'eixo_id' => $eixoId,
                'nome' => $this->nomeLivre($original->nome, $eixoId),
                'descricao' => $original->descricao,
                'tipo' => $original->tipo,
                'corpo' => $original->corpo,
                'por_pagina' => $original->por_pagina,
                'ativo' => true,
                'criado_por' => $usuario->getKey(),
                'copia_de' => $original->getKey(),
            ]);

            $compartilhamento->update([
                'status' => StatusCompartilhamento::Aceito,
                'copia_id' => $copia->getKey(),
                'respondido_em' => now(),
            ]);

            return $copia;
        });
    }

    public function recusar(CompartilhamentoDeModelo $compartilhamento, User $usuario): CompartilhamentoDeModelo
    {
        $this->conferir($compartilhamento, $usuario);

        $compartilhamento->update([
            'status' => StatusCompartilhamento::Recusado,
            'respondido_em' => now(),
        ]);

        return $compartilhamento;
    }

    protected function conferir(CompartilhamentoDeModelo $compartilhamento, User $usuario): void
    {
        Gate::forUser($usuario)->authorize('responder', $compartilhamento);

        if (! $compartilhamento->pendente()) {
            throw RegraDeNegocioException::porque(
                'Este compartilhamento já foi respondido em '
                .$compartilhamento->respondido_em?->format('d/m/Y \à\s H:i')
                .' ('.$compartilhamento->status->rotulo().').'
            );
        }
    }

    /**
     * Em qual Eixo a cópia nasce.
     *
     * Quem tem mais de um Eixo recebe no primeiro — e pode mover depois,
     * editando o modelo. Quem não tem nenhum não tem onde guardar, e é
     * melhor dizer isso do que criar um registro órfão.
     */
    protected function eixoDeDestino(User $usuario): int
    {
        $eixos = $usuario->eixoIds();

        if ($eixos === []) {
            throw RegraDeNegocioException::porque(
                'Sua conta não está vinculada a nenhum Eixo, e o modelo precisa de um '
                .'para ser guardado. Peça à coordenação para vincular o seu Eixo.'
            );
        }

        return $eixos[0];
    }

    /**
     * Dois modelos com o mesmo nome na mesma lista são indistinguíveis na
     * hora de gerar. Quando o nome já existe no Eixo, a cópia ganha um
     * sufixo em vez de entrar duplicada.
     */
    protected function nomeLivre(string $nome, int $eixoId): string
    {
        $existe = fn (string $candidato) => ModeloDocumento::query()
            ->where('eixo_id', $eixoId)
            ->where('nome', $candidato)
            ->exists();

        if (! $existe($nome)) {
            return $nome;
        }

        $sufixo = 2;

        while ($existe($tentativa = "{$nome} ({$sufixo})")) {
            $sufixo++;
        }

        return $tentativa;
    }
}
