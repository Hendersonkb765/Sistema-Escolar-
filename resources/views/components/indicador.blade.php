@props([
    'rotulo',
    'valor',
    'cor' => 'cinza',
    'href' => null,
    'detalhe' => null,
    // SVG pronto, de Navegacao::icone(): o mesmo desenho do item do menu.
    'icone' => null,
])

@php
    $barras = [
        'verde' => 'bg-emerald-500',
        'amarelo' => 'bg-amber-500',
        'vermelho' => 'bg-rose-500',
        'azul' => 'bg-sky-500',
        'roxo' => 'bg-violet-500',
        'cinza' => 'bg-slate-400',
    ];
    // O fundo do ícone acompanha a barra: a cor diz o estado, o desenho diz o assunto.
    $fundos = [
        'verde' => 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400',
        'amarelo' => 'bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400',
        'vermelho' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400',
        'azul' => 'bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400',
        'roxo' => 'bg-violet-50 text-violet-600 dark:bg-violet-500/10 dark:text-violet-400',
        'cinza' => 'bg-slate-100 text-slate-500 dark:bg-slate-500/10 dark:text-slate-400',
    ];
    $elemento = $href ? 'a' : 'div';
@endphp

<{{ $elemento }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'relative block overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900',
        'transition hover:border-slate-300 hover:shadow dark:hover:border-slate-700' => (bool) $href,
    ]) }}>
    <span class="absolute inset-y-0 left-0 w-1 {{ $barras[$cor] ?? $barras['cinza'] }}"></span>
    <div class="flex items-start justify-between gap-2 pl-2">
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">{{ $rotulo }}</p>
        @if ($icone)
            <span data-icone-do-indicador class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg {{ $fundos[$cor] ?? $fundos['cinza'] }}">{!! $icone !!}</span>
        @endif
    </div>
    <p class="pl-2 mt-1 text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $valor }}</p>
    @if ($detalhe)
        <p class="pl-2 mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $detalhe }}</p>
    @endif
</{{ $elemento }}>
