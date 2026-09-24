{{--
    Estilos da folha de prova. Servem à pré-visualização na tela e ao PDF,
    então ficam em CSS simples: o mPDF não entende flex nem grid, e o
    que ele desenha precisa ser o mesmo que o professor viu antes.

    Os valores tipográficos vêm do `LayoutDaFolha`, que sob a ABNT impõe
    A4, margens 3/2/2/3 cm, corpo 12 pt, espaçamento 1,5 e justificado.
--}}
@php
    $tamanho = $layout->tamanho;
    $secundario = $layout->tamanhoSecundario();
    $abnt = $layout->norma->fixaAFormatacao();
@endphp
<style>
    @if (! $paraImpressao)
        {{--
            Só para a tela — inclusive para quem imprima a
            pré-visualização direto do navegador. No PDF o papel e as
            margens vêm do construtor do mPDF: a regra `@page` confunde o
            cálculo de altura dele e o documento entra em laço, gerando
            milhares de páginas.
        --}}
        @page {
            size: A4 portrait;
            margin: {{ $layout->margemCss() }};
        }
    @endif

    body {
        {{-- Sem `{!! !!}` o Blade escapa as aspas e o navegador vê
             `font-family: &#039;Times New Roman&#039;`, que é CSS
             inválido: a primeira família da lista é descartada em
             silêncio. O valor vem de `LayoutDaFolha`, que só devolve uma
             das duas famílias da norma. --}}
        font-family: {!! $layout->familiaCss() !!};
        font-size: {{ $tamanho }}pt;
        line-height: {{ $layout->espacamento }};
        color: #111;
        margin: 0;
        text-align: justify;
    }

    /* Número da página no alto à direita, como pede a NBR 14724. */
    .paginacao {
        position: fixed;
        top: -10mm; right: 0;
        font-size: {{ $secundario }}pt; color: #444;
    }
    .paginacao:after { content: counter(page); }

    /*
        Cabeçalho: logo à esquerda, identificação ao centro, logo à
        direita. É uma tabela porque nem o navegador nem o mPDF põem os
        três blocos lado a lado de outro jeito que sirva aos dois — e as
        células dos extremos têm largura fixa para o centro não
        escorregar quando falta uma das logos.
    */
    .cabecalho { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
    .marca { width: 100%; border-collapse: collapse; }
    .marca .logo { width: 24mm; vertical-align: middle; }
    .marca .logo.esquerda { text-align: left; }
    .marca .logo.direita { text-align: right; }
    .marca .titulo { text-align: center; vertical-align: middle; }

    .instituicao { font-size: {{ $tamanho + 1 }}pt; font-weight: bold; }
    .avaliacao { font-size: {{ $tamanho }}pt; }
    .meta { font-size: {{ $secundario }}pt; color: #444; }

    /* A linha para preencher é a borda de baixo de uma célula. */
    .identificacao {
        width: 100%; border-collapse: collapse;
        border: 1px solid #111; margin-bottom: 10px;
        font-size: {{ $secundario }}pt; text-align: left;
    }
    .identificacao td { padding: 3px 6px; }
    {{-- Largura em milímetros, não em `1%`: o mPDF não faz a coluna
         encolher até o conteúdo e espreme o rótulo letra por letra. --}}
    .identificacao .rotulo { font-weight: bold; width: 24mm; }
    .identificacao .risco { border-bottom: 1px solid #555; }

    .instrucoes {
        border: 1px solid #999; background: #f6f6f6;
        padding: 6px 8px; margin-bottom: 10px;
        font-size: {{ $secundario }}pt; line-height: {{ $layout->espacamentoSecundario() }};
    }

    /* Duas colunas de texto, como numa prova impressa. */
    .corpo { column-count: {{ $colunas }}; column-gap: 10mm; column-rule: 1px solid #ddd; }

    .disciplina {
        break-inside: avoid; page-break-inside: avoid;
        margin: 0 0 6px 0; padding: 3px 6px;
        background: #eee; border-left: 3px solid #111;
        font-weight: bold; font-size: {{ $tamanho }}pt; text-align: left;
    }

    .questao { break-inside: avoid; page-break-inside: avoid; margin-bottom: 10px; }
    .questao .numero { font-weight: bold; }
    .questao .enunciado { margin: 2px 0 4px 0; text-align: justify; }
    .questao .peso { font-size: {{ $secundario }}pt; color: #666; font-weight: normal; }

    /*
        Trecho de código segue o tratamento que a norma dá à citação
        longa: corpo menor e espaçamento simples. O recuo de 4 cm só
        cabe em coluna única — numa coluna de ~7,5 cm ele não deixaria
        texto nenhum.
    */
    .bloco-codigo {
        font-family: 'DejaVu Sans Mono', 'Courier New', monospace;
        font-size: {{ $secundario }}pt;
        line-height: {{ $layout->espacamentoSecundario() }};
        background: #f4f4f4; border: 1px solid #ccc;
        padding: 4px 6px; margin: 4px 0;
        @if ($abnt && $colunas === 1) margin-left: 40mm; @endif
        text-align: left;
    }
    .bloco-codigo .linha { margin: 0; word-wrap: break-word; }
    .bloco-codigo .linguagem {
        font-size: {{ max(7, $secundario - 1) }}pt;
        color: #555; text-transform: uppercase; margin-bottom: 2px;
    }
    .bloco-imagem { margin: 4px 0; text-align: center; }
    .bloco-imagem .legenda {
        font-size: {{ $secundario }}pt; color: #555;
        line-height: {{ $layout->espacamentoSecundario() }};
    }

    /* Tabela, e não lista: o mPDF não recua `<li>` de forma confiável. */
    .alternativas { width: 100%; border-collapse: collapse; }
    .alternativas td { padding: 1px 0; vertical-align: top; text-align: justify; }
    .alternativas .letra { font-weight: bold; width: 7mm; }
    .alternativas .correta td { background: #d8f3dc; }

    .gabarito { margin-top: 12px; border-top: 2px solid #111; padding-top: 8px; }
    .gabarito table { border-collapse: collapse; font-size: {{ $secundario }}pt; }
    .gabarito td { border: 1px solid #333; padding: 2px 6px; text-align: center; }

    .rodape {
        margin-top: 12px; border-top: 1px solid #999; padding-top: 5px;
        font-size: {{ $secundario }}pt; color: #555; text-align: center;
        line-height: {{ $layout->espacamentoSecundario() }};
    }
</style>
