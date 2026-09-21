<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusRegistro: string implements Rotulavel
{
    use DescreveOpcoes;

    case Ativo = 'ativo';
    case Inativo = 'inativo';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Inativo => 'Inativo',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Ativo => 'verde',
            self::Inativo => 'cinza',
        };
    }
}
