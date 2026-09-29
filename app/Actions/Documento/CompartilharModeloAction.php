<?php

namespace App\Actions\Documento;

use App\Enums\PerfilUsuario;
use App\Enums\StatusCompartilhamento;
use App\Exceptions\RegraDeNegocioException;
use App\Models\CompartilhamentoDeModelo;
use App\Models\ModeloDocumento;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Oferece um modelo de documento a outro PAEET.
 *
 * O que se manda é uma oferta, não o modelo: nada aparece na lista de
 * quem recebeu antes de ele aceitar. É o {@see ResponderCompartilhamentoAction}
 * que decide o destino.
 */
class CompartilharModeloAction
{
    public function executar(
        ModeloDocumento $modelo,
        User $remetente,
        User $destinatario,
        ?string $mensagem = null,
    ): CompartilhamentoDeModelo {
        Gate::forUser($remetente)->authorize('compartilhar', $modelo);

        $this->conferir($modelo, $remetente, $destinatario);

        return DB::transaction(fn () => CompartilhamentoDeModelo::query()->create([
            'modelo_documento_id' => $modelo->getKey(),
            'remetente_id' => $remetente->getKey(),
            'destinatario_id' => $destinatario->getKey(),
            'status' => StatusCompartilhamento::Pendente,
            'mensagem' => $mensagem,
        ]));
    }

    /**
     * Para quem este usuário pode mandar.
     *
     * Só gestão, só conta ativa, e nunca para si mesmo. Não se filtra por
     * Eixo de propósito: compartilhar com quem já enxerga o modelo não
     * teria efeito nenhum — o sentido do recurso é atravessar o Eixo.
     *
     * @return Collection<int, User>
     */
    public function destinatariosPossiveis(User $remetente): Collection
    {
        return User::query()
            ->where('ativo', true)
            ->whereKeyNot($remetente->getKey())
            ->whereIn('perfil', [PerfilUsuario::Paeet, PerfilUsuario::PaeetAdmin])
            ->orderBy('nome')
            ->get(['id', 'nome', 'email', 'perfil']);
    }

    protected function conferir(ModeloDocumento $modelo, User $remetente, User $destinatario): void
    {
        if ($destinatario->getKey() === $remetente->getKey()) {
            throw RegraDeNegocioException::porque(
                'Você já tem este modelo — compartilhe com outro PAEET.'
            );
        }

        if (! $destinatario->ativo) {
            throw RegraDeNegocioException::porque(
                "A conta de {$destinatario->nome} está inativa e não receberia o modelo."
            );
        }

        if (! $destinatario->ehGestao()) {
            throw RegraDeNegocioException::porque(
                'Modelos de documento só podem ser compartilhados com a coordenação PAEET.'
            );
        }

        /*
         * Uma oferta pendente por vez para o mesmo par. Reenviar enquanto
         * a primeira espera resposta encheria a caixa de quem recebeu com
         * a mesma coisa, e a resposta de uma não diria nada sobre a outra.
         */
        $pendente = CompartilhamentoDeModelo::query()
            ->where('modelo_documento_id', $modelo->getKey())
            ->where('destinatario_id', $destinatario->getKey())
            ->pendentes()
            ->exists();

        if ($pendente) {
            throw RegraDeNegocioException::porque(
                "{$destinatario->nome} já tem este modelo aguardando resposta."
            );
        }

        /*
         * Se a pessoa já aceitou antes, ela tem a cópia. Mandar de novo
         * criaria uma segunda cópia igual na lista dela, sem que ninguém
         * pedisse.
         */
        $jaTem = ModeloDocumento::query()
            ->where('copia_de', $modelo->getKey())
            ->whereHas('compartilhamentos', fn (Builder $q) => $q
                ->where('destinatario_id', $destinatario->getKey())
                ->where('status', StatusCompartilhamento::Aceito))
            ->exists();

        $jaAceitou = CompartilhamentoDeModelo::query()
            ->where('modelo_documento_id', $modelo->getKey())
            ->where('destinatario_id', $destinatario->getKey())
            ->where('status', StatusCompartilhamento::Aceito)
            ->exists();

        if ($jaTem || $jaAceitou) {
            throw RegraDeNegocioException::porque(
                "{$destinatario->nome} já aceitou este modelo e tem uma cópia dele."
            );
        }
    }
}
