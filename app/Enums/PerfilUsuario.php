<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum PerfilUsuario: string implements Rotulavel
{
    use DescreveOpcoes;

    case PaeetAdmin = 'paeet_admin';
    case Paeet = 'paeet';
    case Professor = 'professor';

    public function rotulo(): string
    {
        return match ($this) {
            self::PaeetAdmin => 'PAEET Admin',
            self::Paeet => 'PAEET',
            self::Professor => 'Professor',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::PaeetAdmin => 'roxo',
            self::Paeet => 'azul',
            self::Professor => 'cinza',
        };
    }

    /**
     * PAEET Admin e PAEET formam a equipe de gestão: criam estrutura
     * acadêmica, solicitam questões, analisam, montam provas e importam
     * resultados — sempre dentro dos Eixos aos quais estão vinculados.
     */
    public function ehGestao(): bool
    {
        return in_array($this, [self::PaeetAdmin, self::Paeet], true);
    }

    /** Somente o PAEET Admin cria outros PAEETs. */
    public function podeCriarPaeet(): bool
    {
        return $this === self::PaeetAdmin;
    }
}
