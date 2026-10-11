<?php

namespace App\Support;

use App\Enums\FormatoDaPlanilha;
use App\Models\ProvaQuestao;
use Illuminate\Support\Collection;

/**
 * Uma linha da planilha do leitor de folhas, já lida.
 *
 * `Q1`, `Q2`… chegam de dois jeitos, conforme a exportação
 * ({@see FormatoDaPlanilha}): como 1 e 0, quando o leitor já conferiu
 * contra o gabarito, ou como a letra que o aluno marcou.
 *
 * No segundo caso `respostas` só fica verdadeiro depois de
 * {@see resolver()}: quem sabe qual é a correta é a prova, e a planilha
 * não tem como saber.
 */
final class LinhaDeResultado
{
    /**
     * @param  array<int, bool>  $respostas  número da questão => acertou
     * @param  array<int, ?string>  $marcadas  número da questão => letra marcada
     */
    public function __construct(
        public readonly int $linha,
        public readonly string $nome,
        public readonly string $sobrenome,
        public readonly ?string $identificacao,
        public readonly array $respostas,
        public readonly ?int $totalInformado = null,
        public readonly ?int $acertosInformados = null,
        public readonly FormatoDaPlanilha $formato = FormatoDaPlanilha::Acerto,
        public readonly array $marcadas = [],
    ) {}

    /**
     * A mesma linha, com o acerto decidido contra o gabarito da prova.
     *
     * Só faz algo no formato de letras: ali a planilha diz o que o aluno
     * marcou e cabe ao sistema conferir. No formato antigo o leitor já
     * conferiu, e refazer a conta seria inventar um gabarito que ele não
     * mandou.
     *
     * @param  Collection<int, ProvaQuestao>  $questoes  número => questão
     */
    public function resolver(Collection $questoes): self
    {
        if ($this->formato !== FormatoDaPlanilha::Resposta) {
            return $this;
        }

        $respostas = [];

        foreach ($this->marcadas as $numero => $letra) {
            $correta = $questoes->get($numero)?->letra_correta;

            // Sem letra marcada não há acerto; sem gabarito tampouco, e
            // a conferência avisa que a questão não existe na prova.
            $respostas[$numero] = $letra !== null
                && $correta !== null
                && mb_strtoupper($letra) === mb_strtoupper($correta);
        }

        return new self(
            linha: $this->linha,
            nome: $this->nome,
            sobrenome: $this->sobrenome,
            identificacao: $this->identificacao,
            respostas: $respostas,
            totalInformado: $this->totalInformado,
            acertosInformados: $this->acertosInformados,
            formato: $this->formato,
            marcadas: $this->marcadas,
        );
    }

    /**
     * As letras que não existem na questão correspondente.
     *
     * Um "E" numa questão de quatro alternativas é leitura errada da
     * folha ou coluna trocada — e contaria como erro do aluno.
     *
     * @param  Collection<int, ProvaQuestao>  $questoes
     * @return array<int, string> número da questão => letra
     */
    public function letrasInvalidas(Collection $questoes): array
    {
        $invalidas = [];

        foreach ($this->marcadas as $numero => $letra) {
            $questao = $questoes->get($numero);

            if ($letra === null || $questao === null) {
                continue;
            }

            if (! in_array(mb_strtoupper($letra), array_map('mb_strtoupper', $questao->letrasValidas()), true)) {
                $invalidas[$numero] = $letra;
            }
        }

        return $invalidas;
    }

    public function nomeCompleto(): string
    {
        return trim($this->nome.' '.$this->sobrenome);
    }

    public function acertos(): int
    {
        return count(array_filter($this->respostas));
    }

    /**
     * O leitor traz a própria contagem de acertos. Quando ela não bate
     * com as colunas `Q`, alguma coisa se perdeu no caminho — e é melhor
     * dizer isso do que importar em silêncio.
     */
    public function contagemConfere(): bool
    {
        return $this->acertosInformados === null || $this->acertosInformados === $this->acertos();
    }
}
