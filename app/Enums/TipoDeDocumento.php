<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/**
 * O tipo decide o que o corpo do modelo pode dizer.
 *
 * Num documento individual existe "o aluno", e `{{ aluno.nome }}` faz
 * sentido. Num coletivo existe a turma inteira, e o que faz sentido é
 * `{{ lista_de_alunos }}`. Trocar os dois não dá erro de sintaxe — dá um
 * documento com um campo vazio no meio, descoberto na hora de entregar.
 */
enum TipoDeDocumento: string implements Rotulavel
{
    use DescreveOpcoes;

    case Individual = 'individual';
    case Coletivo = 'coletivo';

    public function rotulo(): string
    {
        return match ($this) {
            self::Individual => 'Uma via por aluno',
            self::Coletivo => 'Uma via com a lista de alunos',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Individual => 'O documento é repetido para cada aluno selecionado, '
                .'com os dados dele. É o caso da autorização que cada família assina.',
            self::Coletivo => 'Sai uma via só, com a relação dos alunos selecionados '
                .'numa tabela. É o caso da lista que circula para assinatura.',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Individual => 'azul',
            self::Coletivo => 'roxo',
        };
    }
}
