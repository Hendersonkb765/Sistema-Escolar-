<?php

namespace App\Support;

use App\Enums\TipoDeEvento;
use App\Exceptions\RegraDeNegocioException;
use Carbon\CarbonInterface;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ExcecaoDeLeitura;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Throwable;

/**
 * A planilha de eventos e feriados: o modelo que se baixa e o arquivo
 * que volta preenchido, em XLSX, ODS ou CSV.
 *
 * Como a do escopo, o cabeçalho é procurado por nome — sem caixa, sem
 * acento, sem depender da ordem —, e cada linha que não entra diz por
 * quê. Quem preenche é a secretaria, no Excel: a data chega como data do
 * Excel ou como texto "12/10/2026", e as duas valem. "10/12/2026" é 10 de
 * dezembro, nunca 12 de outubro.
 */
final class PlanilhaDeEventos
{
    /** Campo => nomes aceitos no cabeçalho, já normalizados. */
    public const COLUNAS = [
        'titulo' => ['titulo', 'evento', 'nome', 'nome do evento'],
        'tipo' => ['tipo', 'tipo do evento'],
        'inicio' => ['data', 'inicio', 'data inicial', 'data de inicio'],
        'fim' => ['ate', 'fim', 'data final', 'data de termino', 'termino'],
        'descricao' => ['descricao', 'observacao', 'observacoes'],
    ];

    /** O cabeçalho do modelo, como a secretaria o lê. */
    public const ROTULOS = [
        'titulo' => 'Título',
        'tipo' => 'Tipo',
        'inicio' => 'Data',
        'fim' => 'Até',
        'descricao' => 'Descrição',
    ];

    public const OBRIGATORIAS = ['titulo', 'tipo', 'inicio'];

    /**
     * @param  array<int, array{linha: int, titulo: ?string, tipo: ?TipoDeEvento, inicio: ?CarbonInterface, fim: ?CarbonInterface, descricao: ?string, erro: ?string}>  $linhas
     */
    private function __construct(public readonly array $linhas) {}

    public static function ler(string $caminho): self
    {
        try {
            $leitor = IOFactory::createReaderForFile($caminho);
            $leitor->setReadDataOnly(true);

            if ($leitor instanceof Csv) {
                // O Excel brasileiro salva CSV com ";" e em Windows-1252.
                $leitor->setInputEncoding(Csv::GUESS_ENCODING);
                $leitor->setDelimiter(null);
            }

            $celulas = $leitor->load($caminho)->getActiveSheet()->toArray(null, true, false, false);
        } catch (ExcecaoDeLeitura) {
            throw RegraDeNegocioException::porque('Não foi possível ler o arquivo. Envie a planilha em XLSX, ODS ou CSV.');
        }

        if ($celulas === []) {
            throw RegraDeNegocioException::porque('A planilha está vazia.');
        }

        $posicoes = self::mapearCabecalho(array_shift($celulas));
        $linhas = [];

        foreach ($celulas as $indice => $celula) {
            $texto = fn (string $campo) => self::texto($celula, $posicoes[$campo] ?? null);

            if ($texto('titulo') === null && $texto('tipo') === null && $texto('inicio') === null) {
                continue;
            }

            $tipo = self::tipo($texto('tipo'));
            $inicio = self::data($celula[$posicoes['inicio']] ?? null);
            $fim = isset($posicoes['fim']) && $texto('fim') !== null ? self::data($celula[$posicoes['fim']]) : $inicio;

            $linhas[] = [
                // +2: a planilha conta a partir de 1, e o cabeçalho é a primeira.
                'linha' => $indice + 2,
                'titulo' => $texto('titulo'),
                'tipo' => $tipo,
                'inicio' => $inicio,
                'fim' => $fim,
                'descricao' => $texto('descricao'),
                'erro' => self::erroDa($texto, $tipo, $inicio, $fim, isset($posicoes['fim'])),
            ];
        }

        if ($linhas === []) {
            throw RegraDeNegocioException::porque('A planilha tem cabeçalho e nenhum evento. Preencha a partir da segunda linha.');
        }

        return new self($linhas);
    }

