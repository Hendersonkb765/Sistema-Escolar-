<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * A evolução das notas ao longo dos bimestres, em SVG.
 *
 * Desenhado no servidor, e não por uma biblioteca de gráficos no
 * navegador, por três motivos: não entra dependência nova, o resultado é
 * conferível pela mesma suíte de testes do resto, e a mesma marcação
 * serve ao tema claro e ao escuro (a cor de cada série vem em dois tons
 * e o CSS escolhe).
 *
 * Forma: uma linha por disciplina, porque o trabalho do leitor é
 * distinguir séries ao longo do tempo. A paleta é categórica, de ordem
 * fixa — nunca ciclada —, e cada linha leva rótulo na ponta, que é o que
 * mantém a identidade legível sem depender só da cor.
 */
final class GraficoDeEvolucao
{
    /**
     * Paleta categórica em ordem fixa, um par (claro, escuro) por série.
     * Validada para daltonismo e contraste contra as duas superfícies.
     */
    public const PALETA = [
        ['#2a78d6', '#3987e5'],
        ['#eb6834', '#d95926'],
        ['#1baf7a', '#199e70'],
        ['#eda100', '#c98500'],
        ['#e87ba4', '#d55181'],
        ['#008300', '#008300'],
    ];

    /** Acima disto a cauda vira uma série só, em vez de inventar tons. */
    public const MAXIMO_DE_SERIES = 6;

    public const NOTA_MAXIMA = 10.0;

    private function __construct(
        /** @var array<int, string> */
        public readonly array $bimestres,
        /** @var array<int, array{nome: string, pontos: array<int, ?float>, cor: string, corEscura: string}> */
        public readonly array $series,
    ) {}

    /**
     * @param  array<int, string>  $bimestres  rótulos do eixo horizontal
     * @param  array<string, array<int, ?float>>  $series  disciplina => nota por bimestre (null = sem prova)
     */
    public static function de(array $bimestres, array $series): self
    {
        $preparadas = [];

        foreach (array_slice($series, 0, self::MAXIMO_DE_SERIES, preserve_keys: true) as $nome => $pontos) {
            $slot = self::PALETA[count($preparadas) % count(self::PALETA)];

            $preparadas[] = [
                'nome' => $nome,
                'pontos' => array_values($pontos),
                'cor' => $slot[0],
                'corEscura' => $slot[1],
            ];
        }

        return new self(array_values($bimestres), $preparadas);
    }

    public function vazio(): bool
    {
        return $this->series === []
            || collect($this->series)->every(
                fn (array $serie) => collect($serie['pontos'])->filter(fn (?float $p) => $p !== null)->isEmpty()
            );
    }

    /**
     * Coordenadas de cada série, já no sistema do SVG.
     *
     * Fica separado do desenho para o teste poder conferir a geometria
     * sem ler marcação.
     *
     * @return array<int, array{nome: string, cor: string, corEscura: string, pontos: array<int, array{x: float, y: float, valor: float, rotulo: string}>}>
     */
    public function coordenadas(float $largura = 720, float $altura = 260): array
    {
        [$esquerda, $direita, $topo, $base] = [44.0, 132.0, 16.0, 34.0];

        $util = max(1.0, $largura - $esquerda - $direita);
        $alturaUtil = max(1.0, $altura - $topo - $base);
        $passo = count($this->bimestres) > 1 ? $util / (count($this->bimestres) - 1) : 0.0;

        return array_map(function (array $serie) use ($esquerda, $topo, $alturaUtil, $passo) {
            $pontos = [];

            foreach ($serie['pontos'] as $indice => $valor) {
                // Bimestre sem prova não vira zero: a linha se interrompe.
                if ($valor === null) {
                    continue;
                }

                $pontos[] = [
                    'x' => round($esquerda + $indice * $passo, 2),
                    'y' => round($topo + $alturaUtil * (1 - min($valor, self::NOTA_MAXIMA) / self::NOTA_MAXIMA), 2),
                    'valor' => (float) $valor,
                    'rotulo' => number_format((float) $valor, 1, ',', '.'),
                ];
            }

            return [...$serie, 'pontos' => $pontos];
        }, $this->series);
    }

    /**
     * Onde escrever o nome de cada série, já afastados entre si.
     *
     * Linhas que terminam próximas colidiriam os rótulos. Empurrar cada
     * um para cima ou para baixo o solta da sua linha, e por isso vem
     * junto o `y` do ponto: a view traça um fio ligando os dois.
     *
     * @return array<int, array{nome: string, cor: string, corEscura: string, x: float, y: float, yDoPonto: float, rotulo: string}>
     */
    public function rotulos(float $largura = 720, float $altura = 260): array
    {
        $alturaDaLinha = 14.0;

        $candidatos = collect($this->coordenadas($largura, $altura))
            ->filter(fn (array $serie) => $serie['pontos'] !== [])
            ->map(function (array $serie) {
                $ultimo = end($serie['pontos']);

                return [
                    'nome' => $serie['nome'],
                    'cor' => $serie['cor'],
                    'corEscura' => $serie['corEscura'],
                    'x' => $ultimo['x'] + 26,
                    'y' => $ultimo['y'],
                    'yDoPonto' => $ultimo['y'],
                    'rotulo' => $ultimo['rotulo'],
                ];
            })
            ->sortBy('y')
            ->values()
            ->all();

        // Uma passada de cima para baixo basta: cada rótulo cede o
        // espaço mínimo ao anterior.
        foreach ($candidatos as $indice => $rotulo) {
            if ($indice === 0) {
                continue;
            }

            $minimo = $candidatos[$indice - 1]['y'] + $alturaDaLinha;

            if ($rotulo['y'] < $minimo) {
                $candidatos[$indice]['y'] = $minimo;
            }
        }

        return $candidatos;
    }

    public function svg(float $largura = 720, float $altura = 260): HtmlString
    {
        return new HtmlString(view('components.grafico-evolucao', [
            'grafico' => $this,
            'largura' => $largura,
            'altura' => $altura,
            'coordenadas' => $this->coordenadas($largura, $altura),
            'rotulos' => $this->rotulos($largura, $altura),
        ])->render());
    }
}
