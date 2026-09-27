@props(['desabilitado' => false])

{{-- Ver a nota em input.blade.php: `@disabled()` na tag do componente
     impede o Blade de compilá-lo. --}}
    {{--
        `border` explícito e `px-3 py-2`: o preflight do Tailwind zera a
        largura da borda de todo elemento, e sem padding o texto encosta
        na linha. Sem o plugin `forms`, quem os declara é o componente.
    --}}
<select @disabled($desabilitado) {{ $attributes->class([
    'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-slate-900 shadow-sm transition',
    'focus:border-marca-500 focus:ring-2 focus:ring-marca-500/30',
    'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500',
    'dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100',
    'aria-[invalid=true]:border-rose-400',
]) }}>
    {{ $slot }}
</select>
