<?php

namespace App\Support;

use App\Exceptions\RegraDeNegocioException;
use App\Models\SolicitacaoParte;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ExcecaoDeLeitura;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

/**
 * A planilha com as questões de uma parte da solicitação: o modelo que o
 * professor baixa e o arquivo que volta preenchido, em XLSX, ODS ou CSV.
 *
 * Uma linha é uma questão: número, enunciado, habilidade, pontuação, uma
 * coluna por alternativa e a letra da correta. O número de alternativas
 * vem da solicitação, então o modelo é sempre o daquela parte.
 *
 * Aqui só se lê e se confere o formato de cada linha. O que depende do
 * banco — se a questão existe, se ainda aceita alteração — é decidido em
 * ImportarQuestoesAction.
 */
final class PlanilhaDeQuestoes
{
    /** Campo => nomes aceitos no cabeçalho, já normalizados. As alternativas são casadas à parte. */
    public const COLUNAS = [
        'questao' => ['questao', 'numero', 'numero da questao', 'n', 'no'],
        'enunciado' => ['enunciado'],
        'habilidade' => ['habilidade', 'habilidade avaliada'],
        'pontuacao' => ['pontuacao', 'peso', 'pontos', 'valor'],
        'correta' => ['correta', 'alternativa correta', 'resposta', 'resposta correta', 'gabarito'],
    ];

    public const OBRIGATORIAS = ['questao', 'enunciado', 'habilidade', 'pontuacao', 'correta'];

    /**
     * @param  array<int, array{linha: int, questao: ?int, questao_texto: ?string, enunciado: ?string, habilidade: ?string, pontuacao: ?float, alternativas: array<string, ?string>, correta: ?string, erros: array<int, string>}>  $linhas  as preenchidas
     * @param  array<int, int>  $naoPreenchidas  números de questão com a linha só numerada
     */
    private function __construct(
        public readonly array $linhas,
        public readonly array $naoPreenchidas,
    ) {}

    /** As letras das alternativas da parte: A, B, C, D… */
    public static function letras(int $quantidade): array
    {
        return array_map(fn (int $i) => chr(65 + $i), range(0, $quantidade - 1));
    }

    /** @return array<int, string> o cabeçalho do modelo */
    public static function cabecalho(int $alternativas): array
    {
        return [
            'Questão', 'Enunciado', 'Habilidade', 'Pontuação',
            ...array_map(fn (string $letra) => "Alternativa {$letra}", self::letras($alternativas)),
            'Correta',
        ];
    }

    /** O modelo da parte, já com as linhas numeradas: uma por questão pedida. */
    public static function modelo(SolicitacaoParte $parte): Spreadsheet
    {
        $alternativas = (int) $parte->solicitacao->quantidade_alternativas;
        $cabecalho = self::cabecalho($alternativas);
        $ultima = chr(64 + count($cabecalho));

        $planilha = new Spreadsheet;
        $questoes = $planilha->getActiveSheet()->setTitle('Questões');
        $questoes->fromArray($cabecalho, null, 'A1');
        $questoes->fromArray(array_map(fn (int $n) => [$n], range(1, (int) $parte->quantidade_questoes)), null, 'A2');
        $questoes->getStyle("A1:{$ultima}1")->getFont()->setBold(true);
        $questoes->getStyle("A1:{$ultima}".($parte->quantidade_questoes + 1))->getAlignment()
            ->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $questoes->freezePane('B2');

        $larguras = ['A' => 9, 'B' => 60, 'C' => 30, 'D' => 11];
        foreach (self::letras($alternativas) as $i => $letra) {
            $larguras[chr(69 + $i)] = 30;
        }
        $larguras[$ultima] = 9;

        foreach ($larguras as $coluna => $largura) {
            $questoes->getColumnDimension($coluna)->setWidth($largura);
        }

        $letras = self::letras($alternativas);
        $intervalo = $letras[0].' a '.end($letras);

        $instrucoes = $planilha->createSheet()->setTitle('Como preencher');
        $instrucoes->fromArray([
            ["{$parte->disciplina->nome} — {$parte->quantidade_questoes} questão(ões), {$alternativas} alternativas cada"],
            [],
            ['Coluna', 'Obrigatória', 'Como preencher'],
            ['Questão', 'Sim', 'O número da questão, já preenchido. Não mude.'],
            ['Enunciado', 'Sim', 'O texto da pergunta. Use **negrito** e *itálico* como na tela.'],
            ['Habilidade', 'Sim', 'A habilidade que a questão avalia.'],
            ['Pontuação', 'Sim', 'Quanto a questão vale: um número maior que zero, como 1 ou 1,5.'],
            ["Alternativa {$intervalo}", 'Sim', 'O texto de cada alternativa. Todas precisam ser preenchidas.'],
            ['Correta', 'Sim', "A letra da alternativa correta: {$intervalo}."],
            [],
            ['Uma questão por linha. Linha deixada só com o número não altera a questão.'],
            ['As questões entram como rascunho: revise na tela e envie para análise como de costume.'],
        ], null, 'A1');
        $instrucoes->getStyle('A1')->getFont()->setBold(true);
        $instrucoes->getStyle('A3:C3')->getFont()->setBold(true);
        $instrucoes->getColumnDimension('A')->setWidth(18);
        $instrucoes->getColumnDimension('B')->setWidth(12);
        $instrucoes->getColumnDimension('C')->setWidth(80);

        $planilha->setActiveSheetIndex(0);

        return $planilha;
    }

