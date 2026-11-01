<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;
use Carbon\CarbonInterface;

/**
 * Os dias em que há aula. Domingo não entra: não há o que cadastrar
 * nele, e uma data de domingo simplesmente não tem aula.
 *
 * O valor é o `dayOfWeekIso` do Carbon (segunda = 1), para que a data
 * vire dia da semana sem tabela de conversão.
 */
enum DiaDaSemana: int implements Rotulavel
{
    use DescreveOpcoes;

    case Segunda = 1;
    case Terca = 2;
    case Quarta = 3;
    case Quinta = 4;
    case Sexta = 5;
    case Sabado = 6;

    public static function daData(CarbonInterface $data): ?self
    {
        return self::tryFrom($data->dayOfWeekIso);
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::Segunda => 'Segunda',
            self::Terca => 'Terça',
            self::Quarta => 'Quarta',
            self::Quinta => 'Quinta',
            self::Sexta => 'Sexta',
            self::Sabado => 'Sábado',
        };
    }

    public function cor(): string
    {
        return 'cinza';
    }
}
