<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * O emblema quadrado que aparece ao lado do nome do sistema.
 *
 * Cortar os dois primeiros caracteres do nome funciona enquanto ele
 * começa por letra — "PAEET" virava "PA". Num nome como
 * "E.E Francisco Pereira" o corte devolve "E.", com um ponto solto
 * dentro do quadrado. Por isso a sigla vem das iniciais das palavras.
 */
final class Marca
{
    public static function sigla(?string $nome = null): string
    {
        $nome ??= (string) config('app.name');

        $palavras = preg_split('/[^\p{L}\p{N}]+/u', $nome, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($palavras === []) {
            return '';
        }

        // Um nome de uma palavra só não tem iniciais para juntar.
        if (count($palavras) === 1) {
            return Str::upper(Str::substr($palavras[0], 0, 2));
        }

        return Str::upper(Str::substr($palavras[0], 0, 1).Str::substr($palavras[1], 0, 1));
    }
}
