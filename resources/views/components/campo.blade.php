@props([
    'rotulo' => null,
    'para' => null,
    'erro' => null,
    'ajuda' => null,
    'obrigatorio' => false,
])

<div {{ $attributes->only('class')->class('space-y-1.5') }}>
    @if ($rotulo)
        <label @if ($para) for="{{ $para }}" @endif
            class="block text-sm font-medium text-slate-700 dark:text-slate-300">
            {{ $rotulo }}
            @if ($obrigatorio)
                <span class="text-rose-500" aria-hidden="true">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($ajuda && ! $erro)
        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $ajuda }}</p>
    @endif

    @if ($erro)
        <p class="text-xs font-medium text-rose-600 dark:text-rose-400">{{ $erro }}</p>
    @endif
</div>
