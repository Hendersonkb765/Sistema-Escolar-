<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum AcaoFeedback: string implements Rotulavel
{
    use DescreveOpcoes;

    case Aprovada = 'aprovada';
    case Rejeitada = 'rejeitada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aprovada => 'Aprovada',
            self::Rejeitada => 'Rejeitada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Aprovada => 'verde',
            self::Rejeitada => 'vermelho',
        };
    }
}
