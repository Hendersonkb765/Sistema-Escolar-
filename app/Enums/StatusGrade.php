<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusGrade: string implements Rotulavel
{
    use DescreveOpcoes;

    case Rascunho = 'rascunho';
    case Vigente = 'vigente';
    case Arquivada = 'arquivada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Vigente => 'Vigente',
            self::Arquivada => 'Arquivada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Rascunho => 'amarelo',
            self::Vigente => 'verde',
            self::Arquivada => 'cinza',
        };
    }

    /** Uma grade já usada por turmas nunca é editada: gera-se nova versão. */
    public function editavel(): bool
    {
        return $this === self::Rascunho;
    }
}