    public static function ler(string $caminho, int $alternativas): self
    {
        try {
            $leitor = IOFactory::createReaderForFile($caminho);
            $leitor->setReadDataOnly(true);

            if ($leitor instanceof Csv) {
                // O Excel brasileiro salva CSV com ";" e em Windows-1252.
                $leitor->setInputEncoding(Csv::GUESS_ENCODING);
                $leitor->setDelimiter(null);
            }

            $celulas = $leitor->load($caminho)->getSheet(0)->toArray(null, true, false, false);
        } catch (ExcecaoDeLeitura) {
            throw RegraDeNegocioException::porque('Não foi possível ler o arquivo. Envie a planilha em XLSX, ODS ou CSV.');
        }

        if ($celulas === []) {
            throw RegraDeNegocioException::porque('A planilha está vazia.');
        }

        $letras = self::letras($alternativas);
        [$posicoes, $colunasDeAlternativa] = self::mapearCabecalho(array_shift($celulas), $letras);

        $linhas = [];
        $naoPreenchidas = [];

        foreach ($celulas as $indice => $celula) {
            $texto = fn (?int $coluna) => self::texto($celula, $coluna);

            $numero = $texto($posicoes['questao']);
            $preenchidas = array_filter([
                $texto($posicoes['enunciado']), $texto($posicoes['habilidade']),
                $texto($posicoes['pontuacao']), $texto($posicoes['correta']),
                ...array_map($texto, $colunasDeAlternativa),
            ]);

            if ($preenchidas === []) {
                // Linha do modelo só com o número: o professor não preencheu essa questão.
                if ($numero !== null && ctype_digit($numero)) {
                    $naoPreenchidas[] = (int) $numero;
                }

                continue;
            }

            $alternativasDaLinha = [];
            foreach ($colunasDeAlternativa as $letra => $coluna) {
                $alternativasDaLinha[$letra] = $texto($coluna);
            }

            $pontuacao = self::numero($texto($posicoes['pontuacao']));
            $correta = $texto($posicoes['correta']);

            $linha = [
                // +2: a planilha conta a partir de 1, e o cabeçalho é a primeira.
                'linha' => $indice + 2,
                'questao' => $numero !== null && ctype_digit($numero) ? (int) $numero : null,
                'questao_texto' => $numero,
                'enunciado' => $texto($posicoes['enunciado']),
                'habilidade' => $texto($posicoes['habilidade']),
                'pontuacao' => $pontuacao,
                'alternativas' => $alternativasDaLinha,
                'correta' => $correta === null ? null : mb_strtoupper(trim($correta)),
                'erros' => [],
            ];

            $linha['erros'] = self::errosDa($linha, $texto($posicoes['pontuacao']), $letras);
            $linhas[] = $linha;
        }

        return new self($linhas, $naoPreenchidas);
    }

