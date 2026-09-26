<?php

namespace App\Support;

use App\Exceptions\RegraDeNegocioException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ExcecaoDeLeitura;

/**
 * Lê a planilha que o leitor de folhas de resposta exporta.
 *
 * O cabeçalho é o do próprio leitor:
 *
 *     Quiz Name, Class, ZipGrade Id, External Id, First Name, Last Name,
 *     Num Questions, Num Correct, Percent Correct, Key Version, Q1, Q2…
 *
 * As colunas fixas são procuradas pelo nome, sem depender da ordem nem
 * de maiúsculas, e as de questão por `Q` seguido do número. Uma planilha
 * com uma coluna a mais no meio continua sendo lida.
 */
final class PlanilhaDeResultados
{
    /** Nome da coluna, em minúsculas, para cada campo que interessa. */
    public const COLUNAS = [
        'nome' => 'first name',
        'sobrenome' => 'last name',
        'identificacao' => 'external id',
        'total' => 'num questions',
        'acertos' => 'num correct',
    ];

    /** Sem estas duas não há como saber de quem é a linha. */
    public const OBRIGATORIAS = ['nome', 'sobrenome'];

    /**
     * @param  array<string, int>  $posicoes  campo => índice da coluna
     * @param  array<int, int>  $questoes  número da questão => índice
     * @param  array<int, LinhaDeResultado>  $linhas
     */
    private function __construct(
        public readonly array $posicoes,
        public readonly array $questoes,
        public readonly array $linhas,
    ) {}

    public static function ler(string $caminho): self
    {
        // PhpSpreadsheet direto, e não o `maatwebsite/excel`: para uma
        // leitura só de valores, a classe `Import` que ele exige não
        // acrescenta nada. Ele segue valendo para as exportações.
        try {
            $leitor = IOFactory::createReaderForFile($caminho);
            $leitor->setReadDataOnly(true);

            $celulas = $leitor->load($caminho)->getActiveSheet()->toArray(null, true, false, false);
        } catch (ExcecaoDeLeitura $excecao) {
            throw RegraDeNegocioException::porque(
                'Não foi possível ler o arquivo. Envie a planilha em XLSX, XLS ou CSV.'
            );
        }

        if ($celulas === []) {
            throw RegraDeNegocioException::porque('A planilha está vazia.');
        }

        [$posicoes, $questoes] = self::mapearCabecalho(array_shift($celulas));

        return new self($posicoes, $questoes, self::lerLinhas($celulas, $posicoes, $questoes));
    }

    /**
     * @param  array<int, mixed>  $cabecalho
     * @return array{0: array<string, int>, 1: array<int, int>}
     */
    protected static function mapearCabecalho(array $cabecalho): array
    {
        $posicoes = [];
        $questoes = [];

        foreach ($cabecalho as $indice => $celula) {
            $titulo = mb_strtolower(trim((string) $celula));

            if (($campo = array_search($titulo, self::COLUNAS, true)) !== false) {
                $posicoes[$campo] = $indice;

                continue;
            }

            if (preg_match('/^q\s*(\d+)$/', $titulo, $achado) === 1) {
                $questoes[(int) $achado[1]] = $indice;
            }
        }

        foreach (self::OBRIGATORIAS as $campo) {
            if (! isset($posicoes[$campo])) {
                throw RegraDeNegocioException::porque(
                    'A planilha não tem a coluna "'.self::COLUNAS[$campo].'". '
                    .'Use o arquivo exportado pelo leitor de folhas, sem renomear as colunas.'
                );
            }
        }

        if ($questoes === []) {
            throw RegraDeNegocioException::porque(
                'A planilha não tem coluna de questão nenhuma. '
                .'Elas se chamam Q1, Q2, Q3 e assim por diante.'
            );
        }

        ksort($questoes);

        return [$posicoes, $questoes];
    }

    /**
     * @param  array<int, array<int, mixed>>  $celulas
     * @param  array<string, int>  $posicoes
     * @param  array<int, int>  $questoes
     * @return array<int, LinhaDeResultado>
     */
    protected static function lerLinhas(array $celulas, array $posicoes, array $questoes): array
    {
        $linhas = [];

        foreach ($celulas as $indice => $celula) {
            $nome = trim((string) ($celula[$posicoes['nome']] ?? ''));
            $sobrenome = trim((string) ($celula[$posicoes['sobrenome']] ?? ''));

            // Linha em branco no fim da planilha não é erro, é sobra.
            if ($nome === '' && $sobrenome === '') {
                continue;
            }

            $respostas = [];

            foreach ($questoes as $numero => $coluna) {
                $valor = trim((string) ($celula[$coluna] ?? ''));

                // Em branco conta como erro: o aluno não marcou nada.
                $respostas[$numero] = $valor !== '' && (float) $valor > 0;
            }

            $linhas[] = new LinhaDeResultado(
                // +2: a contagem da planilha começa em 1 e o cabeçalho é a primeira.
                linha: $indice + 2,
                nome: $nome,
                sobrenome: $sobrenome,
                identificacao: self::texto($celula, $posicoes['identificacao'] ?? null),
                respostas: $respostas,
                totalInformado: self::inteiro($celula, $posicoes['total'] ?? null),
                acertosInformados: self::inteiro($celula, $posicoes['acertos'] ?? null),
            );
        }

        return $linhas;
    }

    /** @param  array<int, mixed>  $celula */
    protected static function texto(array $celula, ?int $coluna): ?string
    {
        $valor = $coluna === null ? '' : trim((string) ($celula[$coluna] ?? ''));

        return $valor === '' ? null : $valor;
    }

    /** @param  array<int, mixed>  $celula */
    protected static function inteiro(array $celula, ?int $coluna): ?int
    {
        $valor = self::texto($celula, $coluna);

        return $valor === null || ! is_numeric($valor) ? null : (int) $valor;
    }
}
