<?php

namespace App\Support;

/**
 * Os limites contra os quais a análise lê os resultados.
 *
 * Existem como objeto, e não como constantes espalhadas, por dois
 * motivos. O primeiro é que a escola muda esses números sem que isso
 * seja uma alteração de sistema. O segundo é que a tela precisa **dizer
 * qual limite usou** — um "precisa de atenção" sem o número contra o
 * qual foi medido é opinião, não leitura de dado.
 */
final class CriteriosDaAnalise
{
    public function __construct(
        /** Aproveitamento (%) a partir do qual uma habilidade é satisfatória. */
        public readonly float $criterioEsperado,
        /** Nota (0 a 10) a partir da qual o aluno atingiu o esperado. */
        public readonly float $notaEsperada,
        /** Mínimo de questões para concluir algo sobre uma habilidade. */
        public readonly int $minimoDeQuestoes,
        /** Mínimo de respostas válidas para a mesma conclusão. */
        public readonly int $minimoDeRespostas,
        /** Variação (em pontos percentuais) que conta como mudança. */
        public readonly float $variacaoRelevante,
        /** @var array<int, array{ate: float, rotulo: string, cor: string}> */
        public readonly array $faixas,
    ) {}

    public static function daConfiguracao(): self
    {
        return new self(
            criterioEsperado: (float) config('analise.criterio_esperado'),
            notaEsperada: (float) config('analise.nota_esperada'),
            minimoDeQuestoes: (int) config('analise.evidencia_minima.questoes'),
            minimoDeRespostas: (int) config('analise.evidencia_minima.respostas'),
            variacaoRelevante: (float) config('analise.variacao_relevante'),
            faixas: config('analise.faixas'),
        );
    }

    /**
     * Há evidência bastante para dizer alguma coisa?
     *
     * Uma questão só, respondida por três alunos, não sustenta "a turma
     * não domina isto". Sustenta "precisa de mais evidência", que é uma
     * conclusão diferente — e é a diferença entre informar e chutar.
     */
    public function temEvidencia(int $questoes, int $respostas): bool
    {
        return $questoes >= $this->minimoDeQuestoes && $respostas >= $this->minimoDeRespostas;
    }

    /** Por que a evidência não basta, em palavras, ou string vazia. */
    public function faltaDeEvidencia(int $questoes, int $respostas): string
    {
        $faltas = [];

        if ($questoes < $this->minimoDeQuestoes) {
            $faltas[] = $questoes === 1
                ? 'uma questão só'
                : "{$questoes} questões (o mínimo adotado é {$this->minimoDeQuestoes})";
        }

        if ($respostas < $this->minimoDeRespostas) {
            $faltas[] = "{$respostas} respostas (o mínimo adotado é {$this->minimoDeRespostas})";
        }

        return $faltas === [] ? '' : implode(' e ', $faltas);
    }

    /**
     * Como ler um aproveitamento.
     *
     * `inconclusivo` não é um meio-termo entre bom e ruim: é a recusa de
     * classificar sem base. Vem antes dos outros dois de propósito.
     */
    public function situacao(float $percentual, int $questoes, int $respostas): string
    {
        if (! $this->temEvidencia($questoes, $respostas)) {
            return 'inconclusivo';
        }

        return $percentual >= $this->criterioEsperado ? 'satisfatorio' : 'atencao';
    }

    /** @return array<string, string> */
    public static function rotulos(): array
    {
        return [
            'satisfatorio' => 'Resultado satisfatório',
            'atencao' => 'Indício de dificuldade',
            'inconclusivo' => 'Evidências insuficientes',
        ];
    }

    /** @return array<string, string> */
    public static function cores(): array
    {
        return [
            'satisfatorio' => 'verde',
            'atencao' => 'vermelho',
            'inconclusivo' => 'cinza',
        ];
    }

    /** Em qual faixa de nota cai um valor de 0 a 10. */
    public function faixaDaNota(float $nota): int
    {
        foreach ($this->faixas as $indice => $faixa) {
            if ($nota <= $faixa['ate']) {
                return $indice;
            }
        }

        return count($this->faixas) - 1;
    }

    /**
     * A variação entre dois períodos, quando ela cabe ser lida.
     *
     * Devolve `null` quando a diferença está dentro do que se considera
     * oscilação: dizer "melhorou 1,2 ponto" sobre duas provas diferentes
     * é precisão que o dado não tem.
     */
    public function variacao(float $antes, float $depois): ?float
    {
        $diferenca = round($depois - $antes, 1);

        return abs($diferenca) >= $this->variacaoRelevante ? $diferenca : null;
    }
}
