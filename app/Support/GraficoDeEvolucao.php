<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A evolução da nota, um painel por disciplina.
 *
 * Eram linhas sobrepostas, uma cor por disciplina. A forma não servia ao
 * dado por três motivos, e os três apareciam na tela:
 *
 * 1. **Quatro bimestres não são uma série contínua.** A linha liga dois
 *    pontos e com isso afirma o caminho entre eles, que não existe: não
 *    houve nota nenhuma em março. Coluna não afirma nada entre uma e
 *    outra.
 * 2. **A lacuna some.** Uma disciplina avaliada só no 2º bimestre virava
 *    um ponto solto, indistinguível de ruído — e a spec é explícita:
 *    ausência de avaliação não é queda de desempenho. Sem coluna e com
 *    "sem avaliação" escrito embaixo, a ausência se lê como ausência.
 * 3. **Seis cores não se distinguem.** Identidade por cor custa caro e a
 *    comparação que interessa é contra o critério da escola, não entre
 *    disciplinas. Com um painel por disciplina, o nome está no título e
 *    sobra uma cor só — que passa nos dois temas e em daltonismo, porque
 *    não precisa se distinguir de nenhuma outra.
 *
 * O que o professor pergunta é "esta disciplina está acima do que
 * esperamos, e está melhorando?". A linha tracejada do critério responde
 * a primeira por posição, sem depender de cor; a sequência de colunas
 * responde a segunda.
 */
final class GraficoDeEvolucao
{
    /**
     * Uma cor só, nos dois tons do tema.
     *
     * Validada contra as duas superfícies (contraste ≥ 3:1). Não precisa
     * de separação para daltonismo porque não há segunda série da qual
     * se distinguir — foi justamente o que a forma antiga pedia e não
     * conseguia entregar com seis disciplinas.
     */
    public const COR = '#2a78d6';

    public const COR_ESCURA = '#3987e5';

    /** Acima disto a cauda vira uma série só, em vez de inventar painéis. */
    public const MAXIMO_DE_SERIES = 6;

    public const NOTA_MAXIMA = 10.0;

    private function __construct(
        /** @var array<int, string> */
        public readonly array $bimestres,
        /** @var array<int, array{nome: string, pontos: array<int, ?float>}> */
        public readonly array $series,
        public readonly ?float $criterio,
    ) {}

    /**
     * @param  array<int, string>  $bimestres  rótulos do eixo horizontal
     * @param  array<string, array<int, ?float>>  $series  disciplina => nota por bimestre (null = sem prova)
     * @param  ?float  $criterio  nota esperada pela escola, desenhada como referência
     */
    public static function de(array $bimestres, array $series, ?float $criterio = null): self
    {
        $preparadas = [];

        foreach (array_slice($series, 0, self::MAXIMO_DE_SERIES, preserve_keys: true) as $nome => $pontos) {
            $preparadas[] = ['nome' => $nome, 'pontos' => array_values($pontos)];
        }

        return new self(array_values($bimestres), $preparadas, $criterio);
    }

    public function vazio(): bool
    {
        return $this->series === []
            || collect($this->series)->every(
                fn (array $serie) => collect($serie['pontos'])->filter(fn (?float $p) => $p !== null)->isEmpty()
            );
    }

    /**
     * As colunas de um painel, já no sistema do SVG.
     *
     * Fica separado do desenho para o teste poder conferir a geometria
     * sem ler marcação.
     *
     * @param  array<int, ?float>  $pontos
     * @return array<int, array{x: float, y: float, largura: float, altura: float, valor: ?float, rotulo: string, bimestre: string}>
     */
    public function colunas(array $pontos, float $largura = 228, float $altura = 132): array
    {
        // 30 à direita: é onde o rótulo do critério é escrito.
        [$esquerda, $direita, $topo, $base] = [26.0, 30.0, 18.0, 22.0];

        $util = max(1.0, $largura - $esquerda - $direita);
        $alturaUtil = max(1.0, $altura - $topo - $base);

        $quantas = max(1, count($this->bimestres));
        $passo = $util / $quantas;

        // Dois terços de coluna e um terço de respiro: o vão entre elas é
        // o que impede que duas vizinhas sejam lidas como uma só.
        $larguraDaColuna = round($passo * 0.62, 2);

        $colunas = [];

        foreach ($this->bimestres as $indice => $rotuloDoBimestre) {
            $valor = $pontos[$indice] ?? null;

            $centro = $esquerda + $passo * ($indice + 0.5);

            $alturaDaColuna = $valor === null
                ? 0.0
                : round($alturaUtil * min($valor, self::NOTA_MAXIMA) / self::NOTA_MAXIMA, 2);

            $colunas[] = [
                'x' => round($centro - $larguraDaColuna / 2, 2),
                'y' => round($topo + $alturaUtil - $alturaDaColuna, 2),
                'largura' => $larguraDaColuna,
                'altura' => $alturaDaColuna,
                'valor' => $valor === null ? null : (float) $valor,
                'rotulo' => $valor === null ? '' : number_format((float) $valor, 1, ',', '.'),
                'bimestre' => $rotuloDoBimestre,
            ];
        }

        return $colunas;
    }

    /** O `y` da linha de referência do critério, ou null quando não há. */
    public function linhaDoCriterio(float $altura = 132): ?float
    {
        if ($this->criterio === null) {
            return null;
        }

        [$topo, $base] = [18.0, 22.0];
        $alturaUtil = max(1.0, $altura - $topo - $base);

        return round($topo + $alturaUtil * (1 - min($this->criterio, self::NOTA_MAXIMA) / self::NOTA_MAXIMA), 2);
    }

    /** O chão do painel: onde as colunas nascem. */
    public function base(float $altura = 132): float
    {
        return $altura - 22.0;
    }

    public function svg(float $largura = 228, float $altura = 132): HtmlString
    {
        return new HtmlString(view('components.grafico-evolucao', [
            'grafico' => $this,
            'largura' => $largura,
            'altura' => $altura,
        ])->render());
    }
}
