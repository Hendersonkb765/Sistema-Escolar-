<?php

namespace App\Support;

use App\Enums\TipoDeDocumento;
use App\Models\Aluno;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

/**
 * Os campos que o corpo de um modelo de documento pode usar, e a troca
 * deles pelos dados de verdade.
 *
 * O corpo é texto, não HTML. Quem escreve é a coordenação, mas texto
 * guardado é texto exibido em três lugares diferentes — tela, PDF e
 * pré-visualização — e HTML solto em entrada de usuário vira XSS num
 * deles mais cedo ou mais tarde. Negrito e itálico reaproveitam as
 * marcas do enunciado ({@see TextoDoEnunciado}), já testadas.
 *
 * Dois grupos de campo, e a diferença importa:
 *
 * - os **de dado** (`{{ aluno.nome }}`) saem preenchidos;
 * - os **de preencher** (`{{ linha: Assinatura }}`) saem como uma linha
 *   em branco com o rótulo embaixo, para alguém escrever à mão.
 *
 * Campo que não existe não é ignorado em silêncio: `validar()` devolve o
 * nome errado para a tela dizer qual é, porque um `{{ aluno.nomee }}`
 * sobrando no meio de 200 autorizações só aparece depois de imprimir.
 */
final class CamposDoDocumento
{
    /** `{{ qualquer.coisa }}` ou `{{ linha: Rótulo }}`. */
    public const PADRAO = '/\{\{\s*([a-z_]+(?:\.[a-z_]+)?)\s*(?::\s*([^}]*?))?\s*\}\}/u';

    /** Campos de dado disponíveis em qualquer documento. */
    public const COMUNS = [
        'turma.nome' => 'Nome da turma',
        'turma.periodo' => 'Período da turma',
        'turma.periodo_letivo' => 'Ano letivo',
        'curso.nome' => 'Nome do curso',
        'eixo.nome' => 'Nome do Eixo',
        'instituicao' => 'Nome da escola',
        'data' => 'Data de hoje, por extenso',
        'data_curta' => 'Data de hoje (dd/mm/aaaa)',
    ];

    /** Só existem quando há um aluno, isto é, no documento individual. */
    public const DO_ALUNO = [
        'aluno.nome' => 'Nome do aluno',
        'aluno.ra' => 'RA do aluno',
        'aluno.numero' => 'Número do aluno na lista',
    ];

    /** Só existe no documento coletivo. */
    public const DA_TURMA = [
        'lista_de_alunos' => 'Tabela com os alunos selecionados',
    ];

    /** Campos para preencher à mão. */
    public const PARA_PREENCHER = [
        'linha' => 'Linha para preencher, dentro da frase. Aceita rótulo: {{ linha: nome }}',
        'assinatura' => 'Linha de assinatura, centrada, com o rótulo embaixo',
        'caixa' => 'Quadradinho para marcar. Aceita rótulo: {{ caixa: Autorizo }}',
        'espaco' => 'Quadro em branco para escrever várias linhas',
    ];

    /*
     * Marcas internas que separam os dois passos da renderização. São
     * caracteres de controle porque precisam atravessar o escape e o
     * formatador de negrito sem virar outra coisa — e porque ninguém os
     * digita num formulário.
     */
    protected const ABRE = "\x01";

    protected const FECHA = "\x02";

    /**
     * Todos os campos que este tipo de documento aceita.
     *
     * @return array<string, string> campo => explicação
     */
    public static function disponiveis(TipoDeDocumento $tipo): array
    {
        return array_merge(
            $tipo === TipoDeDocumento::Individual ? self::DO_ALUNO : self::DA_TURMA,
            self::COMUNS,
            self::PARA_PREENCHER,
        );
    }

    /**
     * Os campos usados no corpo que este tipo não aceita.
     *
     * @return array<int, string>
     */
    public static function validar(string $corpo, TipoDeDocumento $tipo): array
    {
        $aceitos = array_keys(self::disponiveis($tipo));

        preg_match_all(self::PADRAO, $corpo, $achados);

        $usados = array_unique($achados[1]);

        return array_values(array_diff($usados, $aceitos));
    }

    /**
     * Explica por que um campo não serve neste documento.
     *
     * Dizer "campo desconhecido" quando a pessoa escreveu
     * `{{ aluno.nome }}` num documento coletivo seria mentira: o campo
     * existe, só não neste tipo.
     */
    public static function motivoDaRecusa(string $campo, TipoDeDocumento $tipo): string
    {
        $noOutroTipo = match (true) {
            array_key_exists($campo, self::DO_ALUNO) => TipoDeDocumento::Individual,
            array_key_exists($campo, self::DA_TURMA) => TipoDeDocumento::Coletivo,
            default => null,
        };

        if ($noOutroTipo !== null) {
            return "{{ {$campo} }} só existe no documento \"{$noOutroTipo->rotulo()}\".";
        }

        return "{{ {$campo} }} não é um campo do documento.";
    }

