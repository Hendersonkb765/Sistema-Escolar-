@use('App\Support\TextoDoEnunciado')

@props([
    'questao',
    /** true/false quando se olha um aluno; null quando se olha a turma. */
    'acertou' => null,
    'aluno' => null,
])

{{--
    A questão como o aluno a viu: enunciado, blocos de apoio e as
    alternativas, com a correta assinalada.

    Sai do snapshot da prova, e não da questão original — uma análise de
    prova antiga tem de mostrar o que estava no papel naquele dia, mesmo
    que a questão tenha sido reescrita depois.

    Sobre "a resposta que o aluno deu": a planilha do leitor traz 1 ou 0,
    acerto ou erro, e nunca a letra marcada. Quem acertou marcou a
    correta — isso se deduz, e está escrito. De quem errou só se sabe que
    marcou outra, e dizer qual seria invenção.
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
        @foreach ($questao->alternativas_snapshot ?? [] as $alternativa)
            @php
                $certa = (bool) ($alternativa['correta'] ?? false);
                // Quem acertou marcou a correta: isso se deduz da planilha.
                $marcada = $certa && $acertou === true;
            @endphp

            <li @class([
                    'flex items-start gap-2 rounded-lg border px-3 py-2 text-sm',
                    'border-emerald-300 bg-emerald-50 dark:border-emerald-500/40 dark:bg-emerald-500/10' => $certa,
                    'border-slate-200 dark:border-slate-800' => ! $certa,
                ])>
                <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $alternativa['letra'] }})</span>
                <span class="min-w-0 flex-1 text-slate-700 dark:text-slate-200">{{ $alternativa['texto'] }}</span>

                {{-- Nunca só por cor: cada marca leva o seu rótulo. --}}
                @if ($certa)
                    <x-badge cor="verde" rotulo="Correta"/>
                @endif
                @if ($marcada)
                    <x-badge cor="azul" :rotulo="($aluno ? $aluno.' marcou' : 'O aluno marcou')"/>
                @endif
            </li>
        @endforeach
    </ul>

    @if ($acertou === false)
        <x-alerta tipo="atencao">
            {{ $aluno ? $aluno.' errou' : 'O aluno errou' }} esta questão: marcou uma das outras alternativas.
            Qual delas, a planilha do leitor não informa — ela traz apenas acerto ou erro.
        </x-alerta>
    @endif
</div>
