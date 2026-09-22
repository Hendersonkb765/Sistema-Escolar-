<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum TipoBlocoQuestao: string implements Rotulavel
{
    use DescreveOpcoes;

    case Texto = 'texto';
    case Codigo = 'codigo';
    case Imagem = 'imagem';

    public function rotulo(): string
    {
        return match ($this) {
            self::Texto => 'Texto',
            self::Codigo => 'Código',
            self::Imagem => 'Imagem',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Texto => 'cinza',
            self::Codigo => 'roxo',
            self::Imagem => 'azul',
        };
    }
}