    /**
     * O corpo com os campos trocados, já em HTML seguro.
     *
     * Em dois passos, e a ordem é o ponto. Trocar o campo primeiro e
     * formatar depois é o que faz `**{{ aluno.nome }}**` sair em negrito:
     * formatando antes, cada `**` cai num pedaço de texto diferente,
     * separado pelo campo no meio, e nenhum dos dois encontra o seu par —
     * o documento sai com os asteriscos impressos.
     *
     * Por isso o campo vira primeiro uma marca de um caractere só, que
     * atravessa o escape e o formatador sem ser tocada, e só no fim é
     * trocada pelo HTML dele.
     *
     * @param  array<string, mixed>  $contexto
     */
    public static function render(string $corpo, array $contexto): HtmlString
    {
        // As marcas internas não podem vir de fora.
        $corpo = str_replace([self::ABRE, self::FECHA], '', $corpo);

        $doCampo = [];

        $comMarcas = preg_replace_callback(
            self::PADRAO,
            function (array $achado) use (&$doCampo, $contexto): string {
                $indice = count($doCampo);
                $doCampo[$indice] = self::campoEmHtml($achado[1], $achado[2] ?? '', $contexto);

                return self::ABRE.$indice.self::FECHA;
            },
            $corpo,
        ) ?? $corpo;

        $html = nl2br(TextoDoEnunciado::paraHtml($comMarcas)->toHtml(), false);

        $html = preg_replace_callback(
            '/'.self::ABRE.'(\d+)'.self::FECHA.'/',
            fn (array $achado) => $doCampo[(int) $achado[1]] ?? '',
            $html,
        ) ?? $html;

        return new HtmlString($html);
    }

    /**
     * O corpo quebrado em trechos, para quem não desenha HTML.
     *
     * O .docx é montado bloco a bloco — o Word não aceita o HTML da
     * folha —, e o gerador dele precisa de trechos com `negrito` e
     * `italico` em vez de marcação, como já acontece com o enunciado da
     * prova.
     *
     * Passa pelo mesmo caminho de duas etapas do {@see render()}, e é o
     * que faz o negrito em volta de um campo valer nos dois formatos: um
     * `**{{ aluno.nome }}**` sai daqui como um trecho de texto já em
     * negrito, com o nome dentro.
     *
     * tipo: `texto`, `quebra`, `linha`, `assinatura`, `caixa`, `espaco`
     * ou `lista_de_alunos`.
     *
     * @param  array<string, mixed>  $contexto
     * @return array<int, array{tipo: string, texto?: string, rotulo?: string, negrito?: bool, italico?: bool}>
     */
    public static function trechos(string $corpo, array $contexto): array
    {
        $corpo = str_replace([self::ABRE, self::FECHA], '', $corpo);

        $campos = [];

        $comMarcas = preg_replace_callback(
            self::PADRAO,
            function (array $achado) use (&$campos, $contexto): string {
                $indice = count($campos);
                $campos[$indice] = self::descritor($achado[1], $achado[2] ?? '', $contexto);

                return self::ABRE.$indice.self::FECHA;
            },
            $corpo,
        ) ?? $corpo;

        $partes = [];

        foreach (TextoDoEnunciado::segmentos($comMarcas) as $run) {
            /*
             * Aqui o grupo do padrão é obrigatório, então o `preg_split`
             * alterna texto e índice sem falhas — ao contrário do padrão
             * dos campos, cujo rótulo é opcional.
             */
            $pedacos = preg_split(
                '/'.self::ABRE.'(\d+)'.self::FECHA.'/',
                $run['texto'],
                -1,
                PREG_SPLIT_DELIM_CAPTURE,
            ) ?: [];

            foreach ($pedacos as $posicao => $pedaco) {
                if ($posicao % 2 === 1) {
                    $campo = $campos[(int) $pedaco] ?? null;

                    if ($campo === null) {
                        continue;
                    }

                    // O valor de um campo de dado herda a formatação do
                    // trecho em que ele está; os de preencher não têm.
                    $partes[] = $campo['tipo'] === 'texto'
                        ? $campo + ['negrito' => $run['negrito'], 'italico' => $run['italico']]
                        : $campo;

                    continue;
                }

                foreach (self::porLinha($pedaco) as $parte) {
                    $partes[] = $parte['tipo'] === 'texto'
                        ? $parte + ['negrito' => $run['negrito'], 'italico' => $run['italico']]
                        : $parte;
                }
            }
        }

        return $partes;
    }

