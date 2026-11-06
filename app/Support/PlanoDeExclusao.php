<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * O que uma exclusão vai fazer, antes de fazer: é o que a confirmação
 * mostra, e o que a execução segue.
 */
final readonly class PlanoDeExclusao
{
    /**
     * @param  Collection<int, Model>  $cascata  saem por exclusão lógica, além do próprio registro
     * @param  Collection<int, Model>  $retirados  saem de fato, com cópia para a restauração
     * @param  array<string, int>  $contagens  "aluno(s)" => 35
     * @param  array<int, string>  $avisos
     */
    public function __construct(
        public string $rotulo,
        public Collection $cascata,
        public Collection $retirados,
        public array $contagens,
        public array $avisos,
        public int $confirmacoes,
        public ?string $impedimento = null,
    ) {}

    /** Algo além do próprio registro é atingido? */
    public function temImpacto(): bool
    {
        return $this->contagens !== [] || $this->avisos !== [];
    }
}
