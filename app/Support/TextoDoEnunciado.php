<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

/**
 * Negrito e itálico no enunciado, guardados como marcas no próprio
 * texto: `**negrito**`, `*itálico*` e `***os dois***`.
 *
 * A alternativa seria guardar HTML, mas então cada destino teria de
 * lidar com ele: a tela precisaria higienizar — o enunciado vem do
 * professor, é entrada de usuário —, o mPDF aceita só um subconjunto e o
 * Word não aceita HTML nenhum, já que é montado trecho a trecho. Com
 * marcas, o que se guarda continua sendo texto, sem superfície de XSS, e
 * cada destino recebe o que sabe desenhar:
 *
 * - `paraHtml()` escapa tudo e devolve `<strong>` / `<em>`;
 * - `segmentos()` alimenta o gerador do .docx, que precisa de trechos
 *   com `bold` e `italic` em vez de marcação;
 * - `semMarcas()` dá o texto limpo para onde formatação não cabe.
 */
final class TextoDoEnunciado
{
    public const MARCA_NEGRITO = '**';

    public const MARCA_ITALICO = '*';

    /**
     * As três marcas, da mais longa para a mais curta — `**` tem de
     * casar antes de `*`, senão a primeira das duas estrelas abre um
     * itálico que nunca fecha.
     *
     * `(?!\s)` e `(?<!\s)` exigem que a marca encoste no texto. É o que
     * impede `3 * 4 * 5` de virar itálico, e numa prova de lógica isso
     * acontece.
     */
    protected const PADRAO = '/\*\*\*(?!\s)(.+?)(?<!\s)\*\*\*|\*\*(?!\s)(.+?)(?<!\s)\*\*|\*(?!\s)(.+?)(?<!\s)\*/s';

    /**
     * O texto quebrado em trechos, cada um sabendo se é negrito,
     * itálico ou os dois.
     *
     * @return array<int, array{texto: string, negrito: bool, italico: bool}>
     */
    public static function segmentos(string $texto): array
    {
        preg_match_all(self::PADRAO, $texto, $achados, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $trechos = [];
        $posicao = 0;

        foreach ($achados as $achado) {
            [$inteiro, $inicio] = $achado[0];

            if ($inicio > $posicao) {
                $trechos[] = self::trecho(substr($texto, $posicao, $inicio - $posicao));
            }

            // Qual das três alternativas casou diz a formatação.
            $trechos[] = match (true) {
                self::casou($achado, 1) => self::trecho($achado[1][0], negrito: true, italico: true),
                self::casou($achado, 2) => self::trecho($achado[2][0], negrito: true),
                default => self::trecho($achado[3][0], italico: true),
            };

            $posicao = $inicio + strlen($inteiro);
        }

        if ($posicao < strlen($texto)) {
            $trechos[] = self::trecho(substr($texto, $posicao));
        }

        return array_values(array_filter($trechos, fn (array $trecho) => $trecho['texto'] !== ''));
    }

    /**
     * Grupo que não participou do casamento vem com deslocamento -1 — ou
     * nem vem, quando está no fim do padrão.
     *
     * @param  array<int, array{0: string, 1: int}>  $achado
     */
    protected static function casou(array $achado, int $grupo): bool
    {
        return ($achado[$grupo][1] ?? -1) !== -1;
    }

    /** @return array{texto: string, negrito: bool, italico: bool} */
    protected static function trecho(string $texto, bool $negrito = false, bool $italico = false): array
    {
        return ['texto' => $texto, 'negrito' => $negrito, 'italico' => $italico];
    }

    /** HTML seguro: tudo escapado, só as marcas viram tags. */
    public static function paraHtml(string $texto): HtmlString
    {
        $html = '';

        foreach (self::segmentos($texto) as $trecho) {
            $pedaco = e($trecho['texto']);

            if ($trecho['italico']) {
                $pedaco = "<em>{$pedaco}</em>";
            }

            if ($trecho['negrito']) {
                $pedaco = "<strong>{$pedaco}</strong>";
            }

            $html .= $pedaco;
        }

        return new HtmlString($html);
    }

    /** O texto sem as marcas, para onde não há como formatar. */
    public static function semMarcas(string $texto): string
    {
        return implode('', array_column(self::segmentos($texto), 'texto'));
    }

    public static function temFormatacao(string $texto): bool
    {
        return collect(self::segmentos($texto))
            ->contains(fn (array $trecho) => $trecho['negrito'] || $trecho['italico']);
    }
}
