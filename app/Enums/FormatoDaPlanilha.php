<?php

namespace App\Enums;

use App\Enums\Concerns\DescreveOpcoes;
use App\Enums\Contracts\Rotulavel;

/**
 * O leitor de folhas exporta de dois jeitos, e as colunas `Q` mudam de
 * significado entre eles.
 *
 * Em `acerto`, a célula traz 1 ou 0: o leitor já conferiu contra o
 * gabarito e informa só o veredito. Em `resposta`, traz a letra que o
 * aluno marcou, e quem confere é o sistema.
 *
 * A diferença importa além da leitura: sem a letra não há como dizer
 * **qual** alternativa errada atraiu a turma, que é o que distingue
 * "chutaram" de "confundiram com aquele conceito ali".
 *
 * Os dois são aceitos, e o formato é reconhecido pelo conteúdo. Antes só
 * o primeiro era lido, e uma planilha de letras entrava com tudo zerado —
 * `(float) 'A'` é 0 — salva apenas pelo aviso de que a contagem do leitor
 * não batia.
 */
enum FormatoDaPlanilha: string implements Rotulavel
{
    use DescreveOpcoes;

    case Acerto = 'acerto';
    case Resposta = 'resposta';

    public function rotulo(): string
    {
        return match ($this) {
            self::Acerto => 'Acerto e erro (1 ou 0)',
            self::Resposta => 'Resposta do aluno (a letra marcada)',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::Acerto => 'O leitor conferiu contra o gabarito e exportou só o veredito. '
                .'Não dá para saber qual alternativa errada o aluno marcou.',
            self::Resposta => 'A planilha traz a letra que cada aluno marcou. '
                .'O sistema confere contra o gabarito da prova e guarda a marcação.',
        };
    }

    public function cor(): string
    {
        return match ($this) {
            self::Acerto => 'cinza',
            self::Resposta => 'verde',
        };
    }

    public function temMarcacao(): bool
    {
        return $this === self::Resposta;
    }
}
