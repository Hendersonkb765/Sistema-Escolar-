@props([
    'rotulo',
    'valor',
    'cor' => 'cinza',
    'href' => null,
    'detalhe' => null,
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
    $elemento = $href ? 'a' : 'div';
@endphp

<{{ $elemento }} @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'relative block overflow-hidden rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-slate-800 dark:bg-slate-900',
        'transition hover:border-slate-300 hover:shadow dark:hover:border-slate-700' => (bool) $href,
    ]) }}>
    <span class="absolute inset-y-0 left-0 w-1 {{ $barras[$cor] ?? $barras['cinza'] }}"></span>
    <p class="pl-2 text-sm font-medium text-slate-500 dark:text-slate-400">{{ $rotulo }}</p>
    <p class="pl-2 mt-1 text-2xl font-semibold tabular-nums text-slate-900 dark:text-slate-100">{{ $valor }}</p>
    @if ($detalhe)
        <p class="pl-2 mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $detalhe }}</p>
    @endif
</{{ $elemento }}>
