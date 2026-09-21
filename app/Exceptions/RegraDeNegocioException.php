<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Violação de uma regra do domínio acadêmico — distinta de erro de
 * validação de formulário e de falta de permissão.
 */
class RegraDeNegocioException extends RuntimeException
{
    public static function porque(string $mensagem): self
    {
        return new self($mensagem);
    }
}