    /**
     * Texto cru em trechos e quebras de linha.
     *
     * @return array<int, array{tipo: string, texto?: string}>
     */
    protected static function porLinha(string $texto): array
    {
        if ($texto === '') {
            return [];
        }

        $partes = [];
        $linhas = explode("\n", $texto);

        foreach ($linhas as $indice => $linha) {
            if ($indice > 0) {
                $partes[] = ['tipo' => 'quebra'];
            }

            if ($linha !== '') {
                $partes[] = ['tipo' => 'texto', 'texto' => $linha];
            }
        }

        return $partes;
    }

    /**
     * O que um campo é, sem decidir como ele se desenha.
     *
     * @param  array<string, mixed>  $contexto
     * @return array{tipo: string, texto?: string, rotulo?: string}
     */
    protected static function descritor(string $campo, string $rotulo, array $contexto): array
    {
        return match ($campo) {
            'linha', 'assinatura', 'caixa' => ['tipo' => $campo, 'rotulo' => $rotulo],
            'espaco', 'lista_de_alunos' => ['tipo' => $campo],
            default => ['tipo' => 'texto', 'texto' => self::valor($campo, $contexto)],
        };
    }

    /** @param  array<string, mixed>  $contexto */
    protected static function campoEmHtml(string $campo, string $rotulo, array $contexto): string
    {
        return match ($campo) {
            'linha' => self::linha($rotulo),
            'assinatura' => self::assinatura($rotulo),
            'caixa' => self::caixa($rotulo),
            'espaco' => '<div class="espaco-para-escrever"></div>',
            'lista_de_alunos' => (string) ($contexto['lista_de_alunos'] ?? ''),
            default => e(self::valor($campo, $contexto)),
        };
    }

    /** @param  array<string, mixed>  $contexto */
    protected static function valor(string $campo, array $contexto): string
    {
        $valor = $contexto[$campo] ?? null;

        if ($valor instanceof Carbon) {
            return $valor->translatedFormat('d/m/Y');
        }

        /*
         * Campo de dado vazio vira um traço, e não uma lacuna: no papel
         * impresso, espaço em branco onde deveria haver um nome não se
         * distingue de erro de impressão.
         */
        return $valor === null || $valor === '' ? '—' : (string) $valor;
    }

    /**
     * A linha de preencher é feita de espaços rígidos com borda embaixo.
     *
     * O caminho natural — `display: inline-block` com `min-width` — não
     * serve: o mPDF ignora a largura de um elemento em linha e desenha um
     * tracinho de dois milímetros. A largura precisa vir de conteúdo de
     * verdade, e espaço rígido é o único conteúdo que não se vê.
     */
    protected static function linha(string $rotulo): string
    {
        $legenda = $rotulo === ''
            ? ''
            : ' <span class="rotulo-do-campo">('.e($rotulo).')</span>';

        return '<span class="campo-linha">'.str_repeat('&nbsp;', 26).'</span>'.$legenda;
    }

    /**
     * Assinatura: linha centrada com o rótulo embaixo, como num
     * formulário de papel. É uma tabela porque o mPDF só põe o rótulo sob
     * a linha, e centrado, dentro de uma célula.
     */
    protected static function assinatura(string $rotulo): string
    {
        return '<table class="campo-assinatura"><tr><td>'
            .($rotulo === '' ? '&nbsp;' : e($rotulo))
            .'</td></tr></table>';
    }

    /**
     * O quadradinho é o caractere ☐, e não um elemento com borda: em
     * linha, o mPDF ignora largura e altura e desenha uma barrinha.
     */
    protected static function caixa(string $rotulo): string
    {
        $legenda = $rotulo === '' ? '' : ' '.e($rotulo);

        return '<span class="campo-caixa">&#9744;</span>'.$legenda;
    }

    /**
     * O contexto de um documento, com ou sem aluno.
     *
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    public static function contexto(
        ?Aluno $aluno,
        ?int $numero,
        string $turma,
        int|string $periodo,
        string $periodoLetivo,
        string $curso,
        string $eixo,
        string $instituicao,
        array $extra = [],
    ): array {
        return array_merge([
            'aluno.nome' => $aluno?->nome,
            'aluno.ra' => $aluno?->ra,
            'aluno.numero' => $numero,
            'turma.nome' => $turma,
            'turma.periodo' => $periodo,
            'turma.periodo_letivo' => $periodoLetivo,
            'curso.nome' => $curso,
            'eixo.nome' => $eixo,
            'instituicao' => $instituicao,
            'data' => now()->translatedFormat('j \d\e F \d\e Y'),
            'data_curta' => now()->format('d/m/Y'),
        ], $extra);
    }
}
