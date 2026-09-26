<?php

namespace App\Support;

/**
 * Uma linha da planilha do leitor de folhas, já lida.
 *
 * `Q1`, `Q2`… chegam como 1 (acertou) ou 0 (errou) — o leitor não diz
 * qual alternativa o aluno marcou, só se bateu com o gabarito. Por isso
 * `respostas` é um mapa de número da questão para acerto, e não para
 * letra.
 */
final class LinhaDeResultado
{
    /**
     * @param  array<int, bool>  $respostas  número da questão => acertou
     */
    public function __construct(
        public readonly int $linha,
        public readonly string $nome,
        public readonly string $sobrenome,
        public readonly ?string $identificacao,
        public readonly array $respostas,
        public readonly ?int $totalInformado = null,
        public readonly ?int $acertosInformados = null,
    ) {}

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
