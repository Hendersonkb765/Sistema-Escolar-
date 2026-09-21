<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusTurma: string implements Rotulavel
{
    use DescreveOpcoes;

    case Ativa = 'ativa';
    case Concluida = 'concluida';
    case Arquivada = 'arquivada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativa => 'Ativa',
            self::Concluida => 'Concluída',
            self::Arquivada => 'Arquivada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Ativa => 'verde',
            self::Concluida => 'azul',
            self::Arquivada => 'cinza',
        };
    }
}
