<?php

namespace App\Enums\Concerns;

use App\Enums\Contracts\Rotulavel;

/**
 * Helpers de apresentação compartilhados pelos enums rotuláveis.
 *
 * @mixin Rotulavel
 */
trait DescreveOpcoes
{
    /** @return array<string, string> valor => rótulo */
    public static function opcoes(): array
    {
        $opcoes = [];

        foreach (self::cases() as $caso) {
            $opcoes[$caso->value] = $caso->rotulo();
        }

        return $opcoes;
    }

    /** @return array<int, string> */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }
}
