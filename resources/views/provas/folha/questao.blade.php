{{-- Uma questão da folha: número, enunciado, blocos e alternativas. --}}
<div class="questao">
    <div class="enunciado">
        <span class="numero">{{ $questao->numero }}.</span>
        @if ($mostrarPesos)
            <span class="peso">(peso {{ number_format((float) $questao->peso, 2, ',', '.') }})</span>
        @endif
        {{ $questao->enunciado_snapshot }}
    </div>

    @foreach ($questao->blocos() as $bloco)
        @if ($bloco['tipo'] === 'codigo')
            <div class="bloco-codigo"><span class="linguagem">{{ $bloco['linguagem'] ?? 'código' }}</span>{{ trim((string) $bloco['conteudo']) }}</div>
        @elseif ($bloco['tipo'] === 'imagem' && ! empty($bloco['caminho']))
            <div class="bloco-imagem">
                <img src="{{ $origemDaImagem($bloco['caminho']) }}" alt="{{ $bloco['legenda'] ?: 'Imagem' }}">
                @if (! empty($bloco['legenda']))
                    <div class="legenda">{{ $bloco['legenda'] }}</div>
                @endif
            </div>
        @else
            <div class="enunciado">{{ $bloco['conteudo'] }}</div>
        @endif
    @endforeach

    <ul class="alternativas">
        @foreach ($questao->alternativas_snapshot as $alternativa)
            <li @class(['correta' => $comGabarito && $alternativa['correta']])>
                <span class="letra">{{ $alternativa['letra'] }})</span> {{ $alternativa['texto'] }}
            </li>
        @endforeach
    </ul>
</div>
