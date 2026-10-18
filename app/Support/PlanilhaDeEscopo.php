<?php

namespace App\Support;

use App\Exceptions\RegraDeNegocioException;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Exception as ExcecaoDeLeitura;

/**
 * Lê a planilha do escopo-sequência — CSV ou XLSX.
 *
 * O arquivo vem pronto de fora e a escola não o escreve. Duas
 * consequências que moldam esta classe:
 *
 * 1. O cabeçalho é procurado por nome, sem depender de ordem, de caixa
 *    nem de acento. Uma coluna a mais no meio não atrapalha, e
 *    "Habildades socioemocionais" — como está escrito no arquivo que a
 *    escola recebe — é aceito junto com a grafia correta.
 * 2. Só o que a tela precisa para ordenar e filtrar é interpretado:
 *    bimestre, semana e os códigos. O resto entra como texto. Decidir
 *    que "2/1" é carga teórica e prática, ou que "Sim" é verdadeiro, é
 *    inventar significado para uma coluna que ninguém daqui controla.
 */
final class PlanilhaDeEscopo
{
    /** Campo => nomes aceitos no cabeçalho, já normalizados. */
    public const COLUNAS = [
        'bimestre' => ['bimestre'],
        'semana' => ['semana'],
        'componente_nome' => ['nome do componente', 'componente'],
        'componente_codigo' => ['codigo componente', 'codigo do componente'],
        'unidade_nome' => ['unidade curricular'],
        'unidade_codigo' => ['codigo unidade curricular', 'codigo da unidade curricular'],
        'titulo_aula' => ['titulo da aula'],
        'tema_semana' => ['tema da semana'],
        // Singular e plural: os arquivos da rede trazem os dois.
        'objetivos' => ['objetivos da aula', 'objetivo da aula'],
        // "– macro" é qualificador de um dos arquivos, não outra coluna.
        'objeto_conhecimento' => ['objeto de conhecimento', 'objeto de conhecimento - macro'],
        'habilidades_tecnicas' => ['habilidades tecnicas'],
        // A grafia torta é a do arquivo; a certa fica aceita também.
        'habilidades_socioemocionais' => ['habildades socioemocionais', 'habilidades socioemocionais'],
        'competencia_tecnica' => ['competencia tecnica'],
        'competencias_socioemocionais' => ['competencias socioemocionais'],
        'carga_horaria' => ['ch teorica/pratica', 'ch teorica / pratica', 'carga horaria'],
        'aula_complementar' => ['aula complementar'],
        'aulas_semanais' => ['componente de 3 ou 4 aulas semanais', 'aulas semanais'],
    ];

    /**
     * O cabeçalho como ele vem escrito no arquivo da rede.
     *
     * Serve só para a tela dizer o que esperar. O casamento é feito com
     * a forma normalizada — mostrar "titulo da aula" a quem vai conferir
     * a planilha seria pedir que ela procurasse por algo que não está
     * escrito lá.
     */
    public const ROTULOS = [
        'bimestre' => 'Bimestre',
        'semana' => 'Semana',
        'componente_nome' => 'Nome do componente',
        'componente_codigo' => 'Código componente',
        'unidade_nome' => 'Unidade curricular',
        'unidade_codigo' => 'Código unidade curricular',
        'titulo_aula' => 'Título da aula',
        'tema_semana' => 'Tema da semana',
        'objetivos' => 'Objetivos da aula',
        'objeto_conhecimento' => 'Objeto de conhecimento',
        'habilidades_tecnicas' => 'Habilidades técnicas',
        'habilidades_socioemocionais' => 'Habildades socioemocionais',
        'competencia_tecnica' => 'Competência técnica',
        'competencias_socioemocionais' => 'Competências socioemocionais',
        'carga_horaria' => 'CH teórica/prática',
        'aula_complementar' => 'Aula complementar',
        'aulas_semanais' => 'Componente de 3 ou 4 aulas semanais?',
    ];

