@props([
    'desabilitado' => false,
    'linhas' => 3,
])

    {{--
        `border` explícito e `px-3 py-2`: o preflight do Tailwind zera a
        largura da borda de todo elemento, e sem padding o texto encosta
        na linha. Sem o plugin `forms`, quem os declara é o componente.
    --}}
<textarea rows="{{ $linhas }}" @disabled($desabilitado) {{ $attributes->class([
    'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition',
    'placeholder:text-slate-400 focus:border-marca-500 focus:ring-2 focus:ring-marca-500/30',
    'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500',
    'dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:disabled:bg-slate-900',
]) }}>{{ $slot }}</textarea>
