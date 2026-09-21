@props([
    'titulo' => null,
    'descricao' => null,
])

<div {{ $attributes->class('rounded-xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900') }}>
    @if ($titulo || $descricao || isset($acoes))
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-3 sm:px-6 dark:border-slate-800">
            <div class="min-w-0">
                @if ($titulo)
                    <h2 class="text-base font-semibold text-slate-900 dark:text-slate-100">{{ $titulo }}</h2>
                @endif
                @if ($descricao)
                    <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">{{ $descricao }}</p>
                @endif
            </div>
            @isset($acoes)
                <div class="flex shrink-0 items-center gap-2">{{ $acoes }}</div>
            @endisset
        </div>
    @endif

    <div {{ $attributes->only([])->class('px-4 py-4 sm:px-6') }}>
        {{ $slot }}
    </div>
</div>