    /** Sem estas quatro a linha não tem lugar no percurso. */
    public const OBRIGATORIAS = ['bimestre', 'semana', 'componente_nome', 'titulo_aula'];

    /**
     * @param  array<string, int>  $posicoes  campo => índice da coluna
     * @param  array<int, array<string, mixed>>  $linhas
     * @param  array<int, string>  $colunasIgnoradas  cabeçalhos que o sistema não usa
     */
    private function __construct(
        public readonly array $posicoes,
        public readonly array $linhas,
        public readonly array $colunasIgnoradas,
    ) {}

    public static function ler(string $caminho): self
    {
        try {
            $leitor = IOFactory::createReaderForFile($caminho);
            $leitor->setReadDataOnly(true);

            $celulas = $leitor->load($caminho)->getActiveSheet()->toArray(null, true, false, false);
        } catch (ExcecaoDeLeitura $excecao) {
            throw RegraDeNegocioException::porque(
                'Não foi possível ler o arquivo. Envie o escopo em XLSX, ODS ou CSV.'
            );
        }

        if ($celulas === []) {
            throw RegraDeNegocioException::porque('A planilha está vazia.');
        }

        [$posicoes, $ignoradas] = self::mapearCabecalho(array_shift($celulas));

        return new self($posicoes, self::lerLinhas($celulas, $posicoes), $ignoradas);
    }

    /**
     * @param  array<int, mixed>  $cabecalho
     * @return array{0: array<string, int>, 1: array<int, string>}
     */
    protected static function mapearCabecalho(array $cabecalho): array
    {
        $posicoes = [];
        $ignoradas = [];

        foreach ($cabecalho as $indice => $celula) {
            $titulo = self::normalizar((string) $celula);

            if ($titulo === '') {
                continue;
            }

            $campo = null;

            foreach (self::COLUNAS as $chave => $aceitos) {
                if (in_array($titulo, $aceitos, true)) {
                    $campo = $chave;

                    break;
                }
            }

            // Primeira ocorrência ganha: planilha com a coluna repetida
            // não faz a segunda apagar a primeira em silêncio.
            if ($campo !== null && ! isset($posicoes[$campo])) {
                $posicoes[$campo] = $indice;

                continue;
            }

            $ignoradas[] = trim((string) $celula);
        }

        $faltando = array_values(array_diff(self::OBRIGATORIAS, array_keys($posicoes)));

        if ($faltando !== []) {
            $nomes = array_map(fn (string $campo) => self::ROTULOS[$campo], $faltando);

            throw RegraDeNegocioException::porque(
                'A planilha não tem a(s) coluna(s): '.implode(', ', $nomes).'. '
                .'Use o arquivo do escopo-sequência como ele vem, sem renomear o cabeçalho.'
            );
        }

        return [$posicoes, $ignoradas];
    }

    /**
     * @param  array<int, array<int, mixed>>  $celulas
     * @param  array<string, int>  $posicoes
     * @return array<int, array<string, mixed>>
     */
    protected static function lerLinhas(array $celulas, array $posicoes): array
    {
        $linhas = [];
        $ordem = 0;

        foreach ($celulas as $indice => $celula) {
            $titulo = self::texto($celula, $posicoes['titulo_aula']);
            $componente = self::texto($celula, $posicoes['componente_nome']);

            // Linha em branco no fim da planilha é sobra, não erro.
            if ($titulo === null && $componente === null) {
                continue;
            }

            $campos = [];

            foreach (array_keys(self::COLUNAS) as $campo) {
                $campos[$campo] = self::texto($celula, $posicoes[$campo] ?? null);
            }

            $linhas[] = [
                // +2: a planilha conta a partir de 1 e o cabeçalho é a primeira.
                'linha' => $indice + 2,
                'ordem' => ++$ordem,
                ...$campos,
                'bimestre' => self::inteiro($campos['bimestre']),
                'semana' => self::inteiro($campos['semana']),
                'erro' => self::erroDa($campos),
            ];
        }

        if ($linhas === []) {
            throw RegraDeNegocioException::porque(
                'A planilha tem cabeçalho e nenhuma aula. Confira se enviou o arquivo certo.'
            );
        }

        return $linhas;
    }

