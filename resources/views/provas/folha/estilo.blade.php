{{--
    Estilos da folha de prova. Servem à pré-visualização na tela e ao PDF,
    então ficam em CSS simples: o dompdf não entende flex nem grid, e o
    que ele desenha precisa ser o mesmo que o professor viu antes.
--}}
<style>
    @page { margin: 18mm 14mm 16mm 14mm; }

    body {
        font-family: {{ $fonte === 'serif' ? "'DejaVu Serif', Georgia, serif" : "'DejaVu Sans', Arial, sans-serif" }};
        font-size: {{ $tamanho }}pt;
        line-height: 1.45;
        color: #111;
        margin: 0;
    }

    .cabecalho { border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 10px; }
    .cabecalho table { width: 100%; border-collapse: collapse; }
    .cabecalho .logo { width: 70px; vertical-align: middle; }
    .cabecalho .logo img { max-width: 64px; max-height: 64px; }
    .instituicao { font-size: {{ $tamanho + 2 }}pt; font-weight: bold; }
    .avaliacao { font-size: {{ $tamanho + 1 }}pt; }
    .meta { font-size: {{ $tamanho - 1 }}pt; color: #444; }

    .identificacao { border: 1px solid #111; padding: 6px 8px; margin-bottom: 10px; font-size: {{ $tamanho - 1 }}pt; }
    .identificacao .linha { margin: 4px 0; }
    .identificacao .rotulo { font-weight: bold; }
    .preencher { border-bottom: 1px solid #555; display: inline-block; min-width: 55%; }

    .instrucoes {
        border: 1px solid #999; background: #f6f6f6;
        padding: 6px 8px; margin-bottom: 10px; font-size: {{ $tamanho - 1 }}pt;
    }

    /* Duas colunas de texto, como numa prova impressa. */
    .corpo { column-count: {{ $colunas }}; column-gap: 10mm; column-rule: 1px solid #ddd; }

    .disciplina {
        break-inside: avoid; page-break-inside: avoid;
        margin: 0 0 6px 0; padding: 3px 6px;
        background: #eee; border-left: 3px solid #111;
        font-weight: bold; font-size: {{ $tamanho }}pt;
    }

    .questao { break-inside: avoid; page-break-inside: avoid; margin-bottom: 10px; }
    .questao .numero { font-weight: bold; }
    .questao .enunciado { margin: 2px 0 4px 0; text-align: justify; }
    .questao .peso { font-size: {{ $tamanho - 2 }}pt; color: #666; font-weight: normal; }

    .bloco-codigo {
        font-family: 'DejaVu Sans Mono', 'Courier New', monospace;
        font-size: {{ $tamanho - 2 }}pt; line-height: 1.3;
        background: #f4f4f4; border: 1px solid #ccc;
        padding: 4px 6px; margin: 4px 0;
        white-space: pre-wrap; word-wrap: break-word;
    }
    .bloco-codigo .linguagem {
        display: block; font-size: {{ $tamanho - 3 }}pt;
        color: #555; text-transform: uppercase; margin-bottom: 2px;
    }
    .bloco-imagem { margin: 4px 0; text-align: center; }
    .bloco-imagem img { max-width: 100%; }
    .bloco-imagem .legenda { font-size: {{ $tamanho - 2 }}pt; color: #555; }

    .alternativas { margin: 0; padding: 0; list-style: none; }
    .alternativas li { margin: 1px 0; text-align: justify; }
    .alternativas .letra { font-weight: bold; }
    .alternativas .correta { background: #d8f3dc; }

    .gabarito { margin-top: 12px; border-top: 2px solid #111; padding-top: 8px; }
    .gabarito table { border-collapse: collapse; font-size: {{ $tamanho - 1 }}pt; }
    .gabarito td { border: 1px solid #333; padding: 2px 6px; text-align: center; }

    .rodape {
        margin-top: 12px; border-top: 1px solid #999; padding-top: 5px;
        font-size: {{ $tamanho - 2 }}pt; color: #555; text-align: center;
    }
</style>
