@props(['desabilitado' => false])

{{-- Ver a nota em input.blade.php: `@disabled()` na tag do componente
     impede o Blade de compilá-lo. --}}
<select @disabled($desabilitado) {{ $attributes->class([
    'block w-full rounded-lg border-slate-300 bg-white text-sm text-slate-900 shadow-sm transition',
    'focus:border-marca-500 focus:ring-2 focus:ring-marca-500/30',
    'disabled:cursor-not-allowed disabled:bg-slate-50 disabled:text-slate-500',
    'dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100',
    'aria-[invalid=true]:border-rose-400',
]) }}>
    {{ $slot }}
</select>
