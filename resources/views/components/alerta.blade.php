@props([
    'tipo' => 'info',
    'titulo' => null,
])

@php
    $estilos = [
        'sucesso' => 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200',
        'erro' => 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200',
        'atencao' => 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200',
        'info' => 'border-sky-200 bg-sky-50 text-sky-800 dark:border-sky-500/30 dark:bg-sky-500/10 dark:text-sky-200',
    ];
@endphp

<div role="alert" {{ $attributes->class(['rounded-lg border px-4 py-3 text-sm', $estilos[$tipo] ?? $estilos['info']]) }}>
    @if ($titulo)
        <p class="font-semibold">{{ $titulo }}</p>
    @endif
    <div @class(['mt-0.5' => (bool) $titulo])>{{ $slot }}</div>
</div>
