<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;

/**
 * Um contador por usuário que muda quando chega aviso novo para ele.
 *
 * O sino pergunta por este número a cada 30 segundos num endereço que
 * não abre o banco — nem sessão, nem usuário, nem consulta: a URL
 * assinada diz de quem é o contador, e o contador mora num arquivo. Só
 * quando o número muda o sino vai ao banco buscar os avisos.
 *
 * Antes, cada pergunta era uma requisição Livewire: uma conexão com o
 * banco a cada 30 segundos, por PAEET — e a hospedagem limita as
 * conexões por hora.
 *
 * Quem muda o contador é o evento de notificação gravada no banco, para
 * todo caminho que cria aviso, e só depois do commit: mudar antes faria o
 * sino ir ao banco, não achar o aviso ainda, e ficar quieto até o próximo.
 */
final class SinalDeAvisos
{
    public static function versao(int $usuarioId): int
    {
        return (int) Cache::store('escola')->get(self::chave($usuarioId), 0);
    }

    /**
     * Muda o contador de cada um.
     *
     * Ler, somar e gravar sob trava: sem ela, dois avisos ao mesmo tempo
     * leem 3 e gravam 4, e o sino que já tinha visto o 4 perde o segundo.
     * A leitura do arquivo não precisa de trava própria: o cache em
     * arquivo escreve com LOCK_EX e lê com LOCK_SH.
     *
     * @param  iterable<int>  $usuarioIds
     */
    public static function avisar(iterable $usuarioIds): void
    {
        foreach ($usuarioIds as $id) {
            Cache::store('escola')->lock(self::chave($id).'-trava', 5)
                ->block(3, fn () => Cache::store('escola')->forever(self::chave($id), self::versao($id) + 1));
        }
    }

    /** O endereço que o sino consulta. Sem prazo: vale enquanto a página estiver aberta. */
    public static function url(User $usuario): string
    {
        return URL::signedRoute('avisos.sinal', ['usuario' => $usuario->getKey()]);
    }

    private static function chave(int $usuarioId): string
    {
        return "avisos-versao-{$usuarioId}";
    }
}
