@props([
    'variante' => 'primario',
    'href' => null,
    'tipo' => 'button',
])

@php
    $variantes = [
        'primario' => 'bg-marca-600 text-white shadow-sm hover:bg-marca-700 focus-visible:outline-marca-600',
        'secundario' => 'bg-white text-slate-700 ring-1 ring-inset ring-slate-300 hover:bg-slate-50 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-700 dark:hover:bg-slate-800',
        'perigo' => 'bg-rose-600 text-white shadow-sm hover:bg-rose-700 focus-visible:outline-rose-600',
        'discreto' => 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800',
    ];

    $classes = [
        'inline-flex items-center justify-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium transition',
        'focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2',
        'disabled:cursor-not-allowed disabled:opacity-60',
        $variantes[$variante] ?? $variantes['primario'],
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>{{ $slot }}</a>
@else
    <button type="{{ $tipo }}" {{ $attributes->class($classes) }}>{{ $slot }}</button>
@endif
