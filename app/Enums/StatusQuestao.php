<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusQuestao: string implements Rotulavel
{
    use DescreveOpcoes;

    case Rascunho = 'rascunho';
    case Enviada = 'enviada';
    case EmAnalise = 'em_analise';
    case Aprovada = 'aprovada';
    case Rejeitada = 'rejeitada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Rascunho => 'Rascunho',
            self::Enviada => 'Enviada',
            self::EmAnalise => 'Em análise',
            self::Aprovada => 'Aprovada',
            self::Rejeitada => 'Rejeitada',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Rascunho => 'cinza',
            self::Enviada => 'azul',
            self::EmAnalise => 'azul',
            self::Aprovada => 'verde',
            self::Rejeitada => 'vermelho',
        };
    }

    /** Só questão aprovada entra na montagem da prova. */
    public function elegivelParaProva(): bool
    {
        return $this === self::Aprovada;
    }

    /** O professor edita enquanto a questão não está sob análise nem aprovada. */
    public function editavelPeloProfessor(): bool
    {
        return in_array($this, [self::Rascunho, self::Rejeitada], true);
    }
}
