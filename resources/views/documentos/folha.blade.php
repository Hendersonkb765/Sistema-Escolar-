{{--
    O PDF dos documentos do aluno.

    Cada via repete o cabeçalho. Parece desperdício numa folha só, mas é
    o contrário: no documento individual a folha é recortada e cada
    pedaço vai para uma família diferente. Uma via sem o nome da escola é
    um papel que ninguém sabe de onde veio.

    A altura das vias é fixa quando cabem duas ou três por página. Deixar
    o mPDF decidir faria a segunda via começar no fim de uma folha e
    terminar na outra — e aí o corte com a tesoura parte o documento ao
    meio.
--}}
@php
    // A4 (297mm) menos as margens do mPDF (14 em cima, 12 embaixo).
    $alturaUtil = 297 - 14 - 12;
    $alturaDaVia = $porPagina > 1 ? ($alturaUtil / $porPagina) : null;
    $compacto = $porPagina > 1;
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>{{ $modelo->nome }} — {{ $turma->nome }}</title>
    <style>
        body {
            font-family: Arial, 'DejaVu Sans', Helvetica, sans-serif;
            font-size: {{ $compacto ? 9 : 11 }}pt;
            line-height: {{ $compacto ? 1.25 : 1.4 }};
            color: #111;
        }

        /*
            A via não leva altura fixa. O mPDF soma a altura declarada ao
            que o conteúdo já ocupa em vez de encaixá-lo dentro dela, e
            três vias de 90mm passavam a ocupar 280mm — a terceira caía
            na folha seguinte. Quem separa as folhas é o <pagebreak/> a
            cada N vias, e o limite de altura é o aviso na tela de
            montagem do modelo.
        */
        .via { }

        /* Linha de corte entre as vias da mesma folha. */
        .corte {
            border-top: 1px dashed #999;
            margin: 2mm 0;
            font-size: 6pt;
            color: #999;
            text-align: right;
        }

        .cabecalho { border-bottom: 1.5px solid #111; padding-bottom: 3px; margin-bottom: {{ $compacto ? 4 : 8 }}px; }
        .marca { width: 100%; border-collapse: collapse; }
        .marca .logo { width: {{ $compacto ? 14 : 22 }}mm; vertical-align: middle; }
        .marca .logo.direita { text-align: right; }
        .marca .titulo { text-align: center; vertical-align: middle; }
        .instituicao { font-size: {{ $compacto ? 9 : 12 }}pt; font-weight: bold; line-height: 1.15; }
        .documento { font-size: {{ $compacto ? 8 : 10 }}pt; }

        .corpo { text-align: justify; }

        /* Linha para preencher à mão: uma borda de baixo com largura fixa. */
        /* Espaço rígido com borda embaixo: ver CamposDoDocumento::linha(). */
        .campo-linha { border-bottom: 1px solid #333; }
        /*
            O rótulo em linha não muda de tamanho, só de cor. O mPDF não
            restaura o tamanho da fonte ao fechar um trecho menor dentro
            de um parágrafo: o texto seguinte sai maior que o resto até o
            fim da frase. Na célula da assinatura, que é bloco, o tamanho
            menor funciona.
        */
        .rotulo-do-campo { color: #555; }
        .campo-caixa { font-size: {{ $compacto ? 11 : 13 }}pt; }

        .campo-assinatura {
            width: 72mm;
            border-collapse: collapse;
            margin: {{ $compacto ? 3 : 6 }}mm 0 1mm 0;
        }
        .campo-assinatura td {
            border-top: 1px solid #333;
            text-align: center;
            padding-top: 1px;
            font-size: {{ $compacto ? 7 : 8 }}pt;
            color: #555;
        }
        .espaco-para-escrever {
            height: {{ $compacto ? 12 : 22 }}mm;
            border: 1px solid #bbb;
            margin: 2mm 0;
        }

        .alunos { width: 100%; border-collapse: collapse; font-size: {{ $compacto ? 8 : 10 }}pt; margin: 3mm 0; }
        .alunos th {
            text-align: left; border-bottom: 1px solid #111; padding: 3px 5px;
            font-size: {{ $compacto ? 7 : 8 }}pt; text-transform: uppercase;
        }
        .alunos td { padding: 4px 5px; border-bottom: 1px solid #ddd; }
        .alunos .numero { width: 10mm; text-align: right; }
        .alunos .ra { width: 26mm; }
        .alunos .assinar { width: 60mm; }
    </style>
</head>
<body>
@foreach ($vias as $indice => $via)
    @if ($indice > 0)
        @if ($indice % $porPagina === 0)
            <pagebreak/>
        @else
            <div class="corte">✂ - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -</div>
        @endif
    @endif

    <div class="via">
        <div class="cabecalho">
            <table class="marca">
                <tr>
                    <td class="logo esquerda">
                        @if ($logos['esquerda'])
                            <img src="{{ $logos['esquerda'] }}" alt="" style="height: {{ $compacto ? 9 : 14 }}mm;">
                        @endif
                    </td>
                    <td class="titulo">
                        <div class="instituicao">{{ $instituicao }}</div>
                        <div class="documento">{{ $modelo->nome }}</div>
                    </td>
                    <td class="logo direita">
                        @if ($logos['direita'])
                            <img src="{{ $logos['direita'] }}" alt="" style="height: {{ $compacto ? 9 : 14 }}mm;">
                        @endif
                    </td>
                </tr>
            </table>
        </div>

        <div class="corpo">{!! $via !!}</div>
    </div>
@endforeach
</body>
</html>
