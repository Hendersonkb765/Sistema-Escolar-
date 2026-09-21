<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusImportacao: string implements Rotulavel
{
    use DescreveOpcoes;

    case Pendente = 'pendente';
    case Validando = 'validando';
    case Validada = 'validada';
    case Confirmada = 'confirmada';
    case Falhou = 'falhou';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Pendente',
            self::Validando => 'Validando',
            self::Validada => 'Validada',
            self::Confirmada => 'Confirmada',
            self::Falhou => 'Falhou',
            self::Cancelada => 'Cancelada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Pendente => 'cinza',
            self::Validando => 'azul',
            self::Validada => 'amarelo',
            self::Confirmada => 'verde',
            self::Falhou => 'vermelho',
            self::Cancelada => 'cinza',
        };
    }
}
