{{-- Uma questão da folha: número, enunciado, blocos e alternativas. --}}
@use('App\Support\TextoDoEnunciado')
@use('App\Support\TrechoDeCodigo')

<div class="questao">
    <div class="enunciado">
        <span class="numero">{{ $questao->numero }}.</span>
        @if ($mostrarPesos)
            <span class="peso">(peso {{ number_format((float) $questao->peso, 2, ',', '.') }})</span>
        @endif
        {!! TextoDoEnunciado::paraHtml((string) $questao->enunciado_snapshot) !!}
    </div>

    @foreach ($questao->blocos() as $bloco)
        @if ($bloco['tipo'] === 'codigo')
            {{--
                Uma linha por elemento: o mPDF não honra `white-space:
                pre-wrap` e juntaria tudo numa linha só.
            --}}
            <div class="bloco-codigo">
                <div class="linguagem">{{ $bloco['linguagem'] ?? 'código' }}</div>
                @foreach (TrechoDeCodigo::linhas((string) $bloco['conteudo']) as $linha)
                    <div class="linha">{!! $linha !!}</div>
                @endforeach
            </div>
        @elseif ($bloco['tipo'] === 'imagem' && ! empty($bloco['caminho']))
            <div class="bloco-imagem">
                <img src="{{ $origemDaImagem($bloco['caminho']) }}" alt="{{ $bloco['legenda'] ?: 'Imagem' }}"
                     style="max-width: 100%;">
                @if (! empty($bloco['legenda']))
                    <div class="legenda">{{ $bloco['legenda'] }}</div>
                @endif
            </div>
        @else
            <div class="enunciado">{!! TextoDoEnunciado::paraHtml((string) $bloco['conteudo']) !!}</div>
        @endif
    @endforeach

    <table class="alternativas">
        @foreach ($questao->alternativas_snapshot as $alternativa)
            <tr @class(['correta' => $comGabarito && $alternativa['correta']])>
                <td class="letra">{{ $alternativa['letra'] }})</td>
                <td>{{ $alternativa['texto'] }}</td>
            </tr>
        @endforeach
    </table>
</div>
