@use('App\Support\TextoDoEnunciado')

@props([
    'questao',
    /** true/false quando se olha um aluno; null quando se olha a turma. */
    'acertou' => null,
    'aluno' => null,
    /** A letra que o aluno marcou, quando a planilha trouxe. */
    'marcada' => null,
    /**
     * As alternativas já contadas (`escolhas()`): letra, texto, correta,
     * total e percentual. Sem elas, o componente desenha só o snapshot.
     */
    'alternativas' => null,
    /** As erradas que mais atraíram, para destacar a pista. */
    'distratores' => [],
])

@php
    // Sem contagem, as alternativas saem do snapshot com total zero: o
    // mesmo formato, uma leitura só aqui dentro.
    $lista = $alternativas ?? collect($questao->alternativas_snapshot ?? [])
        ->map(fn (array $alternativa) => [
            'letra' => $alternativa['letra'],
            'texto' => $alternativa['texto'] ?? '',
            'correta' => (bool) ($alternativa['correta'] ?? false),
            'total' => 0,
            'percentual' => 0.0,
        ])
        ->all();

    $comContagem = collect($lista)->sum('total') > 0;
    $letrasDistratoras = array_map(fn (array $d) => mb_strtoupper((string) $d['letra']), $distratores);
    $letraMarcada = $marcada === null ? null : mb_strtoupper((string) $marcada);
@endphp

{{--
    A questão como o aluno a viu: enunciado, blocos de apoio e as
    alternativas, com a correta assinalada.

    Sai do snapshot da prova, e não da questão original — uma análise de
    prova antiga tem de mostrar o que estava no papel naquele dia, mesmo
    que a questão tenha sido reescrita depois.

    Quando a planilha do leitor trouxe a letra marcada, cada alternativa
    traz também quantos a escolheram: é o que distingue "a turma errou"
    de "dois terços caíram na C". Sem essa informação o componente não
    inventa barra nenhuma — mostra as alternativas e cala sobre quantos.
--}}
<div class="space-y-3">
    <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">
        {!! TextoDoEnunciado::paraHtml((string) $questao->enunciado_snapshot) !!}
    </p>

    @foreach ($questao->blocos() as $bloco)
        @if (($bloco['tipo'] ?? '') === 'codigo')
            <x-bloco-codigo :linguagem="$bloco['linguagem'] ?? 'plaintext'"
                            :rotulo="$bloco['linguagem'] ?? null">{{ $bloco['conteudo'] }}</x-bloco-codigo>
        @elseif (($bloco['tipo'] ?? '') === 'imagem' && ! empty($bloco['caminho']))
            <figure>
                <img src="{{ Storage::disk('public')->url($bloco['caminho']) }}"
                     alt="{{ $bloco['legenda'] ?: 'Imagem do enunciado' }}"
                     class="max-h-72 rounded-lg border border-slate-200 dark:border-slate-800">
                @if (! empty($bloco['legenda']))
                    <figcaption class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $bloco['legenda'] }}</figcaption>
                @endif
            </figure>
        @elseif (! empty($bloco['conteudo']))
            <p class="whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">
                {!! TextoDoEnunciado::paraHtml((string) $bloco['conteudo']) !!}
            </p>
        @endif
    @endforeach

    <ul class="space-y-1.5">
        @foreach ($lista as $alternativa)
            @php
                $letra = mb_strtoupper((string) $alternativa['letra']);
                $certa = (bool) $alternativa['correta'];
                // Com a letra na mão não se deduz nada; sem ela, só se
                // sabe que quem acertou marcou a correta.
                $marcou = $letraMarcada === null ? ($certa && $acertou === true) : $letraMarcada === $letra;
                $distratora = in_array($letra, $letrasDistratoras, true);
            @endphp

            <li wire:key="alt-{{ $questao->id }}-{{ $letra }}"
                @class([
                    'rounded-lg border px-3 py-2 text-sm',
                    'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10' => $certa,
                    'border-amber-300 bg-amber-50 dark:border-amber-500/40 dark:bg-amber-500/10' => ! $certa && $distratora,
                    'border-slate-200 dark:border-slate-800' => ! $certa && ! $distratora,
                ])>
                <div class="flex items-start gap-2">
                    <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $alternativa['letra'] }})</span>
                    <span class="min-w-0 flex-1 text-slate-700 dark:text-slate-200">{{ $alternativa['texto'] }}</span>

                    {{-- Nunca só por cor: cada marca leva o seu rótulo. --}}
                    <span class="flex shrink-0 flex-wrap items-center gap-1">
                        @if ($comContagem)
                            <span class="tabular-nums text-xs text-slate-500 dark:text-slate-400">
                                {{ $alternativa['total'] }} ·
                                {{ number_format($alternativa['percentual'], 0) }}%
                            </span>
                        @endif
                        @if ($certa)
                            <x-badge cor="verde" rotulo="Correta"/>
                        @elseif ($distratora)
                            <x-badge cor="amarelo" rotulo="A mais marcada entre as erradas"/>
                        @endif
                        @if ($marcou)
                            <x-badge cor="azul" :rotulo="($aluno ? $aluno.' marcou' : 'O aluno marcou')"/>
                        @endif
                    </span>
                </div>

                @if ($comContagem)
                    <span class="mt-1.5 barra-alternativa {{ $certa ? 'correta' : ($distratora ? 'distrator' : '') }}"
                          role="img"
                          aria-label="{{ $alternativa['total'] }} aluno(s), {{ number_format($alternativa['percentual'], 0) }}%">
                        {{-- Alternativa que ninguém marcou não ganha
                             traço nenhum: o 0% já está escrito ao lado. --}}
                        <span style="width: {{ $alternativa['total'] === 0 ? 0 : max(2, (float) $alternativa['percentual']) }}%"></span>
                    </span>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($acertou === false && $letraMarcada === null)
        <x-alerta tipo="atencao">
            {{ $aluno ? $aluno.' errou' : 'O aluno errou' }} esta questão: marcou uma das outras alternativas.
            Qual delas, esta planilha não informa — ela traz apenas acerto ou erro.
        </x-alerta>
    @endif
</div>
