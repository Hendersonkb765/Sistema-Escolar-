{{--
    Um painel por disciplina: quatro colunas, uma por bimestre.

    Coluna, e não linha, porque bimestre é período discreto — a linha
    afirmaria o caminho entre março e junho, que não existe. Bimestre sem
    avaliação não tem coluna, e diz isso embaixo: ausência de prova não é
    queda de desempenho.

    Uma cor só. A identidade da disciplina está no título do painel, não
    na cor, e o que o professor compara é cada coluna contra a linha do
    critério — que é posição, e não depende de enxergar cor nenhuma.
--}}
@php
    $base = $grafico->base($altura);
    $yDoCriterio = $grafico->linhaDoCriterio($altura);
@endphp

<div class="painel-evolucao grid gap-x-6 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($grafico->series as $serie)
        @php $colunas = $grafico->colunas($serie['pontos'], $largura, $altura); @endphp

        <figure class="min-w-0">
            <figcaption class="truncate text-sm font-medium text-slate-700 dark:text-slate-200"
                        title="{{ $serie['nome'] }}">
                {{ $serie['nome'] }}
            </figcaption>

            <svg viewBox="0 0 {{ $largura }} {{ $altura }}" width="100%" height="{{ $altura }}"
                 role="img" class="mt-1"
                 aria-label="Nota média de {{ $serie['nome'] }} por bimestre: {{ collect($colunas)->map(fn ($c) => $c['bimestre'].' '.($c['valor'] === null ? 'sem avaliação' : $c['rotulo']))->join('; ') }}">

                {{-- Grade recessiva: só 5 e 10, para não listrar o painel. --}}
                @foreach ([5, 10] as $nota)
                    @php $y = $base - ($base - 18.0) * ($nota / 10); @endphp
                    <line class="grade" x1="24" y1="{{ $y }}" x2="{{ $largura - 26 }}" y2="{{ $y }}"/>
                    <text class="rotulo-eixo" x="20" y="{{ $y + 3 }}" text-anchor="end">{{ $nota }}</text>
                @endforeach

                {{-- A linha do critério leva o número: tracejado sem rótulo
                     é um enfeite que o leitor tem de adivinhar. --}}
                @if ($yDoCriterio !== null)
                    <line class="criterio" x1="24" y1="{{ $yDoCriterio }}"
                          x2="{{ $largura - 26 }}" y2="{{ $yDoCriterio }}"/>
                    <text class="rotulo-eixo" x="{{ $largura - 22 }}" y="{{ $yDoCriterio + 3 }}">
                        {{ number_format($grafico->criterio, 1, ',', '.') }}
                    </text>
                @endif

                <line class="eixo" x1="24" y1="{{ $base }}" x2="{{ $largura - 26 }}" y2="{{ $base }}"/>

                @foreach ($colunas as $coluna)
                    @if ($coluna['valor'] === null)
                        <text class="ausente" x="{{ $coluna['x'] + $coluna['largura'] / 2 }}"
                              y="{{ $base - 4 }}" text-anchor="middle">—</text>
                    @else
                        {{-- Topo arredondado de 4px, ancorado na linha de base. --}}
                        <rect class="coluna" rx="4" ry="4"
                              x="{{ $coluna['x'] }}" y="{{ $coluna['y'] }}"
                              width="{{ $coluna['largura'] }}"
                              height="{{ max($coluna['altura'], 2) }}">
                            <title>{{ $serie['nome'] }} — {{ $coluna['bimestre'] }}: {{ $coluna['rotulo'] }}</title>
                        </rect>
                        <text class="valor" x="{{ $coluna['x'] + $coluna['largura'] / 2 }}"
                              y="{{ $coluna['y'] - 4 }}" text-anchor="middle">{{ $coluna['rotulo'] }}</text>
                    @endif

                    <text class="rotulo-eixo" x="{{ $coluna['x'] + $coluna['largura'] / 2 }}"
                          y="{{ $altura - 7 }}" text-anchor="middle">{{ $loop->index + 1 }}º</text>
                @endforeach
            </svg>
        </figure>
    @endforeach
</div>
