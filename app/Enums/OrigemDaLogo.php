<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/** De onde sai cada uma das duas logos do cabeçalho da prova. */
enum OrigemDaLogo: string implements Rotulavel
{
    use DescreveOpcoes;

    /** A que acompanha o sistema — não exige envio nenhum. */
    case Padrao = 'padrao';

    case Enviada = 'enviada';

    case Nenhuma = 'nenhuma';

    public function rotulo(): string
    {
        return match ($this) {
            self::Padrao => 'Usar a logo padrão',
            self::Enviada => 'Enviar uma imagem',
            self::Nenhuma => 'Sem logo deste lado',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Padrao => 'azul',
            self::Enviada => 'verde',
            self::Nenhuma => 'cinza',
        };
    }

    public function exigeArquivo(): bool
    {
        return $this === self::Enviada;
    }
}
