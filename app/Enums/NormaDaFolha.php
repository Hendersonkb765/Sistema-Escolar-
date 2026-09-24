<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/**
 * Como a folha de prova é formatada.
 *
 * A ABNT NBR 14724 trata de trabalhos acadêmicos, não de provas — o que
 * se aproveita dela é a parte tipográfica, que é justamente o que a
 * escola costuma exigir: A4, margens 3/2/2/3 cm, Arial ou Times New
 * Roman 12, espaçamento 1,5 e texto justificado.
 */
enum NormaDaFolha: string implements Rotulavel
{
    use DescreveOpcoes;

    case Abnt = 'abnt';
    case Livre = 'livre';

    public function rotulo(): string
    {
        return match ($this) {
            self::Abnt => 'ABNT (NBR 14724)',
            self::Livre => 'Livre',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Abnt => 'azul',
            self::Livre => 'cinza',
        };
    }

    /** O que a norma fixa — a tela mostra este texto ao lado dos campos travados. */
    public function descricao(): string
    {
        return match ($this) {
            self::Abnt => 'Papel A4, margens de 3 cm (superior e esquerda) e 2 cm '
                .'(inferior e direita), corpo em 12 pt, espaçamento 1,5 entre linhas, '
                .'texto justificado e trechos de código em 10 pt com espaçamento simples.',
            self::Livre => 'Tamanho, espaçamento e margens ficam por sua conta.',
        };
    }

    public function fixaAFormatacao(): bool
    {
        return $this === self::Abnt;
    }
}
