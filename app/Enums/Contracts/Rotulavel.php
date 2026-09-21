<?php

namespace App\Enums\Contracts;

interface Rotulavel
{
    /** Texto exibido na interface. */
    public function rotulo(): string;

    /** Cor semântica do badge: verde, amarelo, vermelho, azul, roxo, cinza. */
    public function cor(): string;
}
