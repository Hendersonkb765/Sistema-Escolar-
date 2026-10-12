<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

enum StatusAluno: string implements Rotulavel
{
    use DescreveOpcoes;

    case Ativo = 'ativo';
    case Transferido = 'transferido';
    case Concluinte = 'concluinte';
    case Inativo = 'inativo';

    public function rotulo(): string
    {
        return match ($this) {
            self::Ativo => 'Ativo',
            self::Transferido => 'Transferido',
            self::Concluinte => 'Concluinte',
            self::Inativo => 'Inativo',
        };
    }

    /**
     * Para onde um desligamento pode levar.
     *
     * Concluinte fica de fora de propósito: ele não é uma saída, é o fim
     * do percurso, e quem o atribui é a conclusão da turma. Ativo
     * tampouco — desligar é sair, não permanecer.
     *
     * @return array<int, self>
     */
    public static function desligamentos(): array
    {
        return [self::Transferido, self::Inativo];
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Ativo => 'Frequentando a turma.',
            self::Transferido => 'Saiu para outra escola ou outra unidade.',
            self::Concluinte => 'Chegou ao fim do curso com a turma.',
            self::Inativo => 'Deixou de frequentar: evasão, desistência ou cancelamento.',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Ativo => 'verde',
            self::Transferido => 'azul',
            self::Concluinte => 'azul',
            self::Inativo => 'cinza',
        };
    }
}
