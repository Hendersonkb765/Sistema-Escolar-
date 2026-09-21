<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusProva: string implements Rotulavel
{
    use DescreveOpcoes;

    case Rascunho = 'rascunho';
    case Gerada = 'gerada';
    case Aplicada = 'aplicada';
    case Encerrada = 'encerrada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Gerada => 'Gerada',
            self::Aplicada => 'Aplicada',
            self::Encerrada => 'Encerrada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Rascunho => 'cinza',
            self::Gerada => 'azul',
            self::Aplicada => 'amarelo',
            self::Encerrada => 'verde',
        };
    }

    /** Prova já gerada tem snapshot imutável e não aceita remontagem. */
    public function editavel(): bool
    {
        return $this === self::Rascunho;
    }

    public function aceitaImportacao(): bool
    {
        return in_array($this, [self::Gerada, self::Aplicada], true);
    }
}
