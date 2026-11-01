<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/**
 * O PAEET só toma ciência da ausência: não aprova nem recusa. Quem
 * organiza a substituição faz isso fora do sistema.
 */
enum StatusAusencia: string implements Rotulavel
{
    use DescreveOpcoes;

    case Pendente = 'pendente';
    case Ciente = 'ciente';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Aguardando ciência',
            self::Ciente => 'Coordenação ciente',
            self::Cancelada => 'Cancelada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Pendente => 'amarelo',
            self::Ciente => 'verde',
            self::Cancelada => 'cinza',
        };
    }
}
