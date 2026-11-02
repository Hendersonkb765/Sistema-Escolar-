<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/**
 * O que é um dia marcado no calendário da escola. Feriado e recesso são
 * dias sem aula; evento e reunião acontecem com a escola funcionando.
 */
enum TipoDeEvento: string implements Rotulavel
{
    use DescreveOpcoes;

    case Feriado = 'feriado';
    case Recesso = 'recesso';
    case Evento = 'evento';
    case Reuniao = 'reuniao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Feriado => 'Feriado',
            self::Recesso => 'Recesso',
            self::Evento => 'Evento',
            self::Reuniao => 'Reunião',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Feriado => 'vermelho',
            self::Recesso => 'amarelo',
            self::Evento => 'azul',
            self::Reuniao => 'roxo',
        };
    }

    public function semAula(): bool
    {
        return in_array($this, [self::Feriado, self::Recesso], true);
    }
}
