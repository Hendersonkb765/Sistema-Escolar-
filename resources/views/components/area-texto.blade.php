@props([
    'desabilitado' => false,
    'linhas' => 3,
])

<textarea rows="{{ $linhas }}" @disabled($desabilitado) {{ $attributes->class([
    'block w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm transition',
    'placeholder:text-slate-400 focus:border-marca-500 focus:ring-2 focus:ring-marca-500/30',
    'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500',
    'dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-900',
]) }}>{{ $slot }}</textarea>
