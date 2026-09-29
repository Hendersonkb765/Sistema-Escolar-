<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusCompartilhamento: string implements Rotulavel
{
    use DescreveOpcoes;

    case Pendente = 'pendente';
    case Aceito = 'aceito';
    case Recusado = 'recusado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Pendente => 'Aguardando resposta',
            self::Aceito => 'Aceito',
            self::Recusado => 'Recusado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Pendente => 'amarelo',
            self::Aceito => 'verde',
            self::Recusado => 'cinza',
        };
    }

    public function respondido(): bool
    {
        return $this !== self::Pendente;
    }
}