    /**
     * @param  array<int, mixed>  $cabecalho
     * @param  array<int, string>  $letras
     * @return array{0: array<string, int>, 1: array<string, int>} campos e colunas das alternativas
     */
    private static function mapearCabecalho(array $cabecalho, array $letras): array
    {
        $posicoes = [];
        $alternativas = [];
        $sobrando = [];

        foreach ($cabecalho as $indice => $celula) {
            $titulo = PlanilhaDeEscopo::normalizar((string) $celula);

            if (preg_match('/^(?:alternativa|alt\.?)\s*([a-z])$/', $titulo, $achado)) {
                $letra = mb_strtoupper($achado[1]);

                if (in_array($letra, $letras, true)) {
                    $alternativas[$letra] ??= $indice;
                } else {
                    $sobrando[] = $letra;
                }

                continue;
            }

            foreach (self::COLUNAS as $campo => $aceitos) {
                if (in_array($titulo, $aceitos, true) && ! isset($posicoes[$campo])) {
                    $posicoes[$campo] = $indice;
                }
            }
        }

        $rotulos = array_combine(self::OBRIGATORIAS, ['Questão', 'Enunciado', 'Habilidade', 'Pontuação', 'Correta']);
        $faltando = [
            ...array_map(fn ($c) => $rotulos[$c], array_values(array_diff(self::OBRIGATORIAS, array_keys($posicoes)))),
            ...array_map(fn ($l) => "Alternativa {$l}", array_values(array_diff($letras, array_keys($alternativas)))),
        ];

        if ($faltando !== []) {
            throw RegraDeNegocioException::porque(
                'A planilha não tem a(s) coluna(s): '.implode(', ', $faltando).'. '
                .'Baixe o modelo desta disciplina e preencha sem mudar o cabeçalho.'
            );
        }

        if ($sobrando !== []) {
            throw RegraDeNegocioException::porque(
                'Esta solicitação pede '.count($letras).' alternativas ('.$letras[0].' a '.end($letras).'), '
                .'e a planilha tem coluna(s) para '.implode(', ', array_map(fn ($l) => "Alternativa {$l}", $sobrando)).'. '
                .'Baixe o modelo desta disciplina.'
            );
        }

        ksort($alternativas);

        return [$posicoes, $alternativas];
    }

    /**
     * Tudo o que falta na linha, de uma vez: o professor corrige a linha
     * inteira numa passada, em vez de descobrir um erro por envio.
     *
     * @param  array<string, mixed>  $linha
     * @param  array<int, string>  $letras
     * @return array<int, string>
     */
    private static function errosDa(array $linha, ?string $pontuacaoEscrita, array $letras): array
    {
        $erros = [];
        $intervalo = $letras[0].' a '.end($letras);

        if ($linha['questao_texto'] === null) {
            $erros[] = 'sem o número da questão';
        } elseif ($linha['questao'] === null) {
            $erros[] = "número da questão \"{$linha['questao_texto']}\" inválido";
        }

        if ($linha['enunciado'] === null) {
            $erros[] = 'sem enunciado';
        }

        if ($linha['habilidade'] === null) {
            $erros[] = 'sem habilidade';
        }

        if ($pontuacaoEscrita === null) {
            $erros[] = 'sem pontuação';
        } elseif ($linha['pontuacao'] === null || $linha['pontuacao'] <= 0) {
            $erros[] = "pontuação \"{$pontuacaoEscrita}\" inválida: use um número maior que zero, como 1 ou 1,5";
        }

        $vazias = array_keys(array_filter($linha['alternativas'], fn ($texto) => $texto === null));

        if ($vazias !== []) {
            $erros[] = count($vazias) === 1
                ? "alternativa {$vazias[0]} vazia"
                : 'alternativas '.implode(', ', $vazias).' vazias';
        }

        if ($linha['correta'] === null) {
            $erros[] = "sem a alternativa correta: informe a letra ({$intervalo})";
        } elseif (! in_array($linha['correta'], $letras, true)) {
            $erros[] = "alternativa correta \"{$linha['correta']}\" não existe: use {$intervalo}";
        }

        return $erros;
    }

    /** "1", "1,5", "1.5" e "2 pontos" querem dizer o que parecem. */
    private static function numero(?string $valor): ?float
    {
        if ($valor === null) {
            return null;
        }

        $limpo = str_replace(',', '.', trim(preg_replace('/\s*pontos?$/i', '', $valor)));

        return is_numeric($limpo) ? (float) $limpo : null;
    }

    /** @param  array<int, mixed>  $celula */
    private static function texto(array $celula, ?int $coluna): ?string
    {
        $valor = $coluna === null ? '' : trim((string) ($celula[$coluna] ?? ''));

        return $valor === '' ? null : $valor;
    }
}
