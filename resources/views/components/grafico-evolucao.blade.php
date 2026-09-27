{{--
    Linhas de evolução: uma por disciplina, eixo horizontal em bimestres.

    A cor de cada série vem em dois tons e o CSS escolhe conforme o tema
    — o `<svg>` é o mesmo nos dois. Nenhum texto usa a cor da série: a
    identidade vem do traço e do rótulo ao lado dele, nunca da cor da
    letra.
--}}
@php
    $eixoEsquerdo = 44.0;
    $eixoDireito = $largura - 132.0;
    $topo = 16.0;
    $base = $altura - 34.0;
    $linhasDeGrade = [0, 2, 4, 6, 8, 10];
@endphp

<figure class="overflow-x-auto">
    <svg viewBox="0 0 {{ $largura }} {{ $altura }}" width="100%" height="{{ $altura }}"
         role="img" class="min-w-[36rem]"
         aria-label="Evolução da nota média por disciplina ao longo dos bimestres">
        <style>
            .grade { stroke: #e1e0d9; stroke-width: 1; }
            .eixo { stroke: #c3c2b7; stroke-width: 1; }
            .rotulo-eixo { fill: #898781; font-size: 11px; }
            .rotulo-serie { fill: #52514e; font-size: 11px; }
            .valor { fill: #0b0b0b; font-size: 11px; font-weight: 600; }
            .anel { stroke: #fcfcfb; stroke-width: 2; }
            /* Pela classe no `<html>`, como o resto do tema. */
            .dark .grade { stroke: #2c2c2a; }
            .dark .eixo { stroke: #383835; }
            .dark .rotulo-serie { fill: #c3c2b7; }
            .dark .valor { fill: #ffffff; }
            .dark .anel { stroke: #1a1a19; }
            .dark .serie { stroke: var(--escura); }
            .dark .ponto { fill: var(--escura); }
            .serie { stroke: var(--clara); fill: none; stroke-width: 2; stroke-linejoin: round; stroke-linecap: round; }
            .ponto { fill: var(--clara); }
        </style>

        {{-- Grade recessiva: 1px, sólida, nunca tracejada. --}}
        @foreach ($linhasDeGrade as $nota)
            @php $y = $topo + ($base - $topo) * (1 - $nota / 10); @endphp
            <line class="grade" x1="{{ $eixoEsquerdo }}" y1="{{ $y }}" x2="{{ $eixoDireito }}" y2="{{ $y }}"/>
            <text class="rotulo-eixo" x="{{ $eixoEsquerdo - 8 }}" y="{{ $y + 4 }}" text-anchor="end">{{ $nota }}</text>
        @endforeach

        <line class="eixo" x1="{{ $eixoEsquerdo }}" y1="{{ $base }}" x2="{{ $eixoDireito }}" y2="{{ $base }}"/>

        @foreach ($grafico->bimestres as $indice => $rotulo)
            @php
                $passo = count($grafico->bimestres) > 1 ? ($eixoDireito - $eixoEsquerdo) / (count($grafico->bimestres) - 1) : 0;
                $x = $eixoEsquerdo + $indice * $passo;
            @endphp
            <text class="rotulo-eixo" x="{{ $x }}" y="{{ $base + 18 }}" text-anchor="middle">{{ $rotulo }}</text>
        @endforeach

        @foreach ($coordenadas as $serie)
            @continue($serie['pontos'] === [])
            @php
                $estilo = '--clara: '.$serie['cor'].'; --escura: '.$serie['corEscura'].';';
                $ultimo = end($serie['pontos']);
            @endphp

            <g style="{{ $estilo }}">
                @if (count($serie['pontos']) > 1)
                    <polyline class="serie"
                              points="{{ collect($serie['pontos'])->map(fn ($p) => $p['x'].','.$p['y'])->implode(' ') }}"/>
                @endif

                {{-- Anel na cor da superfície: o ponto continua legível
                     onde duas linhas se cruzam. --}}
                @foreach ($serie['pontos'] as $ponto)
                    <circle class="ponto anel" cx="{{ $ponto['x'] }}" cy="{{ $ponto['y'] }}" r="4">
                        <title>{{ $serie['nome'] }} — {{ $grafico->bimestres[$loop->index] ?? '' }}: {{ $ponto['rotulo'] }}</title>
                    </circle>
                @endforeach

            </g>
        @endforeach

        {{--
            Os nomes por último, já afastados entre si: quando duas
            linhas terminam próximas, os rótulos colidiriam. O fio liga
            cada nome à sua linha, para afastá-los não os soltar.
        --}}
        @foreach ($rotulos as $rotulo)
            <g style="--clara: {{ $rotulo['cor'] }}; --escura: {{ $rotulo['corEscura'] }};">
                <polyline class="serie" fill="none"
                          points="{{ $rotulo['x'] - 18 }},{{ $rotulo['yDoPonto'] }} {{ $rotulo['x'] - 8 }},{{ $rotulo['yDoPonto'] }} {{ $rotulo['x'] - 4 }},{{ $rotulo['y'] }} {{ $rotulo['x'] - 2 }},{{ $rotulo['y'] }}"/>
                <text class="rotulo-serie" x="{{ $rotulo['x'] }}" y="{{ $rotulo['y'] + 4 }}">
                    <tspan class="valor">{{ $rotulo['rotulo'] }}</tspan>
                    {{ Str::limit($rotulo['nome'], 16) }}
                </text>
            </g>
        @endforeach
    </svg>
</figure>
