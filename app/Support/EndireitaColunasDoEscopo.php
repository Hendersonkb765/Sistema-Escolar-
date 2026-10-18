<?php

namespace App\Support;

use App\Exceptions\RegraDeNegocioException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Desfaz o giro de três colunas na planilha do escopo-sequência.
 *
 * No arquivo que a rede publicou, parte das linhas veio com as colunas
 * "Competência técnica", "Competências socioemocionais" e "Nome do
 * componente" giradas uma casa:
 *
 *     técnica  <- tem o NOME
 *     socio    <- tem a TÉCNICA
 *     nome     <- tem a SOCIOEMOCIONAL
 *
 * A correção **não é adivinhada**: ela é testada. Um código identifica
 * um componente, então o nome tem de ser o mesmo em todas as linhas
 * daquele código. O giro só é aplicado quando resolve essa
 * inconsistência — e quando não resolve, a classe diz e não encosta nas
 * linhas. É a diferença entre consertar e inventar.
 *
 * O original nunca é sobrescrito: a saída é sempre um arquivo novo.
 */
final class EndireitaColunasDoEscopo
{
    /** As três colunas que giram juntas, na ordem em que o conteúdo andou. */
    private const GIRAM = ['competencia_tecnica', 'competencias_socioemocionais', 'componente_nome'];

    /**
     * @param  array<string, array{linhas: int, de: string, para: string}>  $corrigidos
     * @param  array<string, int>  $naoResolvidos  código => quantidade de nomes
     */
    private function __construct(
        public readonly array $corrigidos,
        public readonly array $naoResolvidos,
        public readonly int $linhasTocadas,
    ) {}

    public static function em(string $origem, string $destino): self
    {
        $planilha = self::abrir($origem);
        $aba = $planilha->getActiveSheet();

        $celulas = $aba->toArray(null, true, false, false);
        [$colunas, $codigo] = self::mapear(array_shift($celulas) ?? []);

        $suspeitos = self::codigosComVariosNomes($celulas, $colunas, $codigo);

        $corrigidos = [];
        $naoResolvidos = [];
        $tocadas = 0;

        foreach ($suspeitos as $cod => $linhas) {
            $antes = self::nomesDistintos($celulas, $linhas, $colunas['componente_nome']);

            // O giro vale se, depois dele, o código passa a ter um nome só.
            $depois = self::nomesDistintos($celulas, $linhas, $colunas['competencia_tecnica']);

            if (count($depois) !== 1) {
                $naoResolvidos[$cod] = count($antes);

                continue;
            }

            foreach ($linhas as $i) {
                self::girar($aba, $i + 2, $colunas);
                $tocadas++;
            }

            $corrigidos[$cod] = [
                'linhas' => count($linhas),
                'de' => $antes[0],
                'para' => $depois[0],
            ];
        }

        if ($tocadas > 0) {
            (new Xlsx($planilha))->save($destino);
        }

        return new self($corrigidos, $naoResolvidos, $tocadas);
    }

    private static function abrir(string $origem): Spreadsheet
    {
        if (! is_file($origem)) {
            throw RegraDeNegocioException::porque("Arquivo não encontrado: {$origem}");
        }

        try {
            return IOFactory::createReaderForFile($origem)->load($origem);
        } catch (\Throwable $excecao) {
            throw RegraDeNegocioException::porque(
                'Não foi possível ler o arquivo. Envie o escopo em XLSX, ODS ou CSV.'
            );
        }
    }

    /**
     * @param  array<int, mixed>  $cabecalho
     * @return array{0: array<string, int>, 1: int}
     */
    private static function mapear(array $cabecalho): array
    {
        $posicoes = [];

        foreach ($cabecalho as $indice => $celula) {
            $titulo = PlanilhaDeEscopo::normalizar((string) $celula);

            foreach (PlanilhaDeEscopo::COLUNAS as $campo => $aceitos) {
                if (in_array($titulo, $aceitos, true) && ! isset($posicoes[$campo])) {
                    $posicoes[$campo] = $indice;
                }
            }
        }

        $exigidas = [...self::GIRAM, 'componente_codigo'];

        foreach ($exigidas as $campo) {
            if (! isset($posicoes[$campo])) {
                throw RegraDeNegocioException::porque(
                    'A planilha não tem a coluna "'.PlanilhaDeEscopo::ROTULOS[$campo].'", '
                    .'e sem ela não há como endireitar as outras.'
                );
            }
        }

        return [$posicoes, $posicoes['componente_codigo']];
    }

    /**
     * @param  array<int, array<int, mixed>>  $celulas
     * @param  array<string, int>  $colunas
     * @return array<string, array<int, int>> código => índices das linhas
     */
    private static function codigosComVariosNomes(array $celulas, array $colunas, int $codigo): array
    {
        $porCodigo = [];

        foreach ($celulas as $i => $celula) {
            $cod = trim((string) ($celula[$codigo] ?? ''));

            if ($cod !== '') {
                $porCodigo[$cod][] = $i;
            }
        }

        return array_filter(
            $porCodigo,
            fn (array $linhas) => count(self::nomesDistintos($celulas, $linhas, $colunas['componente_nome'])) > 1
        );
    }

    /**
     * @param  array<int, array<int, mixed>>  $celulas
     * @param  array<int, int>  $linhas
     * @return array<int, string>
     */
    private static function nomesDistintos(array $celulas, array $linhas, int $coluna): array
    {
        $nomes = [];

        foreach ($linhas as $i) {
            $valor = trim((string) ($celulas[$i][$coluna] ?? ''));

            if ($valor !== '') {
                $nomes[PlanilhaDeEscopo::normalizar($valor)] = $valor;
            }
        }

        return array_values($nomes);
    }

    /** @param  array<string, int>  $colunas */
    private static function girar($aba, int $linha, array $colunas): void
    {
        [$tecnica, $socio, $nome] = array_map(
            fn (string $campo) => self::coluna($aba, $colunas[$campo], $linha),
            self::GIRAM
        );

        $valores = [
            'competencia_tecnica' => $aba->getCell($socio)->getValue(),
            'competencias_socioemocionais' => $aba->getCell($nome)->getValue(),
            'componente_nome' => $aba->getCell($tecnica)->getValue(),
        ];

        $aba->setCellValue($tecnica, $valores['competencia_tecnica']);
        $aba->setCellValue($socio, $valores['competencias_socioemocionais']);
        $aba->setCellValue($nome, $valores['componente_nome']);
    }

    private static function coluna($aba, int $indice, int $linha): string
    {
        return $aba->getCell([$indice + 1, $linha])->getCoordinate();
    }
}
