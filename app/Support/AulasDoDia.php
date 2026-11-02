<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * As aulas de um professor numa data — ou por que não há nenhuma.
 */
final readonly class AulasDoDia
{
    /**
     * @param  Collection<int, AulaPrevista>  $aulas
     */
    public function __construct(
        public Collection $aulas,
        public ?string $motivo = null,
    ) {}

    public function vazio(): bool
    {
        return $this->aulas->isEmpty();
    }

    public function daAula(int $quadroHorarioId): ?AulaPrevista
    {
        return $this->aulas->first(fn (AulaPrevista $a) => $a->quadro->id === $quadroHorarioId);
    }
}
