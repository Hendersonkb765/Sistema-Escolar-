<?php

namespace App\Support;

/**
 * Um trecho de código quebrado em linhas, pronto para a folha.
 *
 * `white-space: pre-wrap` resolve no navegador, mas o mPDF o ignora: o
 * código sairia numa linha só, com a indentação comida. Por isso as
 * linhas viram elementos separados e os espaços da esquerda viram
 * espaços rígidos — o que o HTML normal colapsaria.
 */
final class TrechoDeCodigo
{
    /** @return array<int, string> HTML de cada linha, já escapado. */
    public static function linhas(string $conteudo): array
    {
        $linhas = preg_split('/\R/', rtrim($conteudo)) ?: [];

        return array_map(self::linha(...), $linhas);
    }

    protected static function linha(string $linha): string
    {
        $recuo = strlen($linha) - strlen(ltrim($linha, ' '));

        return str_repeat('&nbsp;', $recuo)
            .e(substr($linha, $recuo))
            // Linha vazia sem conteúdo nenhum não ocupa altura.
            ?: '&nbsp;';
    }
}
