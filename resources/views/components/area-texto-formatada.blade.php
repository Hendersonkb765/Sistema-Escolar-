@use('App\Support\TextoDoEnunciado')

@props([
    'desabilitado' => false,
    'linhas' => 3,
    'valor' => '',
])

{{--
    Campo de texto com negrito e itálico.

    A formatação é guardada como marca no próprio texto (`**negrito**`,
    `*itálico*`) — ver `App\Support\TextoDoEnunciado`. Os botões só
    envolvem a seleção; quem interpreta as marcas é o PHP, em um lugar
    só, e por isso a prévia abaixo usa o mesmo parser que a prova
    impressa usará.
--}}
<div x-data="marcasDeTexto" class="space-y-1.5">
    @unless ($desabilitado)
        <div class="flex flex-wrap items-center gap-1">
            <button type="button" x-on:click="envolver('{{ TextoDoEnunciado::MARCA_NEGRITO }}')"
                    title="Negrito — envolve a seleção em ** **"
                    class="rounded border border-slate-300 px-2 py-1 text-sm font-bold leading-none text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                B
            </button>
            <button type="button" x-on:click="envolver('{{ TextoDoEnunciado::MARCA_ITALICO }}')"
                    title="Itálico — envolve a seleção em * *"
                    class="rounded border border-slate-300 px-2 py-1 text-sm italic leading-none text-slate-700 transition hover:bg-slate-100 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800">
                I
            </button>
            <span class="text-xs text-slate-500 dark:text-slate-400">
                Selecione o texto e clique, ou escreva <code>**negrito**</code> e <code>*itálico*</code>.
            </span>
        </div>
    @endunless

    <x-area-texto x-ref="campo" :linhas="$linhas" :desabilitado="$desabilitado" {{ $attributes }}/>

    @if (TextoDoEnunciado::temFormatacao((string) $valor))
        <div class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 dark:border-slate-800 dark:bg-slate-900/40">
            <p class="text-xs font-medium uppercase tracking-wide text-slate-400">Prévia</p>
            <p class="mt-0.5 whitespace-pre-line text-sm text-slate-700 dark:text-slate-200">
                {!! TextoDoEnunciado::paraHtml((string) $valor) !!}
            </p>
        </div>
    @endif
</div>
