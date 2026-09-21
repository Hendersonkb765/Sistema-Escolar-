<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusAluno: string implements Rotulavel
{
    use DescreveOpcoes;

    case Ativo = 'ativo';
    case Transferido = 'transferido';
    case Concluinte = 'concluinte';
    case Inativo = 'inativo';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Transferido => 'Transferido',
            self::Concluinte => 'Concluinte',
            self::Inativo => 'Inativo',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Ativo => 'verde',
            self::Transferido => 'azul',
            self::Concluinte => 'azul',
            self::Inativo => 'cinza',
        };
    }
}
