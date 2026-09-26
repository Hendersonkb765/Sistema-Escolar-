<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/** O ano letivo tem quatro bimestres, e nenhum a mais. */
enum Bimestre: int implements Rotulavel
{
    use DescreveOpcoes;

    case Primeiro = 1;
    case Segundo = 2;
    case Terceiro = 3;
    case Quarto = 4;

    public function rotulo(): string
    {
        return "{$this->value}º bimestre";
    }

    public function cor(): string
    {
        return match ($this) {
            self::Primeiro => 'azul',
            self::Segundo => 'verde',
            self::Terceiro => 'amarelo',
            self::Quarto => 'roxo',
        };
    }

    /** Rótulo curto, para caber numa coluna de tabela. */
    public function sigla(): string
    {
        return "{$this->value}º bim.";
    }
}
