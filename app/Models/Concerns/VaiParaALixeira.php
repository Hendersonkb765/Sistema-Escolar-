<?php

namespace App\Models\Concerns;

use App\Models\Contracts\Excluivel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * O padrão de quem vai para a Lixeira: sai sozinho, com uma confirmação,
 * sem aviso. O model sobrescreve só o que for diferente.
 *
 * @see Excluivel
 */
trait VaiParaALixeira
{
    public function dependentesNaExclusao(): Collection
    {
        return collect();
    }

    public function retiradosNaExclusao(): Collection
    {
        return collect();
    }

    public function avisosDaExclusao(): array
    {
        return [];
    }

    public function confirmacoesParaExcluir(): int
    {
        return 1;
    }

    public function paiNaRestauracao(): ?Model
    {
        return null;
    }
}