    /**
     * Por que a linha não entra, quando não entra.
     *
     * @param  array<string, ?string>  $campos
     */
    protected static function erroDa(array $campos): ?string
    {
        if ($campos['titulo_aula'] === null) {
            return 'Sem título da aula.';
        }

        if ($campos['componente_nome'] === null) {
            return 'Sem nome do componente.';
        }

        $bimestre = self::inteiro($campos['bimestre']);

        if ($bimestre === null || $bimestre < 1 || $bimestre > 4) {
            return 'Bimestre inválido: "'.($campos['bimestre'] ?? '').'". Esperado de 1 a 4.';
        }

        $semana = self::inteiro($campos['semana']);

        if ($semana === null || $semana < 1) {
            return 'Semana inválida: "'.($campos['semana'] ?? '').'".';
        }

        return null;
    }

    /** As linhas que podem ser gravadas. @return array<int, array<string, mixed>> */
    public function aproveitaveis(): array
    {
        return array_values(array_filter($this->linhas, fn (array $linha) => $linha['erro'] === null));
    }

    /** As que ficam de fora, e por quê. @return array<int, array<string, mixed>> */
    public function recusadas(): array
    {
        return array_values(array_filter($this->linhas, fn (array $linha) => $linha['erro'] !== null));
    }

    /**
     * Códigos de componente que aparecem com mais de um nome.
     *
     * Um código identifica um componente: as 112 linhas de C1 dizem
     * todas "Programação Mobile". Quando o nome muda de linha para
     * linha, o que mudou não foi o componente — foi a coluna. Num
     * arquivo real, C5 apareceu com treze nomes porque três colunas
     * estavam rodadas uma casa, e o "Nome do componente" trazia o texto
     * da competência socioemocional.
     *
     * Sem este reparo a importação aceitaria tudo, e a tela mostraria um
     * parágrafo de competência onde devia estar o nome da matéria.
     *
     * A comparação é pela forma normalizada: acento ou caixa diferentes
     * entre duas linhas é desencontro de digitação, não troca de coluna.
     *
     * @return array<string, array<int, string>> código => nomes encontrados
     */
    public function codigosComNomesTrocados(): array
    {
        $porCodigo = [];

        foreach ($this->aproveitaveis() as $linha) {
            $codigo = $linha['componente_codigo'];

            if ($codigo === null) {
                continue;
            }

            $porCodigo[$codigo][self::normalizar($linha['componente_nome'])] = $linha['componente_nome'];
        }

        return array_filter(
            array_map(fn (array $nomes) => array_values($nomes), $porCodigo),
            fn (array $nomes) => count($nomes) > 1
        );
    }

    /** Os códigos de componente distintos que a planilha traz. @return array<int, string> */
    public function codigosDeComponente(): array
    {
        return collect($this->aproveitaveis())
            ->pluck('componente_codigo')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Sem caixa, sem acento, sem espaço sobrando e sem a interrogação —
     * "Componente de 3 ou 4 aulas semanais?" e "COMPONENTE DE 3 OU 4
     * AULAS SEMANAIS" são a mesma coluna.
     */
    public static function normalizar(string $titulo): string
    {
        $limpo = Str::of($titulo)->ascii()->lower()->replace(["\r", "\n", "\t"], ' ')->squish();

        return (string) $limpo->rtrim('?: ');
    }

    /** @param  array<int, mixed>  $celula */
    protected static function texto(array $celula, ?int $coluna): ?string
    {
        $valor = $coluna === null ? '' : trim((string) ($celula[$coluna] ?? ''));

        return $valor === '' ? null : $valor;
    }

    protected static function inteiro(?string $valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        // "3" e "3º" e "Bimestre 3" chegam do mesmo lugar e querem dizer o mesmo.
        preg_match('/\d+/', $valor, $achado);

        return isset($achado[0]) ? (int) $achado[0] : null;
    }
}