    /** O modelo para preencher: o cabeçalho, e uma aba explicando cada coluna. */
    public static function modelo(): Spreadsheet
    {
        $planilha = new Spreadsheet;

        $eventos = $planilha->getActiveSheet()->setTitle('Eventos');
        $eventos->fromArray(array_values(self::ROTULOS), null, 'A1');
        $eventos->getStyle('A1:E1')->getFont()->setBold(true);

        foreach (['A' => 40, 'B' => 14, 'C' => 14, 'D' => 14, 'E' => 50] as $coluna => $largura) {
            $eventos->getColumnDimension($coluna)->setWidth($largura);
        }

        // Data como texto: o Excel não troca 12/10 por 10/12 conforme o computador.
        $eventos->getStyle('C:D')->getNumberFormat()->setFormatCode('@');

        $instrucoes = $planilha->createSheet()->setTitle('Como preencher');
        $instrucoes->fromArray([
            ['Coluna', 'Obrigatória', 'Como preencher'],
            ['Título', 'Sim', 'O nome do evento. Ex.: Nossa Senhora Aparecida, Conselho de classe.'],
            ['Tipo', 'Sim', 'Feriado, Recesso, Evento ou Reunião. Feriado e recesso são dias sem aula.'],
            ['Data', 'Sim', 'O dia, como 12/10/2026 (dia/mês/ano).'],
            ['Até', 'Não', 'O último dia, para um período como o recesso. Em branco: um dia só.'],
            ['Descrição', 'Não', 'O que os professores precisam saber.'],
            [],
            ['Preencha a aba "Eventos" a partir da segunda linha, um evento por linha. Não mude os nomes do cabeçalho.'],
        ], null, 'A1');
        $instrucoes->getStyle('A1:C1')->getFont()->setBold(true);
        $instrucoes->getColumnDimension('A')->setWidth(14);
        $instrucoes->getColumnDimension('B')->setWidth(12);
        $instrucoes->getColumnDimension('C')->setWidth(80);

        $planilha->setActiveSheetIndex(0);

        return $planilha;
    }

    /**
     * @param  array<int, mixed>  $cabecalho
     * @return array<string, int>
     */
    private static function mapearCabecalho(array $cabecalho): array
    {
        $posicoes = [];

        foreach ($cabecalho as $indice => $celula) {
            $titulo = PlanilhaDeEscopo::normalizar((string) $celula);

            foreach (self::COLUNAS as $campo => $aceitos) {
                if (in_array($titulo, $aceitos, true) && ! isset($posicoes[$campo])) {
                    $posicoes[$campo] = $indice;
                }
            }
        }

        $faltando = array_values(array_diff(self::OBRIGATORIAS, array_keys($posicoes)));

        if ($faltando !== []) {
            throw RegraDeNegocioException::porque(
                'A planilha não tem a(s) coluna(s): '.implode(', ', array_map(fn ($c) => self::ROTULOS[$c], $faltando)).'. '
                .'Baixe o modelo e preencha sem mudar o cabeçalho.'
            );
        }

        return $posicoes;
    }

    private static function tipo(?string $valor): ?TipoDeEvento
    {
        $normalizado = PlanilhaDeEscopo::normalizar((string) $valor);

        foreach (TipoDeEvento::cases() as $tipo) {
            if ($normalizado === PlanilhaDeEscopo::normalizar($tipo->rotulo())) {
                return $tipo;
            }
        }

        return null;
    }

    /**
     * A data como a planilha a traz: número de série do Excel, texto
     * "dd/mm/aaaa" ou "aaaa-mm-dd". Data que não existe (31/02) é nula.
     */
    public static function data(mixed $valor): ?CarbonInterface
    {
        if (is_int($valor) || is_float($valor) || (is_string($valor) && preg_match('/^\d{5}(\.\d+)?$/', trim($valor)))) {
            try {
                return Carbon::instance(Date::excelToDateTimeObject((float) $valor))->startOfDay();
            } catch (Throwable) {
                return null;
            }
        }

        $texto = trim((string) $valor);

        foreach (['!d/m/Y', '!Y-m-d', '!d-m-Y', '!d.m.Y'] as $formato) {
            try {
                $data = Carbon::createFromFormat($formato, $texto) ?: null;
            } catch (InvalidFormatException) {
                continue;
            }

            // Sem o reformatar, 31/02 virava 03/03 em silêncio.
            if ($data !== null && $data->format(substr($formato, 1)) === $texto) {
                return $data;
            }
        }

        return null;
    }

    /** @param  callable(string): ?string  $texto */
    private static function erroDa(callable $texto, ?TipoDeEvento $tipo, ?CarbonInterface $inicio, ?CarbonInterface $fim, bool $temColunaFim): ?string
    {
        if ($texto('titulo') === null) {
            return 'Sem título.';
        }

        if (mb_strlen($texto('titulo')) > 120) {
            return 'Título com mais de 120 caracteres.';
        }

        if ($tipo === null) {
            return 'Tipo "'.($texto('tipo') ?? '').'" não reconhecido. Use Feriado, Recesso, Evento ou Reunião.';
        }

        if ($inicio === null) {
            return 'Data "'.($texto('inicio') ?? '').'" não reconhecida. Use dia/mês/ano, como 12/10/2026.';
        }

        if ($temColunaFim && $texto('fim') !== null && $fim === null) {
            return 'Data final "'.$texto('fim').'" não reconhecida. Use dia/mês/ano, como 12/10/2026.';
        }

        if ($fim !== null && $fim->isBefore($inicio)) {
            return "A data final ({$fim->format('d/m/Y')}) vem antes da inicial ({$inicio->format('d/m/Y')}).";
        }

        return null;
    }

    /** @param  array<int, mixed>  $celula */
    private static function texto(array $celula, ?int $coluna): ?string
    {
        $valor = $coluna === null ? '' : trim((string) ($celula[$coluna] ?? ''));

        return $valor === '' ? null : $valor;
    }
}
