<?php

namespace App\Support;

use App\Enums\Bimestre;

/**
 * Onde uma data cai no calendário letivo — ou por que não cai em lugar
 * nenhum.
 *
 * Não achar é uma resposta, e vem com o motivo: a tela mostra "o
 * calendário de 2027 não foi cadastrado" em vez de deixar a aula do
 * escopo em branco sem dizer por quê.
 */
final readonly class SemanaLetiva
{
    private function __construct(
        public ?Bimestre $bimestre,
        public ?int $semana,
        public ?string $motivo,
    ) {}

    public static function em(Bimestre $bimestre, int $semana): self
    {
        return new self($bimestre, $semana, null);
    }

    public static function naoEncontrada(string $motivo): self
    {
        return new self(null, null, $motivo);
    }

    public function encontrada(): bool
    {
        return $this->bimestre !== null;
    }
}
