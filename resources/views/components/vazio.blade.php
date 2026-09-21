@props([
    'titulo' => 'Nada por aqui',
    'descricao' => null,
])

<div {{ $attributes->class('rounded-lg border border-dashed border-slate-300 px-6 py-12 text-center dark:border-slate-700') }}>
    <p class="text-sm font-medium text-slate-700 dark:text-slate-200">{{ $titulo }}</p>
    @if ($descricao)
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $descricao }}</p>
    @endif
    @isset($acoes)
        <div class="mt-4 flex justify-center gap-2">{{ $acoes }}</div>
    @endisset
</div>
