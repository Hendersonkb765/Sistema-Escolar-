<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusSolicitacao: string implements Rotulavel
{
    use DescreveOpcoes;

    case Aberta = 'aberta';
    case Enviada = 'enviada';
    case EmAnalise = 'em_analise';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Enviada => 'Enviada',
            self::EmAnalise => 'Em análise',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Aberta => 'amarelo',
            self::Enviada => 'azul',
            self::EmAnalise => 'azul',
            self::Concluida => 'verde',
            self::Cancelada => 'cinza',
        };
    }

    /**
     * Prazo vencido NÃO bloqueia: só o encerramento ou o cancelamento
     * manual pelo PAEET fecham a solicitação para envio.
     */
    public function aceitaEnvio(): bool
    {
        return in_array($this, [self::Aberta, self::Enviada, self::EmAnalise], true);
    }
}
